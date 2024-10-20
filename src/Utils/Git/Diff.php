<?php

namespace Cognesy\Instructor\Utils\Git;

class Diff
{
    protected GitService $gitService;

    public function __construct(GitService $gitService)
    {
        $this->gitService = $gitService;
    }

    public function against(string $target = 'HEAD'): string
    {
        return $this->gitService->runCommand(sprintf('diff %s', escapeshellarg($target)));
    }

    public function between(string $commitA, string $commitB): string
    {
        return $this->gitService->runCommand(sprintf('diff %s %s', escapeshellarg($commitA), escapeshellarg($commitB)));
    }
}
