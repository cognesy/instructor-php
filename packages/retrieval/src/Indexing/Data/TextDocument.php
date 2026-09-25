<?php

declare(strict_types=1);

namespace Cognesy\Retrieval\Indexing\Data;

use InvalidArgumentException;

final readonly class TextDocument
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public string $id,
        public string $content,
        public array $metadata = [],
        public string $sourceId = '',
        public string $sourceVersion = '',
    ) {
        if ($id === '') {
            throw new InvalidArgumentException('Document id cannot be empty');
        }
    }

    public function sourceIdentity(): string
    {
        return $this->sourceId !== '' ? $this->sourceId : $this->id;
    }
}
