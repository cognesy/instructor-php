<?php declare(strict_types=1);

namespace Cognesy\Instructor\Core;

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Http\HttpClient;
use Cognesy\Instructor\Contracts\CanGeneratePartials;
use Cognesy\Instructor\Contracts\CanGenerateResponse;
use Cognesy\Instructor\Contracts\CanMaterializeRequest;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Events\Request\NewValidationRecoveryAttempt;
use Cognesy\Instructor\Events\Request\StructuredOutputRecoveryLimitReached;
use Cognesy\Instructor\Exceptions\StructuredOutputRecoveryException;
use Cognesy\Polyglot\Inference\Collections\PartialInferenceResponseList;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\InferenceResponseFactory;
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
        $this->errors = [];
    }

    // PUBLIC //////////////////////////////////////////////////////////////

    /**
     * Generates response value
     */
    public function executionResultFor(StructuredOutputExecution $execution): StructuredOutputExecution {
        $processingResult = Result::failure("No response generated");
        while ($processingResult->isFailure() && !$execution->maxRetriesReached()) {
            $inferenceResponse = $this->getInference($execution)->response();
            $inferenceResponse = $inferenceResponse->withContent(match ($execution->outputMode()) {
                OutputMode::Text => $inferenceResponse->content(),
                OutputMode::Tools => $inferenceResponse->toolCalls()->first()?->argsAsJson()
                    ?? $inferenceResponse->content() // fallback if no tool calls - some LLMs return just a string
                    ?? '',
                // for OutputMode::MdJson, OutputMode::Json, OutputMode::JsonSchema try extracting JSON from content
                // and replacing original content with it
                default => Json::fromString($inferenceResponse->content())->toString(),
            });
            $partialResponses = PartialInferenceResponseList::empty();
            $processingResult = $this->responseGenerator->makeResponse(
                response: $inferenceResponse,
                responseModel: $execution->responseModel(),
                mode: $execution->outputMode(),
            );
            if ($processingResult->isFailure()) {
                $execution = $this->handleError($processingResult, $execution, $inferenceResponse, $partialResponses);
            }
        }

        $returnedValue = $this->finalizeResult($execution, $processingResult);

        return $execution->withSuccessfulAttempt(
            inferenceResponse: $inferenceResponse->withValue($returnedValue),
            partialInferenceResponses: $partialResponses,
            returnedValue: $returnedValue
        );
    }

    /**
     * Yields updated structured output executions based on streamed LLM responses.
     *
     * @param StructuredOutputExecution $execution
     * @return Generator<StructuredOutputExecution>
     */
    public function streamUpdatesFor(StructuredOutputExecution $execution): Generator {
        $processingResult = Result::failure("No response generated");

        while ($processingResult->isFailure() && !$execution->maxRetriesReached()) {
            $stream = $this->getInference($execution)->stream()->responses();
            $partialResponseStream = $this->partialsGenerator->getPartialResponses($stream, $execution->responseModel());

            $partialResponses = PartialInferenceResponseList::empty();
            /** @var PartialInferenceResponse $partialResponse */
            foreach ($partialResponseStream as $partialResponse) {
                $partialResponses = $partialResponses->withNewPartialResponse($partialResponse);
                $aggregated = InferenceResponseFactory::fromPartialResponses($partialResponses)
                    ->withValue($partialResponse->value());
                $updatedExecution = $execution->withCurrentAttempt(
                    inferenceResponse: $aggregated,
                    partialInferenceResponses: $partialResponses,
                    errors: $this->errors,
                );
                yield $updatedExecution;
            }

            // we're done streaming - get final response
            $inferenceResponse = $this->partialsGenerator->getCompleteResponse();
            $partialResponses = $this->partialsGenerator->partialResponses();
            $processingResult = $this->responseGenerator->makeResponse(
                response: $inferenceResponse,
                responseModel: $execution->responseModel(),
                mode: $execution->outputMode(),
            );
            if ($processingResult->isFailure()) {
                $execution = $this->handleError($processingResult, $execution, $inferenceResponse, $partialResponses);
            }
        }

        $value = $this->finalizeResult($execution, $processingResult);

        // Yield final response with value
        yield $execution->withSuccessfulAttempt(
            inferenceResponse: $inferenceResponse->withValue($value),
            partialInferenceResponses: $partialResponses,
            returnedValue: $value,
        );
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

    protected function finalizeResult(
        StructuredOutputExecution $execution,
        Result $processingResult,
    ): mixed {
        if ($processingResult->isFailure()) {
            $this->events->dispatch(new StructuredOutputRecoveryLimitReached(['retries' => $execution->attemptCount(), 'errors' => $this->errors]));
            throw new StructuredOutputRecoveryException(
                message: "Structured output recovery attempts limit reached after {$execution->attemptCount()} attempt(s) due to: "
                    . implode(", ", $this->errors),
                errors: $this->errors,
            );
        }
        return $processingResult->unwrap();
    }

    protected function handleError(
        Result $processingResult,
        StructuredOutputExecution $execution,
        InferenceResponse $inferenceResponse,
        PartialInferenceResponseList $partialResponses
    ): StructuredOutputExecution {
        assert($processingResult instanceof Failure);
        $request = $execution->request();
        $error = $processingResult->error();
        $this->errors = is_array($error) ? $error : [$error];
        // store failed response
        $execution = $execution->withFailedAttempt(
            inferenceResponse: $inferenceResponse,
            partialInferenceResponses: $partialResponses,
            errors: $this->errors
        );
        if (!$execution->maxRetriesReached()) {
            $this->events->dispatch(new NewValidationRecoveryAttempt(['retries' => $execution->attemptCount(), 'errors' => $this->errors]));
        }
        return $execution;
    }
}
