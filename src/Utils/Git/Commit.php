<?php

namespace Cognesy\Instructor\Utils\Git;

class Commit
{
    protected string $hash;
    protected GitService $gitService;

    public function __construct(string $hash, GitService $gitService)
    {
        $this->hash = $hash;
        $this->gitService = $gitService;
    }

    public function hash(): string
    {
        return $this->hash;
    }

    public function author(): string
    {
        return $this->gitService->runCommand(sprintf('log -1 --pretty=format:%%an %%ae %s', escapeshellarg($this->hash)));
    }

    public function message(): string
    {
        return $this->gitService->runCommand(sprintf('log -1 --pretty=format:%%B %s', escapeshellarg($this->hash)));
    }

    public function date(): string
    {
        return $this->gitService->runCommand(sprintf('log -1 --pretty=format:%%ad %s', escapeshellarg($this->hash)));
    }
}
