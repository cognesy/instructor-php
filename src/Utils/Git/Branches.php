<?php

namespace Cognesy\Instructor\Utils\Git;

class Branches
{
    protected GitService $gitService;

    public function __construct(GitService $gitService)
    {
        $this->gitService = $gitService;
    }

    public function current(): string
    {
        return $this->gitService->runCommand('rev-parse --abbrev-ref HEAD');
    }

    public function all(): array
    {
        $branches = $this->gitService->runCommand('branch');
        return array_map('trim', explode("\n", $branches));
    }

    public function create(string $branchName): self
    {
        $this->gitService->runCommand(sprintf('checkout -b %s', escapeshellarg($branchName)));
        return $this;
    }

    public function delete(string $branchName, bool $force = false): self
    {
        $command = $force ? 'branch -D' : 'branch -d';
        $this->gitService->runCommand(sprintf('%s %s', $command, escapeshellarg($branchName)));
        return $this;
    }
}
