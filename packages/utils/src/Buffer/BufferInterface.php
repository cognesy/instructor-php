<?php declare(strict_types=1);

namespace Cognesy\Utils\Buffer;

/**
 * General-purpose bounded, ordered buffer (FIFO semantics).
 * Minimal contract to encourage use of DequeInterface for complex flows.
 *
 * @template T
 */
interface BufferInterface
{
    /** @param T $value */
    public function push(mixed $value): void;

    /**
     * @return T
     * @throws \UnderflowException if buffer is empty
     */
    public function pop(): mixed;

    public function count(): int;

    public function isEmpty(): bool;

    /** @return list<T> Oldest to newest */
    public function toArray(): array;
}

