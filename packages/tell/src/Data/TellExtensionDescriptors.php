<?php

declare(strict_types=1);

namespace Cognesy\Tell\Data;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/** @implements IteratorAggregate<int, TellExtensionDescriptor> */
final readonly class TellExtensionDescriptors implements Countable, IteratorAggregate
{
    /** @var list<TellExtensionDescriptor> */
    private array $descriptors;

    public function __construct(TellExtensionDescriptor ...$descriptors) {
        $this->descriptors = array_values($descriptors);
    }

    /** @return list<TellExtensionDescriptor> */
    public function all(): array {
        return $this->descriptors;
    }

    #[\Override]
    public function count(): int {
        return count($this->descriptors);
    }

    /** @return Traversable<int, TellExtensionDescriptor> */
    #[\Override]
    public function getIterator(): Traversable {
        return new ArrayIterator($this->descriptors);
    }
}
