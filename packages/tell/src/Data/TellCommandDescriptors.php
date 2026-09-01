<?php

declare(strict_types=1);

namespace Cognesy\Tell\Data;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/** @implements IteratorAggregate<int, TellCommandDescriptor> */
final readonly class TellCommandDescriptors implements Countable, IteratorAggregate
{
    /** @var list<TellCommandDescriptor> */
    private array $descriptors;

    public function __construct(TellCommandDescriptor ...$descriptors) {
        $this->descriptors = array_values($descriptors);
    }

    public static function merge(self ...$collections): self {
        $descriptors = [];
        foreach ($collections as $collection) {
            array_push($descriptors, ...$collection->all());
        }

        return new self(...$descriptors);
    }

    /** @return list<TellCommandDescriptor> */
    public function all(): array {
        return $this->descriptors;
    }

    #[\Override]
    public function count(): int {
        return count($this->descriptors);
    }

    /** @return Traversable<int, TellCommandDescriptor> */
    #[\Override]
    public function getIterator(): Traversable {
        return new ArrayIterator($this->descriptors);
    }
}
