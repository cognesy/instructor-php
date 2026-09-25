<?php

declare(strict_types=1);

namespace Cognesy\Retrieval\Indexing\Data;

final readonly class IndexingReport
{
    public function __construct(
        public int $sources = 0,
        public int $indexed = 0,
        public int $removed = 0,
        public int $batches = 0,
    ) {}

    public function plus(self $other): self
    {
        return new self(
            $this->sources + $other->sources,
            $this->indexed + $other->indexed,
            $this->removed + $other->removed,
            $this->batches + $other->batches,
        );
    }
}
