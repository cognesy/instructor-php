<?php declare(strict_types=1);

namespace Cognesy\Stream\Iterator;

use Cognesy\Stream\TransformationExecution;
use Iterator;

/**
 * Buffered iterator for TransductionExecution with rewind support.
 *
 * Stores all intermediate results in memory, enabling multiple iterations.
 * Use when you need to iterate over transduction results multiple times.
 *
 * $iterator = new BufferedTransductionIterator($execution);
 *
 * First iteration
 * foreach ($iterator as $result) {  }
 *
 * Second iteration - works!
 * foreach ($iterator as $result) { }
 *
 * Trade-off: O(n) memory usage vs single-pass O(1).
 * @implements Iterator<int, mixed>
 */
final class BufferedTransformationIterator implements Iterator
{
    private TransformationExecution $execution;
    private int $position = 0;

    /** @var array<int, mixed> Buffer of intermediate results */
    private array $buffer = [];
    private bool $fullyConsumed = false;

    public function __construct(TransformationExecution $execution) {
        $this->execution = $execution;
    }

    #[\Override]
    public function current(): mixed {
        $this->ensurePositionLoaded();
        return $this->buffer[$this->position] ?? null;
    }

    #[\Override]
    public function key(): int {
        return $this->position;
    }

    #[\Override]
    public function next(): void {
        $this->position++;
    }

    #[\Override]
    public function valid(): bool {
        $this->ensurePositionLoaded();
        return isset($this->buffer[$this->position]);
    }

    #[\Override]
    public function rewind(): void {
        $this->position = 0;
    }

    // INTERNAL ////////////////////////////////////////////////

    private function ensurePositionLoaded(): void {
        // Already in buffer
        if (isset($this->buffer[$this->position])) {
            return;
        }

        // Already fully consumed
        if ($this->fullyConsumed) {
            return;
        }

        // Fill buffer up to current position
        while (!isset($this->buffer[$this->position])) {
            if (!$this->execution->hasNextStep()) {
                $this->fullyConsumed = true;
                return;
            }

            $nextIndex = count($this->buffer);
            $this->buffer[$nextIndex] = $this->execution->step();
        }
    }
}
