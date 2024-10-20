<?php

namespace Cognesy\Instructor\Utils\Git;

class Commits
{
    protected GitService $gitService;

    public function __construct(GitService $gitService)
    {
        $this->gitService = $gitService;
    }

    public function last(): Commit
    {
        $hash = $this->gitService->runCommand('rev-parse HEAD');
        return new Commit($hash, $this->gitService);
    }

    public function log(int $number = 10): array
    {
        $log = $this->gitService->runCommand(sprintf('log -n %d --pretty=format:%%H', $number));
        $hashes = array_filter(array_map('trim', explode("\n", $log)));
        return array_map(fn($hash) => new Commit($hash, $this->gitService), $hashes);
    }
}
