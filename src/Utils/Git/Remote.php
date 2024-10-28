<?php

namespace Cognesy\Instructor\Utils\Git;

class Remote
{
    protected GitService $gitService;
    private string $remote;

    public function __construct(
        string $remote,
        GitService $gitService,
    ) {
        $this->remote = $remote;
        $this->gitService = $gitService;
    }

    public function url(): string {
        return $this->gitService->runCommand('config --get remote.origin.url');
    }

    public function diff(string $branch): string {
        return $this->gitService->runCommand(sprintf('diff %s/%s', escapeshellarg($this->remote), escapeshellarg($branch)));
    }

    public function push(string $branch = 'HEAD'): self {
        $this->gitService->runCommand(sprintf('push %s %s', escapeshellarg($this->remote), escapeshellarg($branch)));
        return $this;
    }

    public function pull(string $branch = 'HEAD'): self {
        $this->gitService->runCommand(sprintf('pull %s %s', escapeshellarg($this->remote), escapeshellarg($branch)));
        return $this;
    }
}
