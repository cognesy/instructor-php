<?php declare(strict_types=1);

namespace Cognesy\Instructor\ResponseIterators\ModularPipeline\Domain;

use Cognesy\Instructor\Contracts\Sequenceable;

/**
 * Tracks Sequenceable deltas without patch semantics.
 *
 * Maintains the current sequence and tracks which items have been emitted.
 * Replaces SequenceState's complex confirmUpdates() logic with explicit advance().
 *
 * Design: Pure state tracking, no side effects.
 * Event emission handled by EventTap, not here.
 */
final readonly class SequenceTracker
{
    private function __construct(
        private int $previousLength,
        private ?Sequenceable $current,
    ) {}

    public static function empty(): self {
        return new self(
            previousLength: 0,
            current: null,
        );
    }

    /**
     * Update with new sequence, returns new tracker instance.
     */
    public function update(Sequenceable $sequence): self {
        return new self(
            previousLength: $this->previousLength,
            current: $sequence,
        );
    }

    /**
     * Get pending updates (items not yet confirmed as emitted).
     *
     * Returns sequence snapshots for each new item from previousLength
     * up to (but not including) the last item.
     * The last item is kept pending for potential updates.
     */
    public function pending(): SequenceUpdateList {
        if ($this->current === null) {
            return SequenceUpdateList::empty();
        }

        $currentLength = count($this->current);

        // Nothing new to emit
        if ($currentLength === 0 || $currentLength <= $this->previousLength + 1) {
            return SequenceUpdateList::empty();
        }

        // Emit items from previousLength to currentLength - 1
        // (keep last item for updates)
        $targetIndex = max(0, $currentLength - 2);
        $updates = [];

        for ($i = $this->previousLength; $i <= $targetIndex; $i++) {
            $updates[] = $this->sequenceUpToIndex($this->current, $i);
        }

        return SequenceUpdateList::of($updates);
    }

    /**
     * Advance the tracker (confirm current items as emitted).
     *
     * Replaces confirmUpdates() with explicit semantics.
     * Sets previousLength to current length - 1 (keeping last item pending).
     */
    public function advance(): self {
        if ($this->current === null) {
            return $this;
        }

        $confirmedLength = max(0, count($this->current) - 1);

        return new self(
            previousLength: $confirmedLength,
            current: $this->current,
        );
    }

    /**
     * Finalize sequence (emit final complete state).
     */
    public function finalize(): SequenceUpdateList {
        if ($this->current === null || count($this->current) === 0) {
            return SequenceUpdateList::empty();
        }

        return SequenceUpdateList::of([$this->current]);
    }

    // INTERNAL //////////////////////////////////////////////////////////////

    private function sequenceUpToIndex(Sequenceable $sequence, int $index): Sequenceable {
        $result = clone $sequence;

        // Pop items until we're at the target index
        while (count($result) > $index + 1) {
            $result->pop();
        }

        return $result;
    }
}
