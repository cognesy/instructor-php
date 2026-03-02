<?php declare(strict_types=1);

namespace Cognesy\Instructor\ResponseIterators\ModularPipeline;

use Closure;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Instructor\Contracts\CanStreamStructuredOutputUpdates;
use Cognesy\Instructor\Core\InferenceProvider;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Data\StructuredOutputAttemptState;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeResponse;
use Cognesy\Instructor\Enums\AttemptPhase;
use Cognesy\Instructor\ResponseIterators\ModularPipeline\Aggregation\AggregateStream;
use Cognesy\Instructor\ResponseIterators\ModularPipeline\Aggregation\StreamAggregate;
use Cognesy\Instructor\ResponseIterators\ModularPipeline\Events\EventTapTransducer;
use Cognesy\Instructor\ResponseIterators\ModularPipeline\Pipeline\DeserializeAndDeduplicate;
use Cognesy\Instructor\ResponseIterators\ModularPipeline\Pipeline\EnrichResponse;
use Cognesy\Instructor\ResponseIterators\ModularPipeline\Pipeline\ExtractDelta;
use Cognesy\Instructor\Transformation\Contracts\CanTransformResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Stream\Transformation;
use Cognesy\Stream\TransformationStream;
use IteratorAggregate;

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
 * - Pipeline: ExtractDelta → Deserialize → EventTap → Enrich
 * - Aggregation: StreamAggregate (replaces AggregationState)
 * - Events: EventTap (single dispatch point)
 */
final readonly class ModularUpdateGenerator implements CanStreamStructuredOutputUpdates
{
    public function __construct(
        private InferenceProvider $inferenceProvider,
        private CanDeserializeResponse $deserializer,
        private CanTransformResponse $transformer,
        private CanHandleEvents $events,
        private ?Closure $bufferFactory = null,
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
        $aggregateStream = $this->makeStream(
            source: $inferenceStream,
            responseModel: $responseModel,
            mode: $execution->outputMode(),
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

    /**
     * Create observable stream that processes partials and emits StreamAggregate.
     *
     * @param iterable<PartialInferenceResponse> $source
     * @return IteratorAggregate<int, StreamAggregate>
     */
    private function makeStream(
        iterable $source,
        ResponseModel $responseModel,
        OutputMode $mode,
    ): IteratorAggregate {
        // Build pipeline stages (transducers)
        $stages = [
            // 1. Entry: Build PartialFrame from cumulative snapshot content
            new ExtractDelta($mode, $this->bufferFactory),

            // 2. Objects: Deserialize, transform, deduplicate object emissions
            new DeserializeAndDeduplicate(
                deserializer: $this->deserializer,
                transformer: $this->transformer,
                responseModel: $responseModel,
            ),

            // 3. Events: Dispatch frame-level domain events
            new EventTapTransducer(
                events: $this->events,
                expectedToolName: $mode === OutputMode::Tools ? $responseModel->toolName() : '',
            ),

            // 4. Enrich: Convert frame to mode-specific partial response shape
            new EnrichResponse($mode),

            // 5. Terminal: Aggregate into StreamAggregate
            new AggregateStream(),
        ];

        // Build transformation
        $transformation = Transformation::define(...$stages);

        // Wrap in Stream for observation
        return TransformationStream::from($source)->using($transformation);
    }
}
