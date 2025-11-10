<?php declare(strict_types=1);

namespace Cognesy\Instructor\ResponseIterators\Clean;

use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeResponse;
use Cognesy\Instructor\ResponseIterators\Clean\Aggregation\AggregateStream;
use Cognesy\Instructor\ResponseIterators\Clean\Aggregation\StreamAggregate;
use Cognesy\Instructor\ResponseIterators\Clean\Events\EventTapTransducer;
use Cognesy\Instructor\ResponseIterators\Clean\Pipeline\DeserializeAndDeduplicate;
use Cognesy\Instructor\ResponseIterators\Clean\Pipeline\EnrichResponse;
use Cognesy\Instructor\ResponseIterators\Clean\Pipeline\ExtractDelta;
use Cognesy\Instructor\ResponseIterators\Clean\Pipeline\UpdateSequence;
use Cognesy\Instructor\Transformation\Contracts\CanTransformResponse;
use Cognesy\Instructor\Validation\Contracts\CanValidatePartialResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Stream\Transformation;
use Cognesy\Stream\TransformationStream;
use IteratorAggregate;

/**
 * Factory for building Clean streaming pipelines.
 *
 * Assembles the full transformation pipeline:
 * 1. ExtractDelta - Convert PartialInferenceResponse → PartialFrame with buffer
 * 2. DeserializeAndDeduplicate - Create objects with dedup
 * 3. UpdateSequence - Track sequence updates (if applicable)
 * 4. EventTap - Emit all domain events
 * 5. EnrichResponse - Convert PartialFrame → PartialInferenceResponse
 * 6. AggregateStream - Accumulate into StreamAggregate
 *
 * Design: Clean architecture with clear separation of concerns.
 */
final readonly class CleanStreamFactory
{
    public function __construct(
        private CanDeserializeResponse $deserializer,
        private CanValidatePartialResponse $validator,
        private CanTransformResponse $transformer,
        private CanHandleEvents $events,
    ) {}

    /**
     * Create observable stream that processes partials and emits StreamAggregate.
     *
     * @param iterable<PartialInferenceResponse> $source
     * @return IteratorAggregate<int, StreamAggregate>
     */
    public function makeObservableStream(
        iterable $source,
        ResponseModel $responseModel,
        OutputMode $mode,
        bool $accumulatePartials = true,
    ): IteratorAggregate {
        // Build pipeline stages (transducers)
        $stages = [
            // 1. Entry: Extract delta and create PartialFrame
            new ExtractDelta($mode),

            // 2. Objects: Deserialize, validate, transform, deduplicate
            new DeserializeAndDeduplicate(
                deserializer: $this->deserializer,
                validator: $this->validator,
                transformer: $this->transformer,
                responseModel: $responseModel,
            ),

            // 3. Sequences: Track sequence updates (pure state, events in EventTap)
            new UpdateSequence(),

            // 4. Events: Dispatch all domain events
            new EventTapTransducer(
                events: $this->events,
                expectedToolName: $mode === OutputMode::Tools ? $responseModel->toolName() : '',
            ),

            // 5. Enrich: Convert PartialFrame → PartialInferenceResponse
            new EnrichResponse($mode),

            // 6. Terminal: Aggregate into StreamAggregate
            new AggregateStream($accumulatePartials),
        ];

        // Build transformation
        $transformation = Transformation::define(...$stages);

        // Wrap in Stream for observation
        return TransformationStream::from($source)->using($transformation);
    }
}
