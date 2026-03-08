<?php declare(strict_types=1);

namespace Cognesy\Instructor\Streaming\Sequence;

use Cognesy\Instructor\Contracts\Sequenceable;
use Cognesy\Utils\Profiler\TracksObjectCreation;

/**
 * Tracks Sequenceable deltas without patch semantics.
 *
 * Maintains the current sequence and tracks which items have been emitted.
 * Replaces SequenceState's complex confirmUpdates() logic with explicit advance().
 *
 * Design: Pure state tracking, no side effects.
 * Event emission is handled by the streaming event-dispatch reducer, not here.
 */
final readonly class SequenceTracker
{
    use TracksObjectCreation;

    private function __construct(
        private int $previousLength,
        private ?Sequenceable $current,
    ) {
        $this->trackObjectCreation();
    }

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
     * Consume sequence update and return pending updates with advanced tracker.
     */
    public function consume(Sequenceable $sequence): SequenceTrackingResult {
        $confirmedLength = max(0, count($sequence) - 1);

        return new SequenceTrackingResult(
            tracker: new self(
                previousLength: $confirmedLength,
                current: $sequence,
            ),
            updates: $this->pendingFor($sequence),
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
        return SequenceUpdateList::of($this->buildSnapshots($targetIndex));
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

    /**
     * @return Sequenceable[]
     */
    private function buildSnapshots(int $targetIndex): array {
        if ($this->current === null) {
            return [];
        }

        $working = clone $this->current;
        $working->pop(); // keep the newest item pending for potential updates

        $reverseOrderSnapshots = [];
        for ($i = $targetIndex; $i >= $this->previousLength; $i--) {
            $reverseOrderSnapshots[] = clone $working;
            if ($i > $this->previousLength) {
                $working->pop();
            }
        }

        return array_reverse($reverseOrderSnapshots);
    }

    private function pendingFor(Sequenceable $sequence): SequenceUpdateList {
        $currentLength = count($sequence);

        if ($currentLength === 0 || $currentLength <= $this->previousLength + 1) {
            return SequenceUpdateList::empty();
        }

        $targetIndex = max(0, $currentLength - 2);
        return SequenceUpdateList::of($this->buildSnapshotsFor($sequence, $targetIndex));
    }

    /**
     * @return Sequenceable[]
     */
    private function buildSnapshotsFor(Sequenceable $sequence, int $targetIndex): array {
        $working = clone $sequence;
        $working->pop();

        $reverseOrderSnapshots = [];
        for ($i = $targetIndex; $i >= $this->previousLength; $i--) {
            $reverseOrderSnapshots[] = clone $working;
            if ($i > $this->previousLength) {
                $working->pop();
            }
        }

        return array_reverse($reverseOrderSnapshots);
    }
}
