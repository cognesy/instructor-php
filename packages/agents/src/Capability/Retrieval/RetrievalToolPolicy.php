<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Retrieval;

use Cognesy\Retrieval\Context\ContextBudget;
use InvalidArgumentException;

final readonly class RetrievalToolPolicy
{
    public function __construct(
        public int $maxQueryBytes = 4_000,
        public int $maxResults = 5,
        public int $maxContextBytes = 12_000,
        public int $maxContextTokens = 3_000,
        public int $maxExcerptBytes = 3_000,
        public int $maxOutputBytes = 16_000,
        public int $maxReadBytes = 8_000,
    ) {
        if (
            $maxQueryBytes < 1
            || $maxResults < 1
            || $maxContextBytes < 1
            || $maxContextTokens < 1
            || $maxExcerptBytes < 1
            || $maxOutputBytes < 1
            || $maxReadBytes < 1
        ) {
            throw new InvalidArgumentException('Retrieval tool policy values must be positive');
        }
    }

    public function contextBudget(): ContextBudget {
        return new ContextBudget(
            maxBytes: $this->maxContextBytes,
            maxTokens: $this->maxContextTokens,
            maxEvidence: $this->maxResults,
            maxExcerptBytes: $this->maxExcerptBytes,
        );
    }
}
