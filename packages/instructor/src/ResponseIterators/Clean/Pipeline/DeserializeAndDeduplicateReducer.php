<?php declare(strict_types=1);

namespace Cognesy\Instructor\ResponseIterators\Clean\Pipeline;

use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeResponse;
use Cognesy\Instructor\ResponseIterators\Clean\Domain\ContentHash;
use Cognesy\Instructor\ResponseIterators\Clean\Domain\DeduplicationState;
use Cognesy\Instructor\ResponseIterators\Clean\Domain\PartialFrame;
use Cognesy\Instructor\ResponseIterators\Clean\Enums\Emission;
use Cognesy\Instructor\Transformation\Contracts\CanTransformResponse;
use Cognesy\Instructor\Validation\Contracts\CanValidatePartialResponse;
use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Utils\Result\Result;
use Throwable;

/**
 * Deserializes, validates, transforms, and deduplicates partial objects.
 *
 * Always uses buffer.normalized() as source - buffer is single source of truth.
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
        private readonly CanValidatePartialResponse $validator,
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
                ->withEmission(Emission::DriverValue);
            return $this->inner->step($accumulator, $frame);
        }

        // If no content available, forward unchanged
        if (!$reducible->hasContent()) {
            return $this->inner->step($accumulator, $reducible);
        }

        // Use buffer's normalized content as single source of truth
        // Buffer has accumulated all deltas across chunks
        $normalizedText = $reducible->buffer->normalized();
        $result = $this->createObject($normalizedText);

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
            ->withEmission(Emission::ObjectReady);

        return $this->inner->step($accumulator, $frame);
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        return $this->inner->complete($accumulator);
    }

    // INTERNAL //////////////////////////////////////////////////////////////

    private function createObject(string $normalized): Result {
        try {
            // Validate
            $validationResult = $this->validator->validatePartialResponse(
                $normalized,
                $this->responseModel,
            );

            if ($validationResult->isFailure()) {
                return $validationResult;
            }

            // Deserialize
            $deserialized = $this->deserializer->deserialize($normalized, $this->responseModel);
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
