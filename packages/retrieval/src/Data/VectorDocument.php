<?php

declare(strict_types=1);

namespace Cognesy\Retrieval\Data;

use Cognesy\Polyglot\Embeddings\Data\Vector;
use InvalidArgumentException;

final readonly class VectorDocument
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public int|string $id,
        public Vector $vector,
        public array $metadata = [],
        public ?string $content = null,
        public string $embeddingSpace = '',
    ) {
        self::validateVector($vector);
    }

    public function dimension(): int
    {
        return count($this->vector->values());
    }

    private static function validateVector(Vector $vector): void
    {
        if ($vector->values() === []) {
            throw new InvalidArgumentException('Vector values cannot be empty');
        }
        foreach ($vector->values() as $value) {
            if (! is_finite($value)) {
                throw new InvalidArgumentException('Vector values must be finite');
            }
        }
    }
}
