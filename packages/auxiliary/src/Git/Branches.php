<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Git;

class Branches
{
    protected GitService $gitService;

    public function __construct(GitService $gitService) {
        $this->gitService = $gitService;
    }

    public function all(): array {
        $branches = $this->gitService->runCommand('branch');
        return array_map('trim', explode("\n", $branches));
    }

    public function current(): string {
        return $this->gitService->runCommand('rev-parse --abbrev-ref HEAD');
    }
}
