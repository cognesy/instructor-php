<?php

declare(strict_types=1);

namespace Cognesy\Retrieval\Data;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Override;
use Traversable;

/** @implements IteratorAggregate<int, VectorDocument> */
final readonly class VectorDocuments implements Countable, IteratorAggregate
{
    /** @param list<VectorDocument> $documents */
    public function __construct(private array $documents = []) {}

    public static function of(VectorDocument ...$documents): self
    {
        return new self(array_values($documents));
    }

    /** @return list<VectorDocument> */
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
