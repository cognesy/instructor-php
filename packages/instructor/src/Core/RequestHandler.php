<?php
namespace Cognesy\Instructor\Core;

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Instructor\Contracts\CanGeneratePartials;
use Cognesy\Instructor\Contracts\CanGenerateResponse;
use Cognesy\Instructor\Contracts\CanMaterializeRequest;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Instructor\Events\Request\NewValidationRecoveryAttempt;
use Cognesy\Instructor\Events\Request\ValidationRecoveryLimitReached;
use Cognesy\Instructor\Events\StructuredOutput\ResponseGenerated;
use Cognesy\Instructor\Validation\Exceptions\ValidationException;
use Cognesy\Polyglot\LLM\Data\InferenceResponse;
use Cognesy\Polyglot\LLM\Data\PartialInferenceResponse;
use Cognesy\Polyglot\LLM\Enums\OutputMode;
use Cognesy\Polyglot\LLM\Inference;
use Cognesy\Polyglot\LLM\LLMProvider;
use Cognesy\Polyglot\LLM\PendingInference;
use Cognesy\Utils\Json\Json;
use Cognesy\Utils\Result\Result;
use Generator;

class RequestHandler
{
    protected readonly CanGenerateResponse $responseGenerator;
    protected readonly CanGeneratePartials $partialsGenerator;
    protected readonly CanMaterializeRequest $requestMaterializer;
    protected readonly LLMProvider $llmProvider;
    protected readonly CanHandleEvents $events;

    protected int $retries = 0;
    protected array $errors = [];

    public function __construct(
        CanGenerateResponse   $responseGenerator,
        CanGeneratePartials   $partialsGenerator,
        CanMaterializeRequest $requestMaterializer,
        LLMProvider           $llmProvider,
        CanHandleEvents       $events,
    ) {
        $this->responseGenerator = $responseGenerator;
        $this->partialsGenerator = $partialsGenerator;
        $this->requestMaterializer = $requestMaterializer;
        $this->llmProvider = $llmProvider;
        $this->events = $events;

        $this->retries = 0;
        $this->errors = [];
    }

    // PUBLIC //////////////////////////////////////////////////////////////

    /**
     * Generates response value
     */
    public function responseFor(StructuredOutputRequest $request) : InferenceResponse {
        $processingResult = Result::failure("No response generated");
        while ($processingResult->isFailure() && !$this->maxRetriesReached($request)) {
            $llmResponse = $this->getInference($request)->response();
            $llmResponse->withContent(match($request->mode()) {
                OutputMode::Text => $llmResponse->content(),
                OutputMode::Tools => $llmResponse->toolCalls()->first()?->argsAsJson()
                    ?? $llmResponse->content() // fallback if no tool calls - some LLMs return just a string
                    ?? '',
                // for OutputMode::MdJson, OutputMode::Json, OutputMode::JsonSchema try extracting JSON from content
                // and replacing original content with it
                default => Json::fromString($llmResponse->content())->toString(),
            });
            $partialResponses = [];
            $processingResult = $this->processResponse($request, $llmResponse, $partialResponses);
        }

        $value = $this->finalizeResult($processingResult, $request, $llmResponse, $partialResponses);

        return $llmResponse->withValue($value);
    }

    /**
     * Yields response value versions based on streamed responses
     *
     * @param StructuredOutputRequest $request
     * @return Generator<PartialInferenceResponse>
     */
    public function streamResponseFor(StructuredOutputRequest $request) : Generator {
        $processingResult = Result::failure("No response generated");
        while ($processingResult->isFailure() && !$this->maxRetriesReached($request)) {
            $stream = $this->getInference($request)->stream()->responses();
            yield from $this->partialsGenerator->getPartialResponses($stream, $request->responseModel());

            $llmResponse = $this->partialsGenerator->getCompleteResponse();
            $partialResponses = $this->partialsGenerator->partialResponses();
            $processingResult = $this->processResponse($request, $llmResponse, $partialResponses);
        }

        $value = $this->finalizeResult($processingResult, $request, $llmResponse, $partialResponses);

        yield $llmResponse->withValue($value);
    }

    // INTERNAL ///////////////////////////////////////////////////////////

    protected function getInference(StructuredOutputRequest $request) : PendingInference {
        return (new Inference(events: $this->events))
            ->withLLMProvider($this->llmProvider)
            ->with(
                messages: $this->requestMaterializer->toMessages($request),
                model: $request->model(),
                tools: $request->toolCallSchema(),
                toolChoice: $request->toolChoice(),
                responseFormat: $request->responseFormat(),
                options: $request->options(),
                mode: $request->mode()
            )
            ->create();
    }

    protected function processResponse(StructuredOutputRequest $request, InferenceResponse $llmResponse, array $partialResponses) : Result {
        // we have LLMResponse here - let's process it: deserialize, validate, transform
        $processingResult = $this->responseGenerator->makeResponse(
            response: $llmResponse,
            responseModel: $request->responseModel(),
            mode: $request->mode()
        );

        if ($processingResult->isFailure()) {
            // retry - we have not managed to deserialize, validate or transform the response
            $this->handleError($processingResult, $request, $llmResponse, $partialResponses);
        }

        return $processingResult;
    }

    protected function finalizeResult(Result $processingResult, StructuredOutputRequest $request, InferenceResponse $llmResponse, array $partialResponses) : mixed {
        if ($processingResult->isFailure()) {
            $this->events->dispatch(new ValidationRecoveryLimitReached($this->retries, $this->errors));
            throw new ValidationException(
                message: "Validation recovery attempts limit reached after {$this->retries} attempt(s) due to: ".implode(", ", $this->errors),
                errors: $this->errors,
            );
        }

        // get final value
        $value = $processingResult->unwrap();
        // store response
        $request->setResponse($request->messages()->toArray(), $llmResponse, $partialResponses, $value); // TODO: tx messages to Scripts
        // notify on response generation
        $this->events->dispatch(new ResponseGenerated($value));

        return $value;
    }

    protected function handleError(Result $processingResult, StructuredOutputRequest $request, InferenceResponse $llmResponse, array $partialResponses) : void {
        $error = $processingResult->error();
        $this->errors = is_array($error) ? $error : [$error];

        // store failed response
        $request->addFailedResponse($request->messages()->toArray(), $llmResponse, $partialResponses, $this->errors); // TODO: tx messages to Scripts
        $this->retries++;
        if (!$this->maxRetriesReached($request)) {
            $this->events->dispatch(new NewValidationRecoveryAttempt($this->retries, $this->errors));
        }
    }

    protected function maxRetriesReached(StructuredOutputRequest $request) : bool {
        return $this->retries > $request->maxRetries();
    }
}
