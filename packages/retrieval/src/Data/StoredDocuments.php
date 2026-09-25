<?php

declare(strict_types=1);

namespace Cognesy\Retrieval\Data;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Override;
use Traversable;

/** @implements IteratorAggregate<int, StoredDocument> */
final readonly class StoredDocuments implements Countable, IteratorAggregate
{
    /** @param list<StoredDocument> $documents */
    public function __construct(private array $documents = []) {}

    /** @return list<StoredDocument> */
    public function all(): array
    {
        return $this->documents;
    }

    #[Override]
    public function count(): int
    {
        return count($this->documents);
    }

    #[Override]
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->documents);
    }
}
