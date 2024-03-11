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
     * @param T $class
     * @param array<int, T> $items
     */
    private function __construct(string $class, array $items = []) {
        $this->class = $class;
        $this->items = $items;
    }

    /**
     * @param T ...$items
     * @return self<T>
     */
    public static function of(string $class): self {
        return new self($class);
    }

    public function type(): string {
        return $this->class;
    }

    /**
     * @param T ...$items
     * @return self<T>
     */
    public function add(mixed ...$items): self
    {
        foreach ($items as $item) {
            if ($item instanceof $this->class) {
                throw new \InvalidArgumentException("Item must be of type {$this->class}");
            }
            $this->items[] = $item;
        }
        return $this;
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
     * @return T
     */
    public function offsetGet(mixed $offset): mixed {
        return $this->items[$offset];
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