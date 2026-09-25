<?php

declare(strict_types=1);

namespace Cognesy\Retrieval\Data;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Override;
use Traversable;

/** @implements IteratorAggregate<int, SearchHit> */
final readonly class SearchHits implements Countable, IteratorAggregate
{
    /** @param list<SearchHit> $hits */
    public function __construct(private array $hits = []) {}

    /** @return list<SearchHit> */
    public function all(): array
    {
        return $this->hits;
    }

    public function first(): ?SearchHit
    {
        return $this->hits[0] ?? null;
    }

    #[Override]
    public function count(): int
    {
        return count($this->hits);
    }

    #[Override]
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->hits);
    }
}
