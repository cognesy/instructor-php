<?php declare(strict_types=1);

namespace Cognesy\Instructor\ResponseIterators\ModularPipeline\Pipeline;

use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeResponse;
use Cognesy\Instructor\ResponseIterators\ModularPipeline\Domain\PartialFrame;
use Cognesy\Instructor\ResponseIterators\ModularPipeline\Enums\EmissionType;
use Cognesy\Instructor\Transformation\Contracts\CanTransformResponse;
use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Utils\Result\Result;
use ReflectionObject;
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
        $seen = [];
        $snapshot = $this->snapshotValue($object, $seen);
        return hash('xxh3', serialize($snapshot));
    }

    private function forward(mixed $accumulator, PartialFrame $frame): mixed {
        return $this->inner->step($accumulator, $frame);
    }

    /**
     * @param array<int, true> $seen
     */
    private function snapshotValue(mixed $value, array &$seen): mixed {
        if (is_null($value) || is_scalar($value)) {
            return $value;
        }

        if (is_array($value)) {
            return $this->snapshotArray($value, $seen);
        }

        if ($value instanceof \BackedEnum) {
            return ['class' => $value::class, 'value' => $value->value];
        }

        if ($value instanceof \UnitEnum) {
            return ['class' => $value::class, 'name' => $value->name];
        }

        if ($value instanceof \DateTimeInterface) {
            return ['class' => $value::class, 'value' => $value->format(DATE_ATOM)];
        }

        if ($value instanceof \JsonSerializable) {
            return [
                'class' => $value::class,
                'json' => $this->snapshotValue($value->jsonSerialize(), $seen),
            ];
        }

        if (is_object($value)) {
            return $this->snapshotObject($value, $seen);
        }

        return get_debug_type($value);
    }

    /**
     * @param array<int|string, mixed> $value
     * @param array<int, true> $seen
     * @return array<int|string, mixed>
     */
    private function snapshotArray(array $value, array &$seen): array {
        if (array_is_list($value)) {
            return array_map(
                fn(mixed $item): mixed => $this->snapshotValue($item, $seen),
                $value,
            );
        }

        ksort($value);
        $result = [];
        foreach ($value as $key => $item) {
            $result[(string) $key] = $this->snapshotValue($item, $seen);
        }
        return $result;
    }

    /**
     * @param array<int, true> $seen
     * @return array<string, mixed>
     */
    private function snapshotObject(object $value, array &$seen): array {
        $objectId = spl_object_id($value);
        if (isset($seen[$objectId])) {
            return ['class' => $value::class, 'recursion' => $objectId];
        }
        $seen[$objectId] = true;

        $reflection = new ReflectionObject($value);
        $properties = [];
        foreach ($reflection->getProperties() as $property) {
            if ($property->isStatic()) {
                continue;
            }

            $key = $property->getDeclaringClass()->getName() . '::' . $property->getName();
            $properties[$key] = $property->isInitialized($value)
                ? $this->snapshotValue($property->getValue($value), $seen)
                : '__uninitialized__';
        }

        foreach (get_object_vars($value) as $key => $item) {
            $properties['dynamic::' . $key] = $this->snapshotValue($item, $seen);
        }

        ksort($properties);

        return [
            'class' => $value::class,
            'properties' => $properties,
        ];
    }
}
