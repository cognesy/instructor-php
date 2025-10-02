<?php declare(strict_types=1);

namespace Cognesy\Utils\Collection\Contracts;

use Countable;
use IteratorAggregate;
use Traversable;

/**
 * @template T
 * @extends IteratorAggregate<int, T>
 */
interface SetInterface extends Countable, IteratorAggregate
{
    public function contains(mixed $item): bool;

   public function withAdded(mixed ...$items): static;

    public function withRemoved(mixed ...$items): static;

    public function union(SetInterface $other): static;

    public function intersect(SetInterface $other): static;

    public function diff(SetInterface $other): static;

    /** @return list<T> */
    public function values(): array;

    /** @return Traversable<int,T> */
    #[\Override]
    public function getIterator(): Traversable;
}