<?php declare(strict_types=1);

namespace Cognesy\Instructor\Executors\Partials;

use Cognesy\Instructor\Contracts\CanStreamStructuredOutputUpdates;
use Cognesy\Instructor\Core\InferenceProvider;
use Cognesy\Instructor\Data\StructuredOutputAttemptState;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Enums\AttemptPhase;
use Cognesy\Instructor\Executors\Partials\ResponseAggregation\AggregationState;

/**
 * Iterates over chunks from a single attempt using the Partials pipeline.
 *
 * Scope: Single attempt only (does NOT handle validation or retries)
 * State: Stored in execution->attemptState() (ephemeral, non-serializable)
 * Composable: Designed to be wrapped by AttemptIterator for retry logic
 *
 * Responsibility:
 * - Initialize stream on first call
 * - Process chunks one at a time
 * - Update execution with partial responses
 * - Signal when stream is exhausted
 */
final readonly class PartialStreamingUpdateGenerator implements CanStreamStructuredOutputUpdates
{
    public function __construct(
        private InferenceProvider $inferenceProvider,
        private PartialStreamFactory $partials,
    ) {}

    #[\Override]
    public function hasNext(StructuredOutputExecution $execution): bool {
        $state = $execution->attemptState();

        // Not started yet - can initialize
        if ($state === null) {
            return true;
        }

        // Has more chunks to process
        return $state->hasMoreChunks();
    }

    #[\Override]
    public function nextChunk(StructuredOutputExecution $execution): StructuredOutputExecution {
        $state = $execution->attemptState();

        // Initialize stream on first call or if stream not initialized
        if ($state === null || !$state->isStreamInitialized()) {
            return $this->initializeStream($execution);
        }

        // Process next chunk
        return $this->processNextChunk($execution, $state);
    }

    /**
     * Initialize a new streaming session.
     * Creates fresh inference stream and wraps it in Partials pipeline.
     */
    private function initializeStream(StructuredOutputExecution $execution): StructuredOutputExecution {
        $responseModel = $execution->responseModel();
        assert($responseModel !== null, 'Response model cannot be null');

        // Start fresh inference stream (non-deterministic, cannot be replayed)
        $inferenceStream = $this->inferenceProvider
            ->getInference($execution)
            ->stream()
            ->responses();

        // Wrap in Partials pipeline
        $aggregateStream = $this->partials->makeObservableStream(
            source: $inferenceStream,
            responseModel: $responseModel,
            mode: $execution->outputMode(),
            accumulatePartials: true,
        );

        // Create streaming state with initialized stream
        $attemptState = StructuredOutputAttemptState::empty()
            ->withPhase(AttemptPhase::Streaming)
            ->withStream($aggregateStream);

        return $execution->withAttemptState($attemptState);
    }

    /**
     * Process the next chunk from the active stream.
     * Updates execution with partial inference response.
     */
    private function processNextChunk(
        StructuredOutputExecution $execution,
        StructuredOutputAttemptState $state,
    ): StructuredOutputExecution {
        $stream = $state->stream();
        assert($stream !== null, 'Stream must be initialized');

        // Stream should be valid (checked by hasNext)
        if (!$stream->valid()) {
            // Stream exhausted - mark as such
            return $execution->withAttemptState($state->withExhausted());
        }

        // Get current chunk (AggregationState from Partials pipeline)
        /** @var AggregationState $aggregate */
        $aggregate = $stream->current();
        $stream->next();

        // Check if stream is exhausted AFTER advancing (on same iterator instance)
        // IMPORTANT: Cannot call getIterator() again - generators can't be rewound!
        $isExhausted = !$stream->valid();

        // Build inference response from aggregate
        $inference = $aggregate->toInferenceResponse();

        // Update streaming state with processed chunk
        $newState = $state->withNextChunk(
            $inference,
            $aggregate->partials(),
            $isExhausted
        );

        // Update execution with current attempt data
        return $execution
            ->withAttemptState($newState)
            ->withCurrentAttempt(
                inferenceResponse: $inference,
                partialInferenceResponses: $aggregate->partials(),
                errors: $execution->currentErrors(), // Preserve existing errors
            );
    }
}
