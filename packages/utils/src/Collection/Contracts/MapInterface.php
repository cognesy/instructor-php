<?php declare(strict_types=1);

namespace Cognesy\Utils\Collection\Contracts;

use Countable;
use IteratorAggregate;
use Traversable;

/**
 * @template K of array-key
 * @template V
 * @extends IteratorAggregate<K, V>
 */
interface MapInterface extends Countable, IteratorAggregate
{
    public function has(int|string $key): bool;

    /** @return V */
    public function get(int|string $key): mixed; // throws OutOfBoundsException

    /** @return ?V */
    public function getOrNull(int|string $key): mixed;

    public function with(int|string $key, mixed $value): static;

    /** @param array<K,V> $entries */
    public function withAll(array $entries): static;

    public function withRemoved(int|string $key): static;

    public function merge(MapInterface $other): static;

    /** @return list<K> */
    public function keys(): array;

    /** @return list<V> */
    public function values(): array;

    /** @return array<K,V> */
    public function toArray(): array;

    /** @return Traversable<K,V> */
    #[\Override]
    public function getIterator(): Traversable;
}