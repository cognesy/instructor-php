<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Data;

use InvalidArgumentException;
use IteratorAggregate;
use Traversable;

/** @implements IteratorAggregate<int, BatchJob> */
final readonly class BatchJobPage implements IteratorAggregate
{
    /** @param list<BatchJob> $jobs */
    public function __construct(private array $jobs, private ?BatchCursor $nextCursor = null)
    {
        foreach ($jobs as $job) {
            if (!$job instanceof BatchJob) {
                throw new InvalidArgumentException('Batch job page contains an invalid job.');
            }
        }
    }

    /** @return Traversable<int, BatchJob> */
    public function jobs(): Traversable
    {
        yield from $this->jobs;
    }

    public function nextCursor(): ?BatchCursor
    {
        return $this->nextCursor;
    }

    /** @return Traversable<int, BatchJob> */
    #[\Override]
    public function getIterator(): Traversable
    {
        return $this->jobs();
    }
}
