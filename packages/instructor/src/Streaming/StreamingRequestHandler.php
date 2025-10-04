<?php declare(strict_types=1);

namespace Cognesy\Instructor\Streaming;

use Cognesy\Instructor\Contracts\CanExecuteStructuredOutput;
use Cognesy\Instructor\Contracts\CanGeneratePartials;
use Cognesy\Instructor\Core\InferenceProvider;
use Cognesy\Instructor\Core\ValidationRetryHandler;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Polyglot\Inference\Collections\PartialInferenceResponseList;
use Cognesy\Polyglot\Inference\Creation\InferenceResponseFactory;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Utils\Result\Result;
use Generator;

class StreamingRequestHandler implements CanExecuteStructuredOutput
{
    public function __construct(
        private InferenceProvider $inferenceProvider,
        private CanGeneratePartials $partialsGenerator,
        private ValidationRetryHandler $retryHandler,
    ) {}

    /**
     * Executes streaming request and yields partial updates followed by final result.
     *
     * @param StructuredOutputExecution $execution
     * @return Generator<StructuredOutputExecution>
     */
    #[\Override]
    public function nextUpdate(StructuredOutputExecution $execution): Generator {
        $processingResult = Result::failure("No response generated");
        $inferenceResponse = null;
        $partialResponses = PartialInferenceResponseList::empty();

        while ($processingResult->isFailure() && !$execution->maxRetriesReached()) {
            // Get streaming responses
            $stream = $this->inferenceProvider->getInference($execution)->stream()->responses();
            $responseModel = $execution->responseModel();
            assert($responseModel !== null, 'Response model cannot be null');

            // Generate and yield partial responses
            $partialResponseStream = $this->partialsGenerator->makePartialResponses($stream, $responseModel);
            $partialResponses = PartialInferenceResponseList::empty();

            /** @var PartialInferenceResponse $partialResponse */
            foreach ($partialResponseStream as $partialResponse) {
                $partialResponses = $partialResponses->withNewPartialResponse($partialResponse);
                $aggregated = InferenceResponseFactory::fromPartialResponses($partialResponses)
                    ->withValue($partialResponse->value());
                $updatedExecution = $execution->withCurrentAttempt(
                    inferenceResponse: $aggregated,
                    partialInferenceResponses: $partialResponses,
                    errors: $this->retryHandler->errors(),
                );
                yield $updatedExecution;
            }

            // Validate final response
            //$partialResponses = $this->partialsGenerator->partialResponses();
            $inferenceResponse = InferenceResponseFactory::fromPartialResponses($partialResponses);
            $processingResult = $this->retryHandler->validateResponse(
                $inferenceResponse,
                $responseModel,
                $execution->outputMode()
            );

            // Handle validation errors
            if ($processingResult->isFailure()) {
                $execution = $this->retryHandler->handleError(
                    $processingResult,
                    $execution,
                    $inferenceResponse,
                    $partialResponses
                );
            }
        }

        // Finalize result or throw
        assert($inferenceResponse !== null, 'Inference response must be defined after loop');
        $value = $this->retryHandler->finalizeOrThrow($execution, $processingResult);

        // Yield final response with value
        yield $execution->withSuccessfulAttempt(
            inferenceResponse: $inferenceResponse->withValue($value),
            partialInferenceResponses: $partialResponses,
            returnedValue: $value,
        );
    }
}
