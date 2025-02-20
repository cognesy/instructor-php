<?php

namespace Cognesy\Aux\Git;

class Branch
{
    protected GitService $gitService;
    protected string $branchName;

    public function __construct(string $branchName, GitService $gitService) {
        $this->branchName = $branchName;
        $this->gitService = $gitService;
    }

    public function create(): self {
        $this->gitService->runCommand(sprintf('checkout -b %s', escapeshellarg($this->branchName)));
        return $this;
    }

    public function delete(bool $force = false): self {
        $command = $force ? 'branch -D' : 'branch -d';
        $this->gitService->runCommand(sprintf('%s %s', $command, escapeshellarg($this->branchName)));
        return $this;
    }

    public function rebase(): self {
        $this->gitService->runCommand(sprintf('rebase %s', escapeshellarg($this->branchName)));
        return $this;
    }

    public function merge(): self {
        $this->gitService->runCommand(sprintf('merge %s', escapeshellarg($this->branchName)));
        return $this;
    }

    public function push(string $remote = 'origin'): self {
        $this->gitService->runCommand(sprintf('push %s %s', escapeshellarg($remote), escapeshellarg($this->branchName)));
        return $this;
    }

    public function pull(string $remote = 'origin'): self {
        $this->gitService->runCommand(sprintf('pull %s %s', escapeshellarg($remote), escapeshellarg($this->branchName)));
        return $this;
    }
}