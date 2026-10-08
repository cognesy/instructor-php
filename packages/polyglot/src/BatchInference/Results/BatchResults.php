<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Results;

use Cognesy\Polyglot\BatchInference\Data\BatchItemResult;
use Cognesy\Polyglot\BatchInference\Enums\BatchResultsAvailability;
use Closure;
use LogicException;

final class BatchResults
{
    private bool $started = false;

    /** @param iterable<BatchItemResult>|Closure():iterable<BatchItemResult> $items */
    public function __construct(
        private readonly BatchResultsAvailability $availability,
        private readonly iterable|Closure $items,
        private readonly ?string $unavailableReason = null,
    ) {
    }

    public function availability(): BatchResultsAvailability
    {
        return $this->availability;
    }
    public function isAvailable(): bool
    {
        return $this->availability->isAvailable();
    }
    public function isFinal(): bool
    {
        return $this->availability === BatchResultsAvailability::Final;
    }
    public function unavailableReason(): ?string
    {
        return $this->unavailableReason;
    }

    /** @return iterable<BatchItemResult> */
    public function items(): iterable
    {
        if (!$this->isAvailable()) {
            throw new LogicException('Batch results are not available.');
        }
        if ($this->started) {
            throw new LogicException('Batch results can be traversed only once.');
        }
        $this->started = true;
        $items = $this->items instanceof Closure ? ($this->items)() : $this->items;
        yield from $items;
    }
}
