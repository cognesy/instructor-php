<?php

declare(strict_types=1);

namespace Cognesy\Tell\Workspace\Branch;

final readonly class ResolvedBranch
{
    public function __construct(
        public string $branch,
        public string $ref,
        public bool $invocationLocal,
    ) {}

    /** @return array{name: string, source: 'current'|'invocation'} */
    public function toArray(): array {
        return ['name' => $this->branch, 'source' => $this->invocationLocal ? 'invocation' : 'current'];
    }
}
