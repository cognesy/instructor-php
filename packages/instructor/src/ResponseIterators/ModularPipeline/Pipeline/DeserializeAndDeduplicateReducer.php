<?php declare(strict_types=1);

namespace Cognesy\Instructor\ResponseIterators\ModularPipeline\Pipeline;

use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeResponse;
use Cognesy\Instructor\ResponseIterators\ModularPipeline\Domain\PartialFrame;
use Cognesy\Instructor\ResponseIterators\ModularPipeline\Enums\EmissionType;
use Cognesy\Instructor\Transformation\Contracts\CanTransformResponse;
use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Utils\Json\Json;
use Cognesy\Utils\Result\Result;
use Throwable;

/**
 * Deserializes, transforms, and deduplicates partial objects.
 *
 * Always uses buffer.parsed() as source - buffer is single source of truth.
 * Tracks hash of last emitted object locally.
 * Only emits when object content changes.
 *
 * Sets Emission to ObjectReady when object should be emitted.
 */
final class DeserializeAndDeduplicateReducer implements Reducer
{
    private string $lastObjectHash = '';

    public function __construct(
        private readonly Reducer $inner,
        private readonly CanDeserializeResponse $deserializer,
        private readonly CanTransformResponse $transformer,
        private readonly ResponseModel $responseModel,
    ) {}

    #[\Override]
    public function init(): mixed {
        $this->lastObjectHash = '';
        return $this->inner->init();
    }

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        assert($reducible instanceof PartialFrame);

        // If driver already provided a value, emit it directly
        if ($reducible->source->hasValue()) {
            return $this->forward(
                $accumulator,
                $reducible
                    ->withObject(Result::success($reducible->source->value()))
                    ->withEmission(EmissionType::DriverValue),
            );
        }

        // If no content available, forward unchanged
        if (!$reducible->hasContent()) {
            return $this->forward($accumulator, $reducible);
        }

        // Use buffer's parsed content as single source of truth
        $parsed = $reducible->buffer->parsed();
        if ($parsed === null) {
            return $this->forward($accumulator, $reducible);
        }

        $result = $this->createObject($parsed);

        // Handle errors - forward with error in Result, no dedup update
        if ($result->isFailure()) {
            return $this->forward($accumulator, $reducible->withObject($result));
        }

        $objectHash = $this->hashObject($result->unwrap());
        $shouldEmit = $objectHash !== $this->lastObjectHash;
        $this->lastObjectHash = $objectHash;

        $frame = $reducible->withObject($result);
        if ($shouldEmit) {
            $frame = $frame->withEmission(EmissionType::ObjectReady);
        }

        return $this->forward($accumulator, $frame);
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

    private function hashObject(mixed $object): string {
        return hash('xxh3', Json::encode($object));
    }

    private function forward(mixed $accumulator, PartialFrame $frame): mixed {
        return $this->inner->step($accumulator, $frame);
    }
}
