<?php declare(strict_types=1);

namespace Cognesy\Stream\Support;

use Cognesy\Stream\TransformationExecution;
use Iterator;
use LogicException;

/**
 * Single-pass iterator for TransductionExecution.
 *
 * Yields intermediate accumulator values from each transduction step.
 * Cannot be rewound - designed for memory-efficient streaming.
 *
 * ```php
 * $iterator = new TransductionIterator($execution);
 * foreach ($iterator as $intermediateResult) {
 *     echo "Step result: $intermediateResult\n";
 * }
 * // Second foreach throws LogicException
 * ```
 * @implements Iterator<int, mixed>
 */
class TransformationIterator implements Iterator
{
    private TransformationExecution $execution;
    private int $position = 0;
    private mixed $current = null;
    private bool $started = false;
    private bool $hasValue = false;

    public function __construct(TransformationExecution $execution) {
        $this->execution = $execution;
    }

    #[\Override]
    public function current(): mixed {
        return $this->current;
    }

    #[\Override]
    public function key(): int {
        return $this->position;
    }

    #[\Override]
    public function next(): void {
        $this->position++;
        $this->loadNextValue();
    }

    #[\Override]
    public function valid(): bool {
        return $this->hasValue;
    }

    #[\Override]
    public function rewind(): void {
        if ($this->started) {
            throw new LogicException('Cannot rewind single-pass transduction iterator');
        }
        $this->started = true;
        $this->loadNextValue();
    }

    // INTERNAL ////////////////////////////////////////////////

    private function loadNextValue(): void {
        if (!$this->execution->hasNextStep()) {
            $this->current = null;
            $this->hasValue = false;
            return;
        }
        $this->current = $this->execution->step();
        $this->hasValue = true;
    }
}
