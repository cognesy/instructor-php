<?php declare(strict_types=1);

namespace Cognesy\Instructor\Core;

use Cognesy\Instructor\Contracts\CanDetermineRetry;
use Cognesy\Instructor\Contracts\CanGenerateResponse;
use Cognesy\Instructor\Contracts\CanHandleStructuredOutputAttempts;
use Cognesy\Instructor\Contracts\CanStreamStructuredOutputUpdates;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;

/**
 * Orchestrates full structured output execution with retry logic.
 *
 * Composition: Wraps a stream iterator (PartialStreamingUpdateGenerator or StreamingUpdatesGenerator)
 * and adds validation + retry logic on top.
 *
 * Scope: Multiple attempts (retry loop)
 * Responsibility:
 * - Delegate chunk processing to stream iterator
 * - Detect when stream is exhausted
 * - Validate final response
 * - Apply retry policy on validation failures
 * - Finalize successful attempts
 *
 * Design: This is the composable orchestrator that makes stream iterators retry-aware.
 */
final readonly class AttemptIterator implements CanHandleStructuredOutputAttempts
{
    public function __construct(
        private CanStreamStructuredOutputUpdates $streamIterator,
        private CanGenerateResponse $responseGenerator,
        private CanDetermineRetry $retryPolicy,
    ) {}

    #[\Override]
    public function hasNext(StructuredOutputExecution $execution): bool {
        return match(true) {
            $execution->isFinalized() => false,
            $execution->isAttemptActive() => $this->streamIterator->hasNext($execution),
            default => !$execution->maxRetriesReached(),
        };
    }

    #[\Override]
    public function nextUpdate(StructuredOutputExecution $execution): StructuredOutputExecution {
        if (!$execution->isAttemptActive()) {
            return $this->startNewAttempt($execution);
        }
        $updated = $this->streamIterator->nextChunk($execution);
        return match(true) {
            $this->didStreamJustFinish($execution, $updated) => $this->finalizeAttempt($updated),
            default => $updated,
        };
    }

    // INTERNAL ////////////////////////////////////////////////////////////////

    /**
     * Check if stream transitioned from active to exhausted.
     */
    private function didStreamJustFinish(
        StructuredOutputExecution $before,
        StructuredOutputExecution $after,
    ): bool {
        return $before->isAttemptActive() && !$after->isAttemptActive();
    }

    /**
     * Start a new attempt by initializing stream.
     * Delegates to stream iterator.
     */
    private function startNewAttempt(StructuredOutputExecution $execution): StructuredOutputExecution {
        $updated = $this->streamIterator->nextChunk($execution);
        return match(true) {
            !$updated->isAttemptActive() => $this->finalizeAttempt($updated),
            default => $updated,
        };
    }

    /**
     * Finalize an attempt after stream is exhausted.
     * Validates response and applies retry policy on failures.
     */
    private function finalizeAttempt(StructuredOutputExecution $execution): StructuredOutputExecution {
        $responseModel = $execution->responseModel();
        assert($responseModel !== null, 'Response model cannot be null');

        $streamState = $execution->attemptState();
        assert($streamState !== null, 'Streaming state must exist when finalizing');

        $finalInference = $streamState->lastInference() ?? InferenceResponse::empty();
        $partial = $streamState->accumulatedPartial();

        // Validate response
        $validationResult = $this->responseGenerator->makeResponse(
            $finalInference,
            $responseModel,
            $execution->outputMode()
        );

        // Success - finalize execution
        if ($validationResult->isSuccess()) {
            $finalValue = $validationResult->unwrap();

            return $execution->withSuccessfulAttempt(
                inferenceResponse: $finalInference->withValue($finalValue),
                partialInferenceResponse: $partial,
                returnedValue: $finalValue,
            );
        }

        // Failure - first record the failed attempt to advance attempt count
        $failed = $this->retryPolicy->recordFailure(
            $execution,
            $validationResult,
            $finalInference,
            $partial,
        );

        // Decide on retry using UPDATED execution (includes this failure)
        if ($this->retryPolicy->shouldRetry($failed, $validationResult)) {
            return $this->retryPolicy->prepareRetry($failed);
        }

        // No more retries - finalize or throw based on UPDATED execution
        $this->retryPolicy->finalizeOrThrow($failed, $validationResult);

        // If finalizeOrThrow didn't throw (defensive), return failed state
        return $failed;
    }
}
