<?php declare(strict_types=1);

namespace Cognesy\Instructor\Core;

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Http\HttpClient;
use Cognesy\Instructor\Contracts\CanGeneratePartials;
use Cognesy\Instructor\Contracts\CanGenerateResponse;
use Cognesy\Instructor\Contracts\CanMaterializeRequest;
use Cognesy\Instructor\Data\StructuredOutputExecution;
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
    public function responseFor(StructuredOutputExecution $execution): StructuredOutputExecution {
        $processingResult = Result::failure("No response generated");
        while ($processingResult->isFailure() && !$execution->maxRetriesReached()) {
            $inferenceResponse = $this->getInference($execution)->response();
            $inferenceResponse->withContent(match ($execution->outputMode()) {
                OutputMode::Text => $inferenceResponse->content(),
                OutputMode::Tools => $inferenceResponse->toolCalls()->first()?->argsAsJson()
                    ?? $inferenceResponse->content() // fallback if no tool calls - some LLMs return just a string
                    ?? '',
                // for OutputMode::MdJson, OutputMode::Json, OutputMode::JsonSchema try extracting JSON from content
                // and replacing original content with it
                default => Json::fromString($inferenceResponse->content())->toString(),
            });
            $partialResponses = [];
            $processingResult = $this->processResponse($execution, $inferenceResponse, $partialResponses);
        }

        $returnedValue = $this->finalizeResult($processingResult, $execution);

        return $execution->withSuccessfulAttempt(
            messages: $execution->request()->messages()->toArray(),
            inferenceResponse: $inferenceResponse->withValue($returnedValue),
            partialInferenceResponses: $partialResponses,
            returnedValue: $returnedValue
        );
    }

    /**
     * Yields response value versions based on streamed responses
     *
     * @param StructuredOutputExecution $execution
     * @return Generator<PartialInferenceResponse>
     */
    public function streamResponseFor(StructuredOutputExecution $execution): Generator {
        $processingResult = Result::failure("No response generated");
        while ($processingResult->isFailure() && !$execution->maxRetriesReached()) {
            $stream = $this->getInference($execution)->stream()->responses();
            yield from $this->partialsGenerator->getPartialResponses($stream, $execution->responseModel());

            $inferenceResponse = $this->partialsGenerator->getCompleteResponse();
            $partialResponses = $this->partialsGenerator->partialResponses();
            $processingResult = $this->processResponse($execution, $inferenceResponse, $partialResponses);
        }

        $value = $this->finalizeResult($processingResult, $execution, $inferenceResponse, $partialResponses);

        yield $inferenceResponse->withValue($value);
    }

    // INTERNAL ///////////////////////////////////////////////////////////

    protected function getInference(StructuredOutputExecution $execution): PendingInference {
        $request = $execution->request();
        $responseModel = $execution->responseModel();

        $inference = (new Inference(events: $this->events))
            ->withLLMProvider($this->llmProvider);
        if ($this->httpClient !== null) {
            $inference = $inference->withHttpClient($this->httpClient);
        }
        return $inference
            ->with(
                messages: $this->requestMaterializer->toMessages($execution),
                model: $request->model(),
                tools: $responseModel->toolCallSchema(),
                toolChoice: $responseModel->toolChoice(),
                responseFormat: $responseModel->responseFormat(),
                options: $request->options(),
                mode: $execution->outputMode(),
            )
            ->create();
    }

    protected function processResponse(
        StructuredOutputExecution $execution,
        InferenceResponse $inferenceResponse,
        array $partialResponses
    ): Result {
        // we have InferenceResponse here - let's process it: deserialize, validate, transform
        $processingResult = $this->responseGenerator->makeResponse(
            response: $inferenceResponse,
            responseModel: $execution->responseModel(),
            mode: $execution->outputMode(),
        );

        if ($processingResult->isFailure()) {
            // retry - we have not managed to deserialize, validate or transform the response
            assert($processingResult instanceof Failure);
            $this->handleError($processingResult, $execution, $inferenceResponse, $partialResponses);
        }

        return $processingResult;
    }

    protected function finalizeResult(
        Result $processingResult,
        StructuredOutputExecution $execution,
    ): mixed {
        if ($processingResult->isFailure()) {
            $this->events->dispatch(new ValidationRecoveryLimitReached(['retries' => $execution->attemptCount(), 'errors' => $this->errors]));
            throw new ValidationException(
                message: "Validation recovery attempts limit reached after {$execution->attemptCount()} attempt(s) due to: " . implode(", ", $this->errors),
                errors: $this->errors,
            );
        }
        return $processingResult->unwrap();
    }

    protected function handleError(
        Failure $processingResult,
        StructuredOutputExecution $execution,
        InferenceResponse $inferenceResponse,
        array $partialResponses
    ): void {
        $request = $execution->request();
        $error = $processingResult->error();
        $this->errors = is_array($error) ? $error : [$error];

        // store failed response
        $execution->withFailedAttempt(
            messages: $request->messages()->toArray(), // TODO: set Messages not array?
            inferenceResponse: $inferenceResponse,
            partialInferenceResponses: $partialResponses,
            errors: $this->errors
        );
        $this->retries++;
        if (!$execution->maxRetriesReached()) {
            $this->events->dispatch(new NewValidationRecoveryAttempt(['retries' => $this->retries, 'errors' => $this->errors]));
        }
    }
}
