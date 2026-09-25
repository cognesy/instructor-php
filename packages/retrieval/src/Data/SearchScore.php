<?php

declare(strict_types=1);

namespace Cognesy\Retrieval\Data;

final readonly class SearchScore
{
    public function __construct(
        public float $value,
        public DistanceMetric $metric,
        public string $origin = 'store',
    ) {}

    public function higherIsBetter(): bool
    {
        return $this->metric->higherIsBetter();
    }
}
