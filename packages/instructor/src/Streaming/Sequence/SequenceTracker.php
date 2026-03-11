<?php declare(strict_types=1);

namespace Cognesy\Instructor\Streaming\Sequence;

use Cognesy\Instructor\Contracts\Sequenceable;

/**
 * Tracks which items in a Sequenceable have been emitted to the caller.
 *
 * Yields individual completed items (not full Sequence clones).
 * The last item in the sequence is always held back as "in-progress"
 * until a newer item appears or finalize() is called.
 *
 * Design: Pure state tracking, no cloning, no side effects.
 * Memory: O(1) — only stores an integer counter.
 */
final readonly class SequenceTracker
{
    private function __construct(
        private int $emittedCount,
    ) {}

    public static function empty(): self {
        return new self(emittedCount: 0);
    }

    /**
     * Consume a sequence update: emit newly completed items and advance.
     *
     * Items at indices [emittedCount .. count-2] are considered complete
     * (the last item at count-1 may still be receiving updates).
     *
     * @return SequenceTrackingResult New tracker + list of individual completed items.
     */
    public function consume(Sequenceable $sequence): SequenceTrackingResult {
        $currentLength = count($sequence);
        $confirmedUpTo = max(0, $currentLength - 1); // last item held back

        $items = [];
        for ($i = $this->emittedCount; $i < $confirmedUpTo; $i++) {
            $items[] = $sequence->get($i);
        }

        return new SequenceTrackingResult(
            tracker: new self(emittedCount: max($this->emittedCount, $confirmedUpTo)),
            updates: $items,
        );
    }

    /**
     * Finalize: emit any remaining items (including the last held-back one).
     *
     * @return list<mixed> Remaining individual items.
     */
    public function finalize(Sequenceable $sequence): array {
        $currentLength = count($sequence);
        $items = [];
        for ($i = $this->emittedCount; $i < $currentLength; $i++) {
            $items[] = $sequence->get($i);
        }
        return $items;
    }
}
