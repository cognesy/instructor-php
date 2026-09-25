<?php

declare(strict_types=1);

namespace Cognesy\Retrieval\Context;

use InvalidArgumentException;

final readonly class ContextBudget
{
    public function __construct(
        public int $maxBytes = 16_000,
        public int $maxTokens = 4_000,
        public int $maxEvidence = 8,
        public int $maxExcerptBytes = 4_000,
    ) {
        if ($maxBytes < 1 || $maxTokens < 1 || $maxEvidence < 1 || $maxExcerptBytes < 1) {
            throw new InvalidArgumentException('Context budget values must be positive');
        }
    }
}
