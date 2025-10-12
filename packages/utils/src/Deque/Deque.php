<?php declare(strict_types=1);

namespace Cognesy\Utils\Deque;

/**
 * @template T
 * @implements DequeInterface<T>
 */
final class Deque implements DequeInterface
{
    /** @var \SplDoublyLinkedList<T> */
    private \SplDoublyLinkedList $list;

    public function __construct() {
        $this->list = new \SplDoublyLinkedList();
        $this->list->setIteratorMode(\SplDoublyLinkedList::IT_MODE_FIFO);
    }

    #[\Override]
    public function size(): int {
        return $this->list->count();
    }

    #[\Override]
    public function isEmpty(): bool {
        return $this->list->count() === 0;
    }

    #[\Override]
    public function clear(): void {
        while (!$this->list->isEmpty()) {
            $this->list->shift();
        }
    }

    /** @param T $value */
    #[\Override]
    public function pushFront(mixed $value): void {
        $this->list->unshift($value);
    }

    /** @param T $value */
    #[\Override]
    public function pushBack(mixed $value): void {
        $this->list->push($value);
    }

    #[\Override]
    public function popFront(): mixed {
        if ($this->isEmpty()) {
            throw new \UnderflowException('Deque is empty.');
        }
        return $this->list->shift();
    }

    #[\Override]
    public function popBack(): mixed {
        if ($this->isEmpty()) {
            throw new \UnderflowException('Deque is empty.');
        }
        return $this->list->pop();
    }

    #[\Override]
    public function peekFront(): mixed {
        if ($this->isEmpty()) {
            throw new \UnderflowException('Deque is empty.');
        }
        return $this->list->bottom();
    }

    #[\Override]
    public function peekBack(): mixed {
        if ($this->isEmpty()) {
            throw new \UnderflowException('Deque is empty.');
        }
        return $this->list->top();
    }

    /**
     * @return list<T>
     */
    #[\Override]
    public function toArray(): array {
        $result = [];
        foreach ($this->list as $item) {
            $result[] = $item;
        }
        return $result;
    }
}

