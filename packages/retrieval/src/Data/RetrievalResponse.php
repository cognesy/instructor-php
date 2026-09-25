<?php

declare(strict_types=1);

namespace Cognesy\Retrieval\Data;

final readonly class RetrievalResponse
{
    public function __construct(
        public RetrievalRequest $request,
        public StorePage $page,
        public string $executionId,
        public float $durationMs,
    ) {}

    public function hits(): SearchHits
    {
        return $this->page->hits;
    }
}
