<?php declare(strict_types=1);

namespace Cognesy\Instructor\ResponseIterators\Sync;

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
        return $state->hasMoreChunks();
    }

    #[\Override]
    public function nextChunk(StructuredOutputExecution $execution): StructuredOutputExecution {
        $state = $execution->attemptState();
        if ($state !== null && !$state->hasMoreChunks()) {
            return $execution;
        }
        $inference = $this->inferenceProvider->getInference($execution)->response();
        $inference = $this->normalizer->normalizeContent($inference, $execution->outputMode());
        $attemptState = StructuredOutputAttemptState::fromSingleChunk(
            $inference,
            PartialInferenceResponseList::empty(),
            AttemptPhase::Done,
        );
        return $execution
            ->withAttemptState($attemptState)
            ->withCurrentAttempt(
                inferenceResponse: $inference,
                partialInferenceResponses: PartialInferenceResponseList::empty(),
                errors: $execution->currentErrors(),
            );
    }
}
