<?php declare(strict_types=1);

namespace Cognesy\Instructor\ResponseIterators\ModularPipeline\Pipeline;

use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeResponse;
use Cognesy\Instructor\ResponseIterators\ModularPipeline\Domain\ContentHash;
use Cognesy\Instructor\ResponseIterators\ModularPipeline\Domain\DeduplicationState;
use Cognesy\Instructor\ResponseIterators\ModularPipeline\Domain\PartialFrame;
use Cognesy\Instructor\ResponseIterators\ModularPipeline\Enums\EmissionType;
use Cognesy\Instructor\Transformation\Contracts\CanTransformResponse;
use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Utils\Result\Result;
use Throwable;

/**
 * Deserializes, transforms, and deduplicates partial objects.
 *
 * Always uses buffer.parsed() as source - buffer is single source of truth.
 * Uses DeduplicationState to track hash of last emitted object.
 * Only emits when object content changes.
 *
 * Sets Emission to ObjectReady when object should be emitted.
 */
final class DeserializeAndDeduplicateReducer implements Reducer
{
    private DeduplicationState $state;

    public function __construct(
        private readonly Reducer $inner,
        private readonly CanDeserializeResponse $deserializer,
        private readonly CanTransformResponse $transformer,
        private readonly ResponseModel $responseModel,
    ) {
        $this->state = DeduplicationState::empty();
    }

    #[\Override]
    public function init(): mixed {
        $this->state = DeduplicationState::empty();
        return $this->inner->init();
    }

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        assert($reducible instanceof PartialFrame);

        // If driver already provided a value, emit it directly
        if ($reducible->source->hasValue()) {
            $frame = $reducible
                ->withObject(Result::success($reducible->source->value()))
                ->withEmission(EmissionType::DriverValue);
            return $this->inner->step($accumulator, $frame);
        }

        // If no content available, forward unchanged
        if (!$reducible->hasContent()) {
            return $this->inner->step($accumulator, $reducible);
        }

        // Use buffer's parsed content as single source of truth
        $parsed = $reducible->buffer->parsed();

        if ($parsed->isFailure()) {
             // If parsing fails (invalid/incomplete JSON), we forward failure.
             $frame = $reducible->withObject($parsed);
             return $this->inner->step($accumulator, $frame);
        }

        $result = $this->createObject($parsed->unwrap());

        // Handle errors - forward with error in Result, no dedup update
        if ($result->isFailure()) {
            $frame = $reducible->withObject($result);
            return $this->inner->step($accumulator, $frame);
        }

        $object = $result->unwrap();

        // Use object content as dedup key (hash of object)
        $dedupKey = $object;

        // Check if object should be emitted (hash changed)
        if (!$this->state->shouldEmit($dedupKey)) {
            // Same object, don't emit - update hash but keep emission as None
            $this->state = $this->state->withHash(ContentHash::of($dedupKey));
            $frame = $reducible->withObject($result);
            return $this->inner->step($accumulator, $frame);
        }

        // Object changed - update hash and mark for emission
        $this->state = $this->state->withHash(ContentHash::of($dedupKey));
        $frame = $reducible
            ->withObject($result)
            ->withEmission(EmissionType::ObjectReady);

        return $this->inner->step($accumulator, $frame);
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        return $this->inner->complete($accumulator);
    }

    // INTERNAL //////////////////////////////////////////////////////////////

    /**
     * @param array<string, mixed> $data
     */
    private function createObject(array $data): Result {
        try {
            // Deserialize
            $deserialized = $this->deserializer->deserialize($data, $this->responseModel);
            if ($deserialized->isFailure()) {
                return $deserialized;
            }

            // Transform
            $transformed = $this->transformer->transform($deserialized->unwrap(), $this->responseModel);
            if ($transformed->isFailure()) {
                return $transformed;
            }

            return Result::success($transformed->unwrap());

        } catch (Throwable $e) {
            return Result::failure($e->getMessage());
        }
    }
}
