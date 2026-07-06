<?php declare(strict_types=1);

namespace Cognesy\Instructor\Streaming\Pipeline;

use Cognesy\Instructor\Core\ObjectHydrator;
use Cognesy\Instructor\Data\ResponseModel;
use Cognesy\Instructor\Streaming\StructuredOutputStreamState;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;
use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Utils\Json\IncrementalJsonParser;
use Throwable;

final class AccumulatePartialResponsesReducer implements Reducer
{
    private const MIN_GROWTH_BYTES = 8;
    private const GROWTH_DIVISOR = 32;

    private int $lastSnapshotRevision = -1;
    private StructuredOutputStreamState $state;
    private bool $hasProducedValue = false;
    private ?Throwable $lastCreationError = null;
    private IncrementalJsonParser $jsonParser;
    private string $activeToolKey = '';
    private int $deltaSinceLastMaterialization = 0;
    private int $lastMaterializedLength = 0;

    public function __construct(
        private readonly Reducer $inner,
        private readonly OutputMode $mode,
        private readonly ObjectHydrator $hydrator,
        private readonly ResponseModel $responseModel,
        private readonly int $materializationInterval = 1,
    ) {
        $this->state = StructuredOutputStreamState::empty();
        $this->jsonParser = new IncrementalJsonParser();
    }

    #[\Override]
    public function init(): mixed {
        $this->lastSnapshotRevision = -1;
        $this->state->reset();
        $this->hasProducedValue = false;
        $this->lastCreationError = null;
        $this->jsonParser->reset();
        $this->activeToolKey = '';
        $this->deltaSinceLastMaterialization = 0;
        $this->lastMaterializedLength = 0;
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
        if (!$this->hasProducedValue && $this->lastCreationError !== null) {
            trigger_error(
                'Streaming object creation never succeeded. Last error: '
                . $this->lastCreationError->getMessage()
                . ' in ' . $this->lastCreationError->getFile()
                . ':' . $this->lastCreationError->getLine(),
                E_USER_WARNING,
            );
        }
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

        $this->deltaSinceLastMaterialization++;
        if ($this->shouldSkipMaterialization($state, strlen($snapshot))) {
            return $state;
        }
        $this->deltaSinceLastMaterialization = 0;
        $this->lastMaterializedLength = strlen($snapshot);

        $parsed = $this->parseCurrentState($snapshot);
        if ($parsed === null) {
            return $state;
        }

        $object = $this->createObject($parsed);
        if ($object === null) {
            return $state;
        }

        $this->lastSnapshotRevision = $state->snapshotRevision();
        $this->state->setValue($object);
        return $this->state;
    }

    /**
     * Skip materialization when the throttle has not elapsed yet,
     * unless this is the first value (time-to-first-response priority)
     * or the stream is finishing.
     *
     * With the default interval (1) an adaptive byte-growth gate applies:
     * the buffer must grow by max(MIN_GROWTH_BYTES, length/GROWTH_DIVISOR)
     * since the last materialization. This keeps early updates frequent while
     * bounding total parse+deserialize work to O(n) over the whole stream
     * (per-delta materialization is O(n²) on long outputs).
     * An explicit interval > 1 uses pure delta-count throttling.
     */
    private function shouldSkipMaterialization(StructuredOutputStreamState $state, int $snapshotLength): bool {
        if (!$this->hasProducedValue) {
            return false;
        }
        if ($state->finishReason() !== '') {
            return false;
        }
        if ($this->materializationInterval > 1) {
            return $this->deltaSinceLastMaterialization < $this->materializationInterval;
        }

        // Snapshot shrunk — new document started (e.g. next tool call): materialize now.
        if ($snapshotLength < $this->lastMaterializedLength) {
            return false;
        }

        $requiredGrowth = max(self::MIN_GROWTH_BYTES, intdiv($snapshotLength, self::GROWTH_DIVISOR));
        return ($snapshotLength - $this->lastMaterializedLength) < $requiredGrowth;
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
    private function parseCurrentState(string $snapshot): ?array {
        if ($snapshot === '') {
            return null;
        }

        if ($this->jsonParser->buffer() === '' && $snapshot !== '') {
            $this->jsonParser->append($snapshot);
        }

        return $this->jsonParser->currentArray();
    }

    private function createObject(array $data): mixed {
        $hydrated = $this->hydrator->hydratePartial($data, $this->responseModel);
        if ($hydrated->isFailure()) {
            $error = $hydrated->error();
            if ($error instanceof Throwable) {
                $this->lastCreationError = $error;
            }
            return null;
        }

        $this->hasProducedValue = true;
        return $hydrated->unwrap();
    }

    /**
     * Returns the last unexpected error from object creation, if any.
     * Useful for diagnosing silent streaming failures where JSON parsed
     * successfully but deserialization/transformation threw an exception.
     */
    public function lastCreationError(): ?Throwable {
        return $this->lastCreationError;
    }

    public function hasProducedValue(): bool {
        return $this->hasProducedValue;
    }

    private function accumulateDelta(PartialInferenceDelta $delta): StructuredOutputStreamState
    {
        $previousToolKey = $this->state->toolKey();
        $this->state->applyDelta($delta);
        $this->appendDeltaToParser($delta, $previousToolKey);

        if ($delta->value !== null) {
            $this->state->setValue($delta->value);
            return $this->state;
        }

        if ($delta->contentDelta !== '' || $delta->toolArgs !== '') {
            $this->state->clearValue();
        }

        return $this->state;
    }

    private function appendDeltaToParser(PartialInferenceDelta $delta, string $previousToolKey): void
    {
        match ($this->mode) {
            OutputMode::Tools => $this->appendToolArgsDelta($delta, $previousToolKey),
            default => $this->appendContentDelta($delta),
        };
    }

    private function appendContentDelta(PartialInferenceDelta $delta): void
    {
        if ($delta->contentDelta === '') {
            return;
        }

        $this->jsonParser->append($delta->contentDelta);
    }

    private function appendToolArgsDelta(PartialInferenceDelta $delta, string $previousToolKey): void
    {
        $currentToolKey = $this->state->toolKey();

        if ($currentToolKey !== '' && $currentToolKey !== $previousToolKey) {
            $this->jsonParser->reset();
            $this->activeToolKey = $currentToolKey;
        }

        if ($delta->toolArgs === '') {
            return;
        }

        if ($currentToolKey !== '' && $this->activeToolKey !== $currentToolKey) {
            $this->jsonParser->reset();
            $this->activeToolKey = $currentToolKey;
        }

        $this->jsonParser->append($delta->toolArgs);
    }
}
