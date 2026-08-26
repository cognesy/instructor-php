<?php

declare(strict_types=1);

namespace Cognesy\Tell\Contracts\Collections;

use ArrayIterator;
use Cognesy\Tell\Contracts\Data\TellExtensionDescriptor;
use Countable;
use IteratorAggregate;
use Traversable;

/** @implements IteratorAggregate<int, TellExtensionDescriptor> */
final readonly class TellExtensionDescriptors implements Countable, IteratorAggregate
{
    /** @var list<TellExtensionDescriptor> */
    private array $descriptors;

    public function __construct(TellExtensionDescriptor ...$descriptors)
    {
        $this->descriptors = array_values($descriptors);
    }

    /** @return list<TellExtensionDescriptor> */
    public function all(): array
    {
        return $this->descriptors;
    }

    public function count(): int
    {
        return count($this->descriptors);
    }

    /** @return Traversable<int, TellExtensionDescriptor> */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->descriptors);
    }
}
