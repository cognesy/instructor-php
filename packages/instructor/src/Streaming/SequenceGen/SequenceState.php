<?php declare(strict_types=1);

namespace Cognesy\Instructor\Streaming\SequenceGen;

use Cognesy\Instructor\Contracts\Sequenceable;
use Cognesy\Utils\Json\Json;

final readonly class SequenceState
{
    private ?Sequenceable $sequence;
    private array $delta;
    private int $previousLength;

    public function __construct(
        ?Sequenceable $sequence = null,
        array $delta = [],
        ?int $previousLength = null,
    ) {
        $this->sequence = $sequence;
        $this->delta = $delta;
        $this->previousLength = $previousLength ?? 0;
    }

    // STATES /////////////////////////////////////////////////////

    public static function initial(): self {
        return new self();
    }

    // TRANSITIONS ////////////////////////////////////////////////

    public function updateSequence(Sequenceable $sequence): self {
        $delta = $this->makeDeltaSequence(
            currentSequence: $this->sequence,
            previousLength: $this->previousLength,
            newSequence: $sequence,
        );
        return $this->with(
            sequence: $sequence,
            delta: $delta,
            // previousLength stays the same - will be updated after dispatching updates
        );
    }

    public function confirmUpdates(): self {
        // Mark all items except the last as confirmed
        $confirmedLength = max(0, $this->length() - 1);
        return $this->with(previousLength: $confirmedLength);
    }

    public function completeSequence(Sequenceable $sequence): self {
        return $this->with(
            sequence: $sequence,
            previousLength: $this->length(),
        );
    }

    // ACCESSORS //////////////////////////////////////////////////

    public function sequence(): ?Sequenceable {
        return $this->sequence;
    }

    public function updates(): array {
        if ($this->sequence === null) {
            return [];
        }

        return $this->makeListOfSequenceUpdates(
            sequence: $this->sequence,
            lastLength: $this->previousLength,
            // Ensure non-negative target length to avoid infinite popping when sequence is empty
            targetLength: max(0, $this->length() - 1),
        );
    }

    public function length(): int {
        return $this->sequence?->count() ?? 0;
    }

    public function previousLength(): int {
        return $this->previousLength;
    }

    // INTERNAL ///////////////////////////////////////////////////

    private function with(
        ?Sequenceable $sequence = null,
        ?array $delta = null,
        ?int $previousLength = null,
    ): self {
        return new self(
            sequence: $sequence ?? $this->sequence,
            delta: $delta ?? $this->delta,
            previousLength: $previousLength ?? $this->previousLength,
        );
    }

    private function makeListOfSequenceUpdates(Sequenceable $sequence, int $lastLength, int $targetLength) : array {
        $itemsInOrder = [];
        $current = clone $sequence;

        // Remove items after targetLength to ensure we only process complete items
        while (count($current) > $targetLength) {
            $current->pop();
        }

        // Collect states for each new complete item
        while (count($current) > $lastLength) {
            $itemsInOrder[] = clone $current;
            $current->pop();
        }

        return array_reverse($itemsInOrder);
    }

    private function makeDeltaSequence(
        ?Sequenceable $currentSequence,
        int $previousLength,
        Sequenceable $newSequence,
    ) : array {
        if ($currentSequence === null) {
            return $this->sequenceItems($newSequence);
        }
        $delta = $this->sequenceItems($newSequence);
        if (count($delta) <= $previousLength) {
            return [];
        }
        $delta = array_slice($delta, $previousLength);
        return $delta;
    }

    private function sequenceItems(Sequenceable $sequence): array {
        $copy = clone $sequence;
        $items = [];
        while (!$copy->isEmpty()) {
            $items[] = $copy->pop();
        }
        return $items;
    }
}
