<?php

declare(strict_types=1);

namespace Cognesy\Tell\Composition\Standalone\Host;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * Ordered providers for one declared contribution capability.
 *
 * @implements IteratorAggregate<int, object>
 */
final readonly class TellCapabilityProviders implements Countable, IteratorAggregate
{
    /** @var list<object> */
    private array $providers;

    /** @param list<object> $providers */
    public function __construct(array $providers) {
        $this->providers = array_values($providers);
    }

    /** @return list<object> */
    public function all(): array {
        return $this->providers;
    }

    #[\Override]
    public function count(): int {
        return count($this->providers);
    }

    /** @return Traversable<int, object> */
    #[\Override]
    public function getIterator(): Traversable {
        return new ArrayIterator($this->providers);
    }
}
