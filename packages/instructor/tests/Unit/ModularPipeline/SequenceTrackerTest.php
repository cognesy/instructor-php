<?php declare(strict_types=1);

use Cognesy\Instructor\Contracts\Sequenceable;
use Cognesy\Instructor\ResponseIterators\ModularPipeline\Domain\SequenceTracker;

final class CountingSequence implements Sequenceable
{
    public static int $popCount = 0;

    /**
     * @param list<mixed> $items
     */
    public function __construct(private array $items = []) {}

    public static function resetCounters(): void {
        self::$popCount = 0;
    }

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
    public function push(mixed $item): void {
        $this->items[] = $item;
    }

    #[\Override]
    public function pop(): mixed {
        self::$popCount++;
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

it('emits pending sequence snapshots while keeping last item mutable', function() {
    $sequence = CountingSequence::fromItems(['a', 'b', 'c']);

    $result = SequenceTracker::empty()->consume($sequence);
    $snapshots = array_map(
        fn(Sequenceable $update): array => $update->toArray(),
        $result->updates->toArray(),
    );

    expect($snapshots)->toBe([
        ['a'],
        ['a', 'b'],
    ]);
});

it('finalize emits full sequence snapshot once', function() {
    $sequence = CountingSequence::fromItems(['a', 'b', 'c']);
    $tracker = SequenceTracker::empty()->consume($sequence)->tracker;

    $updates = $tracker->finalize()->toArray();

    expect($updates)->toHaveCount(1)
        ->and($updates[0]->toArray())->toBe(['a', 'b', 'c']);
});

it('generates pending updates with linear pop count', function() {
    CountingSequence::resetCounters();
    $items = range(1, 8);
    $sequence = CountingSequence::fromItems($items);

    SequenceTracker::empty()->consume($sequence);

    expect(CountingSequence::$popCount)->toBe(count($items) - 1);
});
