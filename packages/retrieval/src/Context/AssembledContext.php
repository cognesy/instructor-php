<?php

declare(strict_types=1);

namespace Cognesy\Retrieval\Context;

use Countable;
use Override;

final readonly class AssembledContext implements Countable
{
    /** @param list<EvidenceReference> $evidence */
    public function __construct(
        public string $text,
        public array $evidence,
        public int $bytes,
        public int $tokens,
        public int $omitted,
    ) {}

    /** @return array<string, EvidenceReference> */
    public function citations(): array
    {
        $citations = [];
        foreach ($this->evidence as $reference) {
            $citations[$reference->citation] = $reference;
        }

        return $citations;
    }

    #[Override]
    public function count(): int
    {
        return count($this->evidence);
    }
}
