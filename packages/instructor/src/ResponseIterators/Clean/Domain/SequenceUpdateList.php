<?php declare(strict_types=1);

namespace Cognesy\Instructor\ResponseIterators\Clean\Domain;

use Cognesy\Instructor\Contracts\Sequenceable;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * Collection of Sequenceable snapshots representing sequence updates.
 *
 * Replaces raw arrays with typed collection for safety and clarity.
 * Each item is a snapshot of the sequence at a specific point.
 *
 * @implements IteratorAggregate<int, Sequenceable>
 */
final readonly class SequenceUpdateList implements IteratorAggregate, Countable
{
    /**
     * @param array<int, Sequenceable> $items
     */
    private function __construct(
        private array $items,
    ) {}

    public static function empty(): self {
        return new self([]);
    }

    /**
     * @param Sequenceable[] $items
     */
    public static function of(array $items): self {
        return new self($items);
    }

    public function withAdded(Sequenceable $item): self {
        return new self([...$this->items, $item]);
    }

    #[\Override]
    public function getIterator(): Traversable {
        yield from $this->items;
    }

    #[\Override]
    public function count(): int {
        return count($this->items);
    }

    public function isEmpty(): bool {
        return $this->items === [];
    }

    /**
     * @return Sequenceable[]
     */
    public function toArray(): array {
        return $this->items;
    }
}
