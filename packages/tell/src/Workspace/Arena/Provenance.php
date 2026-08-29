<?php

declare(strict_types=1);

namespace Cognesy\Tell\Workspace\Arena;

final readonly class Provenance
{
    public function __construct(
        public string $source,
        public ?string $branch,
        public ?ObjectHash $head,
        /** @var array<string, mixed> */
        public array $metadata = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array {
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
