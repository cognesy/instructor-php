<?php

declare(strict_types=1);

namespace Cognesy\Retrieval\Context;

use Cognesy\Retrieval\Data\SearchScore;

final readonly class EvidenceReference
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public string $citation,
        public int|string $id,
        public string $content,
        public SearchScore $score,
        public array $metadata = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'citation' => $this->citation,
            'id' => $this->id,
            'content' => $this->content,
            'score' => $this->score->value,
            'scoreMetric' => $this->score->metric->value,
            'scoreOrigin' => $this->score->origin,
            'metadata' => $this->metadata,
        ];
    }
}
