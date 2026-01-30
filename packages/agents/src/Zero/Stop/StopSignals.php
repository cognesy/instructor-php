<?php declare(strict_types=1);

namespace Cognesy\Agents\Zero\Stop;

/**
 * @implements \IteratorAggregate<int, StopSignal>
 */
final readonly class StopSignals implements \IteratorAggregate
{
    /** @var list<StopSignal> */
    private array $items;

    /**
     * @param list<StopSignal> $items
     */
    private function __construct(array $items) {
        $this->items = $items;
    }

    public static function empty(): self {
        return new self([]);
    }

    /**
     * @param list<StopSignal> $items
     */
    public static function fromList(array $items): self {
        return new self($items);
    }

    public function with(StopSignal $signal): self {
        return new self([...$this->items, $signal]);
    }

    public function isEmptyList(): bool {
        return $this->items === [];
    }

    public function areAllNone(): bool {
        foreach ($this->items as $signal) {
            if (!$signal->isNone()) {
                return false;
            }
        }
        return true;
    }

    public function stopReason(): StopSignal {
        if ($this->items === []) {
            return StopSignal::none();
        }
        return $this->getTopPrioritySignal();
    }

    /**
     * @return \Traversable<int, StopSignal>
     */
    public function getIterator(): \Traversable {
        yield from $this->items;
    }

    /**
     * @return list<StopSignal>
     */
    public function toList(): array {
        return $this->items;
    }

    public function toArray(): array {
        $items = [];
        foreach ($this->items as $signal) {
            $items[] = $signal->toArray();
        }
        return $items;
    }

    public static function fromArray(mixed $data): self {
        if (!is_array($data)) {
            return self::empty();
        }
        $items = [];
        foreach ($data as $signalData) {
            if (is_array($signalData)) {
                $items[] = StopSignal::fromArray($signalData);
            }
        }
        return new self($items);
    }

    private function getTopPrioritySignal(): StopSignal {
        $sorted = $this->sortedItems();
        return $sorted[0] ?? StopSignal::none();
    }

    private function sortedItems(): array {
        if ($this->items === []) {
            return [];
        }
        $sorted = $this->items;
        usort($sorted, static function (StopSignal $left, StopSignal $right): int {
            return $left->compare($right);
        });
        return $sorted;
    }
}
