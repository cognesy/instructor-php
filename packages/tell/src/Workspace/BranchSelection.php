<?php

declare(strict_types=1);

namespace Cognesy\Tell\Workspace;

final readonly class BranchSelection
{
    public function __construct(
        public string $branch,
        public string $ref,
        public bool $invocationLocal,
    ) {}

    /** @return array{name: string, source: 'current'|'invocation'} */
    public function toArray(): array
    {
        return ['name' => $this->branch, 'source' => $this->invocationLocal ? 'invocation' : 'current'];
    }
}
