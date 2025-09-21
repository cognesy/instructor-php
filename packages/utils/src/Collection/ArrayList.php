<?php declare(strict_types=1);

namespace Cognesy\Utils\Collection;

use ArrayIterator;
use Cognesy\Utils\Collection\Contracts\ListInterface;
use OutOfBoundsException;
use Traversable;

/**
 * @template T
 * @implements ListInterface<T>
 */
final class ArrayList implements ListInterface
{
    /** @var list<T> */
    private array $items;

    private function __construct(array $items) {
        // reindex to ensure list semantics
        $this->items = array_values($items);
    }

    /** @template U @param list<U> $items @return ArrayList<U> */
    public static function fromArray(array $items): self {
        return new self($items);
    }

    /** @template U @return ArrayList<U> */
    public static function empty(): self {
        return new self([]);
    }

    /** @template U @param U ...$items @return ArrayList<U> */
    public static function of(mixed ...$items): self {
        return new self($items);
    }

    public function count(): int {
        return count($this->items);
    }

    public function isEmpty(): bool {
        return $this->items === [];
    }

    public function get(int $index): mixed {
        if (!array_key_exists($index, $this->items)) {
            throw new OutOfBoundsException("ArrayList index out of range: {$index}");
        }
        return $this->items[$index];
    }

    public function getOrNull(int $index): mixed {
        return $this->items[$index] ?? null;
    }

    public function first(): mixed {
        return $this->items[0] ?? null;
    }

    public function last(): mixed {
        if ($this->items === []) return null;
        return $this->items[array_key_last($this->items)];
    }

    public function withAdded(mixed ...$items): static {
        return new self([...$this->items, ...$items]);
    }

    public function withInserted(int $index, mixed ...$items): static {
        $n = $this->items;
        if ($index < 0 || $index > count($n)) {
            throw new OutOfBoundsException("Insert index out of range: {$index}");
        }
        array_splice($n, $index, 0, $items);
        return new self($n);
    }

    public function withRemovedAt(int $index, int $count = 1): static {
        if ($count < 0) {
            throw new OutOfBoundsException("Removal count must be >= 0");
        }
        $n = $this->items;
        if ($index < 0 || $index >= count($n)) {
            throw new OutOfBoundsException("Remove index out of range: {$index}");
        }
        array_splice($n, $index, $count);
        return new self($n);
    }

    public function filter(callable $predicate): static {
        return new self(array_values(array_filter($this->items, $predicate)));
    }

    public function map(callable $mapper): ListInterface {
        return new self(array_map($mapper, $this->items));
    }

    public function reduce(callable $reducer, mixed $initial): mixed {
        return array_reduce($this->items, $reducer, $initial);
    }

    public function concat(ListInterface $other): static {
        return new self([...$this->items, ...$other->all()]);
    }

    public function reverse(): static {
        return new self(array_reverse($this->items));
    }

    /** @return list<T> */
    public function all(): array {
        return $this->items;
    }

    /** @return list<T> */
    public function toArray(): array {
        return $this->items;
    }

    /** @return Traversable<int,T> */
    public function getIterator(): Traversable {
        return new ArrayIterator($this->items);
    }
}
