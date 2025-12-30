<?php declare(strict_types=1);

namespace Cognesy\Utils\Deque;

/**
 * Double-ended queue (deque) with ordered semantics and O(1) end-operations.
 *
 * @template T
 */
interface DequeInterface
{
    public function size(): int;
    public function isEmpty(): bool;
    public function clear(): void;

    /** @param T $value */
    public function pushFront(mixed $value): void;
    /** @param T $value */
    public function pushBack(mixed $value): void;

    /**
     * @return T
     * @throws \UnderflowException if empty
     */
    public function popFront(): mixed;

    /**
     * @return T
     * @throws \UnderflowException if empty
     */
    public function popBack(): mixed;

    /**
     * @return T
     * @throws \UnderflowException if empty
     */
    public function peekFront(): mixed;

    /**
     * @return T
     * @throws \UnderflowException if empty
     */
    public function peekBack(): mixed;

    /**
     * @return list<T> Front (oldest) to back (newest)
     */
    public function toArray(): array;
}

