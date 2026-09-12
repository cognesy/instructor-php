<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Data;

/** Immutable semantic changes applied before provider rendering. */
final readonly class InferenceRequestAdjustments
{
    /** @var list<InferenceRequestAdjustment> */
    private array $items;

    public function __construct(InferenceRequestAdjustment ...$items)
    {
        $this->items = array_values($items);
    }

    public static function empty(): self
    {
        static $empty = null;

        return $empty ??= new self;
    }

    public function with(InferenceRequestAdjustment $adjustment): self
    {
        return new self(...[...$this->items, $adjustment]);
    }

    /** @return list<InferenceRequestAdjustment> */
    public function all(): array
    {
        return $this->items;
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    /** @return list<array{feature: string, requested: string, effective: string, reason: string}> */
    public function toArray(): array
    {
        return array_map(
            static fn (InferenceRequestAdjustment $adjustment): array => $adjustment->toArray(),
            $this->items,
        );
    }
}
