<?php

declare(strict_types=1);

namespace Cognesy\Retrieval\Data;

use Cognesy\Polyglot\Embeddings\Data\Vector;

final readonly class StoredDocument
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public int|string $id,
        public array $metadata = [],
        public ?string $content = null,
        public ?Vector $vector = null,
        public string $embeddingSpace = '',
    ) {}
}
