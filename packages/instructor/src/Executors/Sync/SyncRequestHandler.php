<?php declare(strict_types=1);

namespace Cognesy\Instructor\Executors\Sync;

use Cognesy\Instructor\Contracts\CanExecuteStructuredOutput;
use Cognesy\Instructor\Contracts\CanGenerateResponse;
use Cognesy\Instructor\Core\InferenceProvider;
use Cognesy\Instructor\Core\RetryHandler;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Polyglot\Inference\Collections\PartialInferenceResponseList;
use Cognesy\Utils\Result\Result;
use Generator;
use JetBrains\PhpStorm\Deprecated;

/**
 * @deprecated Replaced by SyncUpdateGenerator + AttemptIterator pattern.
 * This handler embeds retry logic, which is now extracted to AttemptIterator + DefaultRetryPolicy.
 * Will be removed in future version after refactoring is complete and tested.
 */
#[Deprecated(
    reason: 'Use SyncUpdateGenerator + AttemptIterator instead',
    replacement: '%class%\\Executors\\Sync\\SyncUpdateGenerator with Core\\AttemptIterator'
)]
class SyncRequestHandler implements CanExecuteStructuredOutput
{
    private ResponseNormalizer $normalizer;

    public function __construct(
        private InferenceProvider $inferenceProvider,
        private CanGenerateResponse $processor,
        private RetryHandler $retryHandler,
    ) {
        $this->normalizer = new ResponseNormalizer();
    }

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

            // Process response (deserialize + validate + transform)
            $responseModel = $execution->responseModel();
            assert($responseModel !== null, 'Response model cannot be null');
            $processingResult = $this->processor->makeResponse($inferenceResponse, $responseModel, $execution->outputMode());

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
