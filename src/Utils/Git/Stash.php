<?php

namespace Cognesy\Instructor\Utils\Git;

class Stash
{
    protected GitService $gitService;

    public function __construct(GitService $gitService)
    {
        $this->gitService = $gitService;
    }

    public function save(): self
    {
        $this->gitService->runCommand('stash');
        return $this;
    }

    public function apply(): self
    {
        $this->gitService->runCommand('stash apply');
        return $this;
    }
}
