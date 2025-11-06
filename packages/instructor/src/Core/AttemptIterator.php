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
        private CanStreamStructuredOutputUpdates $streamIterator,  // Composable!
        private CanGenerateResponse $responseGenerator,
        private CanDetermineRetry $retryPolicy,  // Pluggable!
    ) {}

    #[\Override]
    public function hasNext(StructuredOutputExecution $execution): bool {
        // Finalized = done
        if ($execution->isFinalized()) {
            return false;
        }

        // Currently processing an attempt
        if ($execution->isAttemptActive()) {
            return $this->streamIterator->hasNext($execution);
        }

        // Can start new attempt if not max retries
        return !$execution->maxRetriesReached();
    }

    #[\Override]
    public function nextUpdate(StructuredOutputExecution $execution): StructuredOutputExecution {
        // If attempt is active, delegate to stream iterator
        if ($execution->isAttemptActive()) {
            $updated = $this->streamIterator->nextChunk($execution);

            // Check if stream just finished
            if ($this->didStreamJustFinish($execution, $updated)) {
                // Stream exhausted - validate and decide retry
                return $this->finalizeAttempt($updated);
            }

            return $updated;
        }

        // No active stream - start new attempt
        return $this->startNewAttempt($execution);
    }

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
        // Delegate to stream iterator to initialize stream
        // (stream iterator will create fresh streaming state)
        $updated = $this->streamIterator->nextChunk($execution);

        // Check if attempt finished immediately (e.g., sync single-chunk execution)
        if (!$updated->isAttemptActive()) {
            return $this->finalizeAttempt($updated);
        }

        return $updated;
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
        $partials = $streamState->accumulatedPartials();

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
                partialInferenceResponses: $partials,
                returnedValue: $finalValue,
            );
        }

        // Failure - first record the failed attempt to advance attempt count
        $failed = $this->retryPolicy->recordFailure(
            $execution,
            $validationResult,
            $finalInference,
            $partials,
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
