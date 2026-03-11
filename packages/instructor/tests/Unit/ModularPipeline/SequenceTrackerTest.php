<?php declare(strict_types=1);

use Cognesy\Instructor\Contracts\Sequenceable;
use Cognesy\Instructor\Streaming\Sequence\SequenceTracker;

final class CountingSequence implements Sequenceable
{
    /**
     * @param list<mixed> $items
     */
    public function __construct(
        private array $items = [],
    ) {}

    /**
     * @param list<mixed> $items
     */
    public static function fromItems(array $items): self {
        return new self($items);
    }

    #[\Override]
    public static function of(string $class, string $name = '', string $description = ''): static {
        return new static();
    }

    #[\Override]
    public function toArray(): array {
        return $this->items;
    }

    #[\Override]
    public function get(int $index): mixed {
        return $this->items[$index] ?? null;
    }

    #[\Override]
    public function push(mixed $item): void {
        $this->items[] = $item;
    }

    #[\Override]
    public function pop(): mixed {
        return array_pop($this->items);
    }

    #[\Override]
    public function isEmpty(): bool {
        return $this->items === [];
    }

    #[\Override]
    public function count(): int {
        return count($this->items);
    }
}

it('emits individual completed items while keeping last item pending', function() {
    $sequence = CountingSequence::fromItems(['a', 'b', 'c']);

    $result = SequenceTracker::empty()->consume($sequence);

    // With 3 items, 'a' and 'b' are confirmed, 'c' is held back
    expect($result->updates)->toBe(['a', 'b']);
});

it('finalize emits remaining items', function() {
    $sequence = CountingSequence::fromItems(['a', 'b', 'c']);
    $result = SequenceTracker::empty()->consume($sequence);

    $remaining = $result->tracker->finalize($sequence);

    // Only 'c' was held back
    expect($remaining)->toBe(['c']);
});

it('emits individual items for each completed entry', function() {
    $items = range(1, 8);
    $sequence = CountingSequence::fromItems($items);

    $result = SequenceTracker::empty()->consume($sequence);

    // With 8 items, items 1-7 are confirmed (last kept pending)
    expect($result->updates)->toHaveCount(7);
    expect($result->updates)->toBe(range(1, 7));
});

it('incremental consume tracks emitted position', function() {
    // First batch: 3 items
    $seq1 = CountingSequence::fromItems(['a', 'b', 'c']);
    $result1 = SequenceTracker::empty()->consume($seq1);
    expect($result1->updates)->toBe(['a', 'b']);

    // Second batch: 5 items (2 new)
    $seq2 = CountingSequence::fromItems(['a', 'b', 'c', 'd', 'e']);
    $result2 = $result1->tracker->consume($seq2);
    expect($result2->updates)->toBe(['c', 'd']);

    // Finalize
    $remaining = $result2->tracker->finalize($seq2);
    expect($remaining)->toBe(['e']);
});
