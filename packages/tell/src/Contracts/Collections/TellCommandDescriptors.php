<?php

declare(strict_types=1);

namespace Cognesy\Tell\Contracts\Collections;

use ArrayIterator;
use Cognesy\Tell\Contracts\Data\TellCommandDescriptor;
use Countable;
use IteratorAggregate;
use Traversable;

/** @implements IteratorAggregate<int, TellCommandDescriptor> */
final readonly class TellCommandDescriptors implements Countable, IteratorAggregate
{
    /** @var list<TellCommandDescriptor> */
    private array $descriptors;

    public function __construct(TellCommandDescriptor ...$descriptors)
    {
        $this->descriptors = array_values($descriptors);
    }

    /** @return list<TellCommandDescriptor> */
    public function all(): array
    {
        return $this->descriptors;
    }

    public function count(): int
    {
        return count($this->descriptors);
    }

    /** @return Traversable<int, TellCommandDescriptor> */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->descriptors);
    }
}
