<?php declare(strict_types=1);

namespace Cognesy\Utils\Buffer;

/**
 * Simple array-based implementation of BufferInterface.
 *
 * @template T
 * @implements BufferInterface<T>
 */
final class ArrayBuffer implements BufferInterface
{
    /** @var array<int, T> */
    private array $buffer = [];

    /** @param T $value */
    #[\Override]
    public function push(mixed $value): void {
        $this->buffer[] = $value;
    }

    /**
     * @return T
     * @throws \UnderflowException if buffer is empty
     */
    #[\Override]
    public function pop(): mixed {
        if ($this->isEmpty()) {
            throw new \UnderflowException('Buffer is empty.');
        }
        /** @var T $value */
        $value = array_shift($this->buffer);
        return $value;
    }

    #[\Override]
    public function count(): int {
        return count($this->buffer);
    }

    #[\Override]
    public function isEmpty(): bool {
        return empty($this->buffer);
    }

    /** @return list<T> Oldest to newest */
    #[\Override]
    public function toArray(): array {
        return $this->buffer;
    }
}