<?php

declare(strict_types=1);

namespace Cognesy\Retrieval\Data;

final readonly class DocumentPage
{
    public function __construct(
        public StoredDocuments $documents,
        public ?StoreContinuation $continuation = null,
    ) {}

    public function exhausted(): bool
    {
        return $this->continuation === null;
    }
}
