<?php declare(strict_types=1);

namespace Cognesy\Utils\Collection\Contracts;

use Countable;
use IteratorAggregate;
use Traversable;

/**
 * @template T
 */
interface ListInterface extends Countable, IteratorAggregate
{
    /** @return list<T> */
    public function all(): array;

    public function isEmpty(): bool;

    /** @return mixed<T> */
    public function get(int $index): mixed; // throws OutOfBoundsException

    /** @return ?T */
    public function getOrNull(int $index): mixed;

    /** @return ?T */
    public function first(): mixed;

    /** @return ?T */
    public function last(): mixed;

    public function withAdded(mixed ...$items): static;

    public function withInserted(int $index, mixed ...$items): static;

    public function withRemovedAt(int $index, int $count = 1): static;

    /** @param callable(T):bool $predicate */
    public function filter(callable $predicate): static;

    /** @template U @param callable(T):U $mapper @return ListInterface */
    public function map(callable $mapper): ListInterface;

    /** @template U @param callable(U,T):U $reducer @param U $initial @return U */
    public function reduce(callable $reducer, mixed $initial): mixed;

    public function concat(ListInterface $other): static;

    public function reverse(): static;

    /** @return list<T> */
    public function toArray(): array;

    /** @return Traversable<int,T> */
    public function getIterator(): Traversable;
}
