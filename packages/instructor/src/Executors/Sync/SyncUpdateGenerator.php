<?php declare(strict_types=1);

namespace Cognesy\Instructor\Executors\Sync;

use Cognesy\Instructor\Contracts\CanStreamStructuredOutputUpdates;
use Cognesy\Instructor\Core\InferenceProvider;
use Cognesy\Instructor\Data\StructuredOutputAttemptState;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Enums\AttemptPhase;
use Cognesy\Polyglot\Inference\Collections\PartialInferenceResponseList;

/**
 * Stream iterator for synchronous (non-streaming) execution.
 *
 * Scope: Single attempt only (does NOT handle validation or retries)
 * Pattern: Makes one inference request, yields one update
 *
 * Responsibility:
 * - Make non-streaming inference request
 * - Return single update (no actual streaming)
 * - Signal exhaustion immediately
 *
 * Design note: Sync execution is modeled as streaming with a single chunk.
 * This allows using the same AttemptIterator orchestrator for both sync and streaming.
 */
final readonly class SyncUpdateGenerator implements CanStreamStructuredOutputUpdates
{
    private ResponseNormalizer $normalizer;

    public function __construct(
        private InferenceProvider $inferenceProvider,
    ) {
        $this->normalizer = new ResponseNormalizer();
    }

    #[\Override]
    public function hasNext(StructuredOutputExecution $execution): bool {
        $state = $execution->attemptState();

        // Not started yet - can make request
        if ($state === null) {
            return true;
        }

        // Already made request - no more updates (sync = single chunk)
        return !$state->isStreamExhausted();
    }

    #[\Override]
    public function nextChunk(StructuredOutputExecution $execution): StructuredOutputExecution {
        $state = $execution->attemptState();

        // Should not be called if already exhausted within the same attempt
        if ($state !== null && $state->isStreamExhausted()) {
            return $execution;
        }

        // Make single synchronous inference request
        $inference = $this->inferenceProvider->getInference($execution)->response();

        // Normalize content based on output mode (extract JSON, handle tool calls, etc.)
        $inference = $this->normalizer->normalizeContent($inference, $execution->outputMode());

        // Create streaming state marked as exhausted (single chunk pattern)
        $attemptState = StructuredOutputAttemptState::empty()
            ->withPhase(AttemptPhase::Done)
            ->withNextChunk(
                $inference,
                PartialInferenceResponseList::empty(),
                true,
            );

        // Update execution with inference and mark stream exhausted
        return $execution
            ->withAttemptState($attemptState)
            ->withCurrentAttempt(
                inferenceResponse: $inference,
                partialInferenceResponses: PartialInferenceResponseList::empty(),
                errors: $execution->currentErrors(),
            );
    }
}
