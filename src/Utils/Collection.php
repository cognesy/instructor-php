<?php
namespace Cognesy\Instructor\Utils;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * @template T
 */
class Collection implements IteratorAggregate, ArrayAccess, Countable
{
    /**
     * @var array<int, T>
     */
    private array $items;
    private string $class;

    /**
     * @param string $class
     * @param array<int, T> $items
     */
    public function __construct(string $class, array $items = []) {
        $this->class = $class;
        $this->items = $items;
    }

    /**
     * @param string $class
     * @return static<T>
     */
    public static function of(string $class): self {
        return new self($class);
    }

    public function getType(): string {
        return $this->class;
    }

    /**
     * @param array<int, T> $items
     * @return static<T>
     */
    public function add(array $items): self {
        $newItems = array_filter($items, fn($item) => $item instanceof $this->class);
        if (count($items) !== count($newItems)) {
            throw new \InvalidArgumentException("All items must be of type {$this->class}");
        }
        return new self($this->class, array_merge($this->items, $newItems));
    }

    public function count(): int {
        return count($this->items);
    }

    /**
     * @param int $offset
     */
    public function offsetExists(mixed $offset): bool {
        return isset($this->items[$offset]);
    }

    /**
     * @param int $offset
     * @return T|null
     */
    public function offsetGet(mixed $offset): mixed {
        return $this->items[$offset] ?? null;
    }

    /**
     * @param int $offset
     * @param T $value
     */
    public function offsetSet(mixed $offset, mixed $value): void {
        $this->items[$offset] = $value;
    }

    /**
     * @param int $offset
     */
    public function offsetUnset(mixed $offset): void {
        unset($this->items[$offset]);
    }

    /**
     * @return Traversable<int, T>
     */
    public function getIterator(): Traversable {
        return new ArrayIterator($this->items);
    }
}