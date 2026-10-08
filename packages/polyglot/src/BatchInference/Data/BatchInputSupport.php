<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Data;

use Cognesy\Polyglot\BatchInference\Enums\BatchInputKind;
use InvalidArgumentException;

final readonly class BatchInputSupport
{
    public function __construct(
        private BatchInputKind $kind,
        private ?int $enforcedMaxItems = null,
        private ?int $enforcedMaxBytes = null,
        private ?int $enforcedMaxRecordBytes = null,
        private ?int $enforcedMinItems = null,
    ) {
        foreach ([$enforcedMaxItems, $enforcedMaxBytes, $enforcedMaxRecordBytes, $enforcedMinItems] as $limit) {
            if ($limit !== null && $limit < 1) {
                throw new InvalidArgumentException('Batch input limits must be positive.');
            }
        }
        if ($enforcedMinItems !== null && $enforcedMaxItems !== null && $enforcedMinItems > $enforcedMaxItems) {
            throw new InvalidArgumentException('Batch minimum item count cannot exceed the maximum.');
        }
    }

    public function kind(): BatchInputKind
    {
        return $this->kind;
    }
    public function enforcedMaxItems(): ?int
    {
        return $this->enforcedMaxItems;
    }
    public function enforcedMaxBytes(): ?int
    {
        return $this->enforcedMaxBytes;
    }
    public function enforcedMaxRecordBytes(): ?int
    {
        return $this->enforcedMaxRecordBytes;
    }
    public function enforcedMinItems(): ?int
    {
        return $this->enforcedMinItems;
    }
}
