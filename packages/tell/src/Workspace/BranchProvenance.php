<?php

declare(strict_types=1);

namespace Cognesy\Tell\Workspace;

use Cognesy\Tell\Canonical\CanonicalHash;

final readonly class BranchProvenance
{
    public function __construct(
        public string $source,
        public ?string $branch,
        public ?CanonicalHash $head,
        /** @var array<string, mixed> */
        public array $metadata = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $data = [
            'source' => $this->source,
            'branch' => $this->branch,
            'head' => $this->head?->toString(),
        ];
        if ($this->metadata !== []) {
            $data['metadata'] = $this->metadata;
        }

        return $data;
    }
}
