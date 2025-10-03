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

    /**
     * @template U
     * @param list<U> $items
     * @return ArrayList<U>
     * @phpstan-return ArrayList<U>
     */
    public static function fromArray(array $items): self {
        return new self($items);
    }

    /**
     * @template U
     * @return ArrayList<U>
     * @phpstan-return ArrayList<U>
     * @phpstan-ignore-next-line
     */
    public static function empty(): self {
        return new self([]);
    }

    /**
     * @template U
     * @param U ...$items
     * @return ArrayList<U>
     * @phpstan-return ArrayList<U>
     */
    public static function of(mixed ...$items): self {
        return new self($items);
    }

    #[\Override]
    public function count(): int {
        return count($this->items);
    }

    #[\Override]
    public function isEmpty(): bool {
        return $this->items === [];
    }

    #[\Override]
    public function itemAt(int $index): mixed {
        if (!array_key_exists($index, $this->items)) {
            throw new OutOfBoundsException("ArrayList index out of range: {$index}");
        }
        return $this->items[$index];
    }

    #[\Override]
    public function getOrNull(int $index): mixed {
        return $this->items[$index] ?? null;
    }

    #[\Override]
    public function first(): mixed {
        return $this->items[0] ?? null;
    }

    #[\Override]
    public function last(): mixed {
        if ($this->items === []) return null;
        return $this->items[array_key_last($this->items)];
    }

    #[\Override]
    public function withAppended(mixed ...$items): static {
        return new self([...$this->items, ...$items]);
    }

    #[\Override]
    public function withInserted(int $index, mixed ...$items): static {
        $n = $this->items;
        if ($index < 0 || $index > count($n)) {
            throw new OutOfBoundsException("Insert index out of range: {$index}");
        }
        array_splice($n, $index, 0, $items);
        return new self($n);
    }

    #[\Override]
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

    #[\Override]
    public function filter(callable $predicate): static {
        return new self(array_values(array_filter($this->items, $predicate)));
    }

    /**
     * @template U
     * @param callable(T):U $mapper
     * @return ListInterface<U>
     */
    #[\Override]
    public function map(callable $mapper): ListInterface {
        return new self(array_map($mapper, $this->items));
    }

    /**
     * @template U
     * @param callable(U,T):U $reducer
     * @param U $initial
     * @return U
     */
    #[\Override]
    public function reduce(callable $reducer, mixed $initial): mixed {
        return array_reduce($this->items, $reducer, $initial);
    }

    #[\Override]
    public function concat(ListInterface $other): static {
        return new self([...$this->items, ...$other->all()]);
    }

    #[\Override]
    public function reverse(): static {
        return new self(array_reverse($this->items));
    }

    /** @return list<T> */
    #[\Override]
    public function all(): array {
        return $this->items;
    }

    /** @return list<T> */
    #[\Override]
    public function toArray(): array {
        return $this->items;
    }

    /** @return Traversable<int,T> */
    #[\Override]
    public function getIterator(): Traversable {
        return new ArrayIterator($this->items);
    }
}
