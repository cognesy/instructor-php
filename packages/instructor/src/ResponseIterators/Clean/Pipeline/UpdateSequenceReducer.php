<?php declare(strict_types=1);

namespace Cognesy\Instructor\ResponseIterators\Clean\Pipeline;

use Cognesy\Instructor\Contracts\Sequenceable;
use Cognesy\Instructor\ResponseIterators\Clean\Domain\PartialFrame;
use Cognesy\Instructor\ResponseIterators\Clean\Domain\SequenceTracker;
use Cognesy\Stream\Contracts\Reducer;

/**
 * Tracks sequence updates without emitting events.
 *
 * Updates SequenceTracker state as Sequenceable objects flow through.
 * Event emission is handled by EventTap, not here.
 *
 * This reducer is pure state tracking.
 */
final class UpdateSequenceReducer implements Reducer
{
    private SequenceTracker $tracker;

    public function __construct(
        private readonly Reducer $inner,
    ) {
        $this->tracker = SequenceTracker::empty();
    }

    #[\Override]
    public function init(): mixed {
        $this->tracker = SequenceTracker::empty();
        return $this->inner->init();
    }

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        assert($reducible instanceof PartialFrame);

        // Only process if object is Sequenceable
        if (!$reducible->hasObject()) {
            return $this->inner->step($accumulator, $reducible);
        }

        $object = $reducible->object->unwrap();
        if (!($object instanceof Sequenceable)) {
            return $this->inner->step($accumulator, $reducible);
        }

        // Update tracker state
        $this->tracker = $this->tracker->update($object);

        // Note: EventTap will handle emitting SequenceUpdated events
        // This reducer is pure state tracking

        return $this->inner->step($accumulator, $reducible);
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        return $this->inner->complete($accumulator);
    }

    /**
     * Get current tracker state (used by EventTap).
     */
    public function getTracker(): SequenceTracker {
        return $this->tracker;
    }

    /**
     * Advance tracker (confirm pending updates as emitted).
     */
    public function advanceTracker(): void {
        $this->tracker = $this->tracker->advance();
    }
}
