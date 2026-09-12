<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Data;

/** One explicit semantic change applied before provider rendering. */
final readonly class InferenceRequestAdjustment
{
    public function __construct(
        public string $feature,
        public string $requested,
        public string $effective,
        public string $reason,
    ) {}

    /** @return array{feature: string, requested: string, effective: string, reason: string} */
    public function toArray(): array
    {
        return [
            'feature' => $this->feature,
            'requested' => $this->requested,
            'effective' => $this->effective,
            'reason' => $this->reason,
        ];
    }
}
