<?php declare(strict_types=1);

namespace Cognesy\Instructor\Streaming\Pipeline;

use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Deserialization\Contracts\CanDeserializeResponse;
use Cognesy\Instructor\Streaming\StructuredOutputStreamState;
use Cognesy\Instructor\Transformation\Contracts\CanTransformResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;
use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Utils\Json\Json;
use Throwable;

final class AccumulatePartialResponsesReducer implements Reducer
{
    private string $lastSnapshot = '';
    private int $lastSnapshotRevision = -1;
    private StructuredOutputStreamState $state;

    public function __construct(
        private readonly Reducer $inner,
        private readonly OutputMode $mode,
        private readonly CanDeserializeResponse $deserializer,
        private readonly CanTransformResponse $transformer,
        private readonly ResponseModel $responseModel,
    ) {
        $this->state = StructuredOutputStreamState::empty();
    }

    #[\Override]
    public function init(): mixed {
        $this->lastSnapshot = '';
        $this->lastSnapshotRevision = -1;
        $this->state->reset();
        return $this->inner->init();
    }

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        $state = match (true) {
            $reducible instanceof PartialInferenceDelta => $this->accumulateDelta($reducible),
            default => $reducible,
        };

        assert($state instanceof StructuredOutputStreamState);

        return $this->inner->step($accumulator, $this->forwardState($state));
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        return $this->inner->complete($accumulator);
    }

    private function forwardState(StructuredOutputStreamState $state): StructuredOutputStreamState {
        if ($this->state->hasValue()) {
            return $state;
        }

        $snapshot = $this->snapshotContent();
        if ($snapshot === '' && $state->finishReason() === '') {
            return $state;
        }

        if ($state->snapshotRevision() === $this->lastSnapshotRevision) {
            return $state;
        }

        if ($snapshot === $this->lastSnapshot) {
            $this->lastSnapshotRevision = $state->snapshotRevision();
            return $state;
        }

        $parsed = $this->parseSnapshot($snapshot);
        if ($parsed === null) {
            return $state;
        }

        $object = $this->createObject($parsed);
        if ($object === null) {
            return $state;
        }

        $this->lastSnapshot = $snapshot;
        $this->lastSnapshotRevision = $state->snapshotRevision();
        $this->state->setValue($object);
        return $this->state;
    }

    private function snapshotContent(): string {
        return match ($this->mode) {
            OutputMode::Tools => $this->state->toolArgsSnapshot(),
            default => $this->state->content(),
        };
    }

    /**
     * @return array<array-key, mixed>|null
     */
    private function parseSnapshot(string $snapshot): ?array {
        if ($snapshot === '') {
            return null;
        }

        $hasBraces = str_contains($snapshot, '{') || str_contains($snapshot, '[');
        if (!$hasBraces) {
            return null;
        }

        try {
            return Json::fromPartial($snapshot)->toArray();
        } catch (Throwable) {
            return null;
        }
    }

    private function createObject(array $data): mixed {
        try {
            $deserialized = $this->deserializer->deserialize($data, $this->responseModel);
            if ($deserialized->isFailure()) {
                return null;
            }

            $transformed = $this->transformer->transform($deserialized->unwrap(), $this->responseModel);
            if ($transformed->isFailure()) {
                return null;
            }

            return $transformed->unwrap();
        } catch (Throwable) {
            return null;
        }
    }

    private function accumulateDelta(PartialInferenceDelta $delta): StructuredOutputStreamState
    {
        $this->state->applyDelta($delta);
        $this->state->setValue($delta->value);
        return $this->state;
    }
}
