<?php declare(strict_types=1);

namespace Cognesy\Instructor\Core;

use Cognesy\Instructor\Contracts\CanExecuteStructuredOutput;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Polyglot\Inference\Collections\PartialInferenceResponseList;
use Cognesy\Utils\Result\Result;
use Generator;

class SyncRequestHandler implements CanExecuteStructuredOutput
{
    public function __construct(
        private InferenceProvider $inferenceProvider,
        private ResponseNormalizer $normalizer,
        private ValidationRetryHandler $retryHandler,
    ) {}

    /**
     * Executes synchronous request and yields final result once.
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
            // Get inference response
            $inferenceResponse = $this->inferenceProvider->getInference($execution)->response();

            // Normalize content based on output mode
            $inferenceResponse = $this->normalizer->normalizeContent(
                $inferenceResponse,
                $execution->outputMode()
            );

            // Validate response
            $responseModel = $execution->responseModel();
            assert($responseModel !== null, 'Response model cannot be null');
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
        $returnedValue = $this->retryHandler->finalizeOrThrow($execution, $processingResult);

        // Yield final result
        yield $execution->withSuccessfulAttempt(
            inferenceResponse: $inferenceResponse->withValue($returnedValue),
            partialInferenceResponses: $partialResponses,
            returnedValue: $returnedValue
        );
    }
}
