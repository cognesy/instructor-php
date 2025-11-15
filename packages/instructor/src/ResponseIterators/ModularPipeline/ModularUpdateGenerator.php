<?php declare(strict_types=1);

namespace Cognesy\Instructor\ResponseIterators\ModularPipeline;

use Cognesy\Instructor\Contracts\CanStreamStructuredOutputUpdates;
use Cognesy\Instructor\Core\InferenceProvider;
use Cognesy\Instructor\Data\StructuredOutputAttemptState;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Enums\AttemptPhase;
use Cognesy\Instructor\ResponseIterators\ModularPipeline\Aggregation\StreamAggregate;

/**
 * Iterates over chunks from a single attempt using the modular pipeline.
 *
 * Implements CanStreamStructuredOutputUpdates contract for use with ExecutorFactory.
 *
 * Scope: Single attempt only (does NOT handle validation or retries)
 * State: Stored in execution->attemptState() (ephemeral, non-serializable)
 * Composable: Designed to be wrapped by AttemptIterator for retry logic
 *
 * Architecture: Uses modular pipeline with:
 * - Domain: PartialFrame, ContentBuffer, Emission, etc.
 * - Pipeline: ExtractDelta → Deserialize → UpdateSequence → Enrich
 * - Aggregation: StreamAggregate (replaces AggregationState)
 * - Events: EventTap (single dispatch point)
 */
final readonly class ModularUpdateGenerator implements CanStreamStructuredOutputUpdates
{
    public function __construct(
        private InferenceProvider $inferenceProvider,
        private ModularStreamFactory $factory,
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

    // INTERNAL //////////////////////////////////////////////////////////////////

    /**
     * Initialize a new streaming session.
     * Creates fresh inference stream and wraps it in Clean pipeline.
     */
    private function initializeStream(StructuredOutputExecution $execution): StructuredOutputExecution {
        $responseModel = $execution->responseModel();
        assert($responseModel !== null, 'Response model cannot be null');

        // Start fresh inference stream (non-deterministic, cannot be replayed)
        $inferenceStream = $this->inferenceProvider
            ->getInference($execution)
            ->stream()
            ->responses();

        // Wrap in Clean pipeline
        $aggregateStream = $this->factory->makeStream(
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

        // Get current chunk (StreamAggregate from Clean pipeline)
        /** @var StreamAggregate $aggregate */
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
            $aggregate->partial(),
            $isExhausted,
        );

        // Update execution with current attempt data
        return $execution
            ->withAttemptState($newState)
            ->withCurrentAttempt(
                inferenceResponse: $inference,
                partialInferenceResponse: $aggregate->partial(),
                errors: $execution->currentErrors(), // Preserve existing errors
            );
    }
}
