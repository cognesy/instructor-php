<?php

namespace Cognesy\Instructor\Utils\Git;

class Remote
{
    protected GitService $gitService;

    public function __construct(GitService $gitService)
    {
        $this->gitService = $gitService;
    }

    public function url(): string
    {
        return $this->gitService->runCommand('config --get remote.origin.url');
    }

    public function diff(string $branch): string
    {
        return $this->gitService->runCommand(sprintf('diff origin/%s', escapeshellarg($branch)));
    }
}
