<?php declare(strict_types=1);

namespace Cognesy\Stream\Support;

use Iterator;

/**
 * Manages shared state for splitting a single iterator into multiple independent branches.
 *
 * This class implements a buffering mechanism that allows multiple consumers to iterate
 * over the same source at different rates. The buffer automatically grows and shrinks
 * based on consumption patterns across all active branches.
 *
 * Key concepts:
 * - Branch: An independent consumer identified by an integer ID
 * - Cursor: Each branch's current position in the logical sequence
 * - Buffer: Holds values between the slowest and fastest branch
 * - Head: Logical index of the oldest buffered item
 * - Tail: Logical index after the newest buffered item
 */
final class TeeState
{
    private Iterator $source;

    /** @var array<int, mixed> Buffer of values indexed by logical position */
    private array $buffer = [];

    /** Logical index of the first buffered item */
    private int $head = 0;

    /** Logical index after the last buffered item */
    private int $tail = 0;

    /** @var array<int, int> Each branch's current logical position */
    private array $cursor = [];

    /** @var array<int, bool> Tracks which branches are still consuming */
    private array $active = [];

    /** Whether the source iterator has been initially positioned */
    private bool $sourceInitialized = false;

    /** Whether the source iterator is exhausted */
    private bool $sourceExhausted = false;

    public function __construct(Iterator $source, int $branches) {
        $this->source = $source;
        $this->initializeBranches($branches);
    }

    /**
     * Checks if a specific branch has more values available.
     */
    public function hasValue(int $id): bool {
        if ($this->isBranchInactive($id)) {
            return false;
        }

        if ($this->hasBufferedValue($id)) {
            return true;
        }

        if ($this->sourceExhausted) {
            return false;
        }

        // Need to check if source has values without fully initializing
        if (!$this->sourceInitialized) {
            return $this->source->valid();
        }

        return $this->source->valid();
    }

    /**
     * Retrieves the next value for a specific branch.
     * Must call hasValue() first to check availability.
     */
    public function nextValue(int $id): mixed {
        if ($this->hasBufferedValue($id)) {
            $value = $this->readFromBuffer($id);
        } else {
            $this->appendNextValueToBuffer();
            $this->cursor[$id] = $this->tail;
            $value = $this->buffer[$this->tail - 1];
        }

        $this->cleanupBuffer();
        return $value;
    }

    /**
     * Marks a branch as inactive, allowing the buffer to be cleaned up.
     * Called when a branch consumer stops iterating.
     */
    public function deactivate(int $id): void {
        $this->active[$id] = false;
        $this->cleanupBuffer();
    }

    // INTERNAL //////////////////////////////////////////////////////

    private function initializeBranches(int $branches): void {
        for ($i = 0; $i < $branches; $i++) {
            $this->cursor[$i] = $this->head;
            $this->active[$i] = true;
        }
    }

    private function isBranchInactive(int $id): bool {
        return !$this->active[$id];
    }

    private function hasBufferedValue(int $id): bool {
        return $this->cursor[$id] < $this->tail;
    }

    private function readFromBuffer(int $id): mixed {
        $position = $this->cursor[$id];
        $this->cursor[$id] = $position + 1;
        return $this->buffer[$position];
    }


    private function appendNextValueToBuffer(): bool {
        if ($this->sourceExhausted) {
            return false;
        }

        if (!$this->sourceInitialized) {
            return $this->initializeSource();
        }

        return $this->appendValueFromSource();
    }

    private function initializeSource(): bool {
        if (!$this->source->valid()) {
            $this->sourceExhausted = true;
            return false;
        }

        $this->sourceInitialized = true;
        $this->bufferValue($this->source->current());
        $this->source->next();
        return true;
    }

    private function appendValueFromSource(): bool {
        if (!$this->source->valid()) {
            $this->sourceExhausted = true;
            return false;
        }

        $this->bufferValue($this->source->current());
        $this->source->next();
        return true;
    }

    private function bufferValue(mixed $value): void {
        $this->buffer[$this->tail] = $value;
        $this->tail++;
    }

    /**
     * Removes buffered values that all active branches have consumed.
     * This prevents unbounded memory growth when branches consume at different rates.
     */
    private function cleanupBuffer(): void {
        $slowestPosition = $this->findSlowestActivePosition();

        if ($slowestPosition === null) {
            $this->clearBuffer();
            return;
        }

        if ($slowestPosition <= $this->head) {
            return;
        }

        $this->evictValuesBeforePosition($slowestPosition);
    }

    private function findSlowestActivePosition(): ?int {
        $slowest = null;

        foreach ($this->cursor as $id => $position) {
            if (!$this->active[$id]) {
                continue;
            }
            $slowest = ($slowest === null) ? $position : min($slowest, $position);
        }

        return $slowest;
    }

    private function clearBuffer(): void {
        $this->buffer = [];
        $this->head = $this->tail;
    }

    private function evictValuesBeforePosition(int $position): void {
        for ($i = $this->head; $i < $position; $i++) {
            unset($this->buffer[$i]);
        }
        $this->head = $position;
    }
}
