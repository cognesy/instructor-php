<?php declare(strict_types=1);

namespace Cognesy\Instructor\Core;

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Http\HttpClient;
use Cognesy\Instructor\Contracts\CanGeneratePartials;
use Cognesy\Instructor\Contracts\CanGenerateResponse;
use Cognesy\Instructor\Contracts\CanMaterializeRequest;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Instructor\Events\Request\NewValidationRecoveryAttempt;
use Cognesy\Instructor\Events\Request\ValidationRecoveryLimitReached;
use Cognesy\Instructor\Validation\Exceptions\ValidationException;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\LLMProvider;
use Cognesy\Polyglot\Inference\PendingInference;
use Cognesy\Utils\Json\Json;
use Cognesy\Utils\Result\Failure;
use Cognesy\Utils\Result\Result;
use Generator;

class RequestHandler
{
    protected readonly CanGenerateResponse $responseGenerator;
    protected readonly CanGeneratePartials $partialsGenerator;
    protected readonly CanMaterializeRequest $requestMaterializer;
    protected readonly LLMProvider $llmProvider;
    protected readonly CanHandleEvents $events;
    protected readonly ?HttpClient $httpClient;

    protected int $retries = 0;
    /** @var array<int, string> */
    protected array $errors = [];

    public function __construct(
        CanGenerateResponse $responseGenerator,
        CanGeneratePartials $partialsGenerator,
        CanMaterializeRequest $requestMaterializer,
        LLMProvider $llmProvider,
        CanHandleEvents $events,
        ?HttpClient $httpClient = null,
    ) {
        $this->responseGenerator = $responseGenerator;
        $this->partialsGenerator = $partialsGenerator;
        $this->requestMaterializer = $requestMaterializer;
        $this->llmProvider = $llmProvider;
        $this->events = $events;
        $this->httpClient = $httpClient;

        $this->retries = 0;
        $this->errors = [];
    }

    // PUBLIC //////////////////////////////////////////////////////////////

    /**
     * Generates response value
     */
    public function responseFor(StructuredOutputRequest $request): InferenceResponse {
        $processingResult = Result::failure("No response generated");
        while ($processingResult->isFailure() && !$this->maxRetriesReached($request)) {
            $inferenceResponse = $this->getInference($request)->response();
            $inferenceResponse->withContent(match ($request->mode()) {
                OutputMode::Text => $inferenceResponse->content(),
                OutputMode::Tools => $inferenceResponse->toolCalls()->first()?->argsAsJson()
                    ?? $inferenceResponse->content() // fallback if no tool calls - some LLMs return just a string
                    ?? '',
                // for OutputMode::MdJson, OutputMode::Json, OutputMode::JsonSchema try extracting JSON from content
                // and replacing original content with it
                default => Json::fromString($inferenceResponse->content())->toString(),
            });
            $partialResponses = [];
            $processingResult = $this->processResponse($request, $inferenceResponse, $partialResponses);
        }

        $value = $this->finalizeResult($processingResult, $request, $inferenceResponse, $partialResponses);
        $inferenceResponse->withValue($value);

        return $inferenceResponse;
    }

    /**
     * Yields response value versions based on streamed responses
     *
     * @param StructuredOutputRequest $request
     * @return Generator<PartialInferenceResponse>
     */
    public function streamResponseFor(StructuredOutputRequest $request): Generator {
        $processingResult = Result::failure("No response generated");
        while ($processingResult->isFailure() && !$this->maxRetriesReached($request)) {
            $stream = $this->getInference($request)->stream()->responses();
            yield from $this->partialsGenerator->getPartialResponses($stream, $request->responseModel());

            $inferenceResponse = $this->partialsGenerator->getCompleteResponse();
            $partialResponses = $this->partialsGenerator->partialResponses();
            $processingResult = $this->processResponse($request, $inferenceResponse, $partialResponses);
        }

        $value = $this->finalizeResult($processingResult, $request, $inferenceResponse, $partialResponses);

        yield $inferenceResponse->withValue($value);
    }

    // INTERNAL ///////////////////////////////////////////////////////////

    protected function getInference(StructuredOutputRequest $request): PendingInference {
        $inference = (new Inference(events: $this->events))
            ->withLLMProvider($this->llmProvider);
        if ($this->httpClient !== null) {
            $inference = $inference->withHttpClient($this->httpClient);
        }
        return $inference
            ->with(
                messages: $this->requestMaterializer->toMessages($request),
                model: $request->model(),
                tools: $request->toolCallSchema(),
                toolChoice: $request->toolChoice(),
                responseFormat: $request->responseFormat(),
                options: $request->options(),
                mode: $request->mode(),
            )
            ->create();
    }

    protected function processResponse(
        StructuredOutputRequest $request,
        InferenceResponse $inferenceResponse,
        array $partialResponses
    ): Result {
        // we have InferenceResponse here - let's process it: deserialize, validate, transform
        $processingResult = $this->responseGenerator->makeResponse(
            response: $inferenceResponse,
            responseModel: $request->responseModel(),
            mode: $request->mode(),
        );

        if ($processingResult->isFailure()) {
            // retry - we have not managed to deserialize, validate or transform the response
            $this->handleError($processingResult, $request, $inferenceResponse, $partialResponses);
        }

        return $processingResult;
    }

    protected function finalizeResult(
        Result $processingResult,
        StructuredOutputRequest $request,
        InferenceResponse $inferenceResponse,
        array $partialResponses
    ): mixed {
        if ($processingResult->isFailure()) {
            $this->events->dispatch(new ValidationRecoveryLimitReached(['retries' => $this->retries, 'errors' => $this->errors]));
            throw new ValidationException(
                message: "Validation recovery attempts limit reached after {$this->retries} attempt(s) due to: " . implode(", ", $this->errors),
                errors: $this->errors,
            );
        }

        $value = $processingResult->unwrap();
        $request->setResponse($request->messages()->toArray(), $inferenceResponse, $partialResponses, $value); // TODO: tx messages to Scripts

        return $value;
    }

    protected function handleError(
        Failure $processingResult,
        StructuredOutputRequest $request,
        InferenceResponse $inferenceResponse,
        array $partialResponses
    ): void {
        $error = $processingResult->error();
        $this->errors = is_array($error) ? $error : [$error];

        // store failed response
        $request->addFailedResponse($request->messages()->toArray(), $inferenceResponse, $partialResponses, $this->errors); // TODO: tx messages to Scripts
        $this->retries++;
        if (!$this->maxRetriesReached($request)) {
            $this->events->dispatch(new NewValidationRecoveryAttempt(['retries' => $this->retries, 'errors' => $this->errors]));
        }
    }

    protected function maxRetriesReached(StructuredOutputRequest $request): bool {
        return $this->retries > $request->maxRetries();
    }
}
