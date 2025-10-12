<?php declare(strict_types=1);

namespace Cognesy\Utils\Buffer;

use Cognesy\Utils\Deque\Deque;
use Cognesy\Utils\Deque\DequeInterface;

/**
 * Fixed-size circular buffer built on a Deque.
 * Overwrites the oldest element when full.
 *
 * @template T
 * @implements BoundedBufferInterface<T>
 */
final class SimpleRingBuffer implements BoundedBufferInterface
{
    /** @var DequeInterface<T> */
    private DequeInterface $deque;
    private int $capacity;

    public function __construct(
        int $capacity,
        ?DequeInterface $deque = null
    ) {
        if ($capacity <= 0) {
            throw new \InvalidArgumentException('Capacity must be greater than 0.');
        }
        $this->capacity = $capacity;
        $this->deque = $deque ?? new Deque();
    }

    /** @param T $value */
    #[\Override]
    public function push(mixed $value): void {
        if ($this->count() >= $this->capacity) {
            $this->deque->popFront();
        }
        $this->deque->pushBack($value);
    }

    #[\Override]
    public function pop(): mixed {
        if ($this->isEmpty()) {
            throw new \UnderflowException('Buffer is empty.');
        }
        return $this->deque->popFront();
    }

    #[\Override]
    public function count(): int {
        return $this->deque->size();
    }

    #[\Override]
    public function isEmpty(): bool {
        return $this->deque->isEmpty();
    }

    /**
     * @return list<T>
     */
    #[\Override]
    public function toArray(): array {
        return $this->deque->toArray();
    }

    #[\Override]
    public function isFull(): bool {
        return ($this->count() >= $this->capacity);
    }

    #[\Override]
    public function capacity(): int {
        return $this->capacity;
    }
}

