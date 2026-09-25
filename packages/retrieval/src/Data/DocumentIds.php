<?php

declare(strict_types=1);

namespace Cognesy\Retrieval\Data;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Override;
use Traversable;

/** @implements IteratorAggregate<int, int|string> */
final readonly class DocumentIds implements Countable, IteratorAggregate
{
    /** @param list<int|string> $ids */
    public function __construct(private array $ids = []) {}

    public static function of(int|string ...$ids): self
    {
        return new self(array_values($ids));
    }

    /** @return list<int|string> */
    public function all(): array
    {
        return $this->ids;
    }

    #[Override]
    public function count(): int
    {
        return count($this->ids);
    }

    #[Override]
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->ids);
    }
}
