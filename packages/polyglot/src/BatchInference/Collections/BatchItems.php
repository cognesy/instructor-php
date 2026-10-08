<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Collections;

use Cognesy\Polyglot\BatchInference\Data\BatchItem;
use IteratorAggregate;
use Traversable;

/** @implements IteratorAggregate<int, BatchItem> */
final readonly class BatchItems implements IteratorAggregate
{
    /** @param iterable<BatchItem> $items */
    private function __construct(private iterable $items)
    {
    }

    public static function of(BatchItem ...$items): self
    {
        return new self($items);
    }

    /** @param iterable<BatchItem> $items */
    public static function fromIterable(iterable $items): self
    {
        return new self($items);
    }

    /** @return Traversable<int, BatchItem> */
    #[\Override]
    public function getIterator(): Traversable
    {
        yield from $this->items;
    }
}
