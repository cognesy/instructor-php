<?php

namespace Cognesy\Instructor\Utils\Git;

class Git
{
    protected string $repoPath;
    protected GitService $gitService;

    public function __construct(string $repoPath)
    {
        $this->repoPath = $repoPath;
        $this->gitService = new GitService($repoPath);
    }

    public static function dir(string $repoPath): self
    {
        return new self($repoPath);
    }

    public function add(string $path): self
    {
        $this->gitService->runCommand(sprintf('add %s', escapeshellarg($path)));
        return $this;
    }

    public function commit(string $message): self
    {
        $this->gitService->runCommand(sprintf('commit -m %s', escapeshellarg($message)));
        return $this;
    }

    public function push(string $remote = 'origin', string $branch = 'HEAD'): self
    {
        $this->gitService->runCommand(sprintf('push %s %s', escapeshellarg($remote), escapeshellarg($branch)));
        return $this;
    }

    public function pull(string $remote = 'origin', string $branch = 'HEAD'): self
    {
        $this->gitService->runCommand(sprintf('pull %s %s', escapeshellarg($remote), escapeshellarg($branch)));
        return $this;
    }

    public function branches(): Branches
    {
        return new Branches($this->gitService);
    }

    public function commits(): Commits
    {
        return new Commits($this->gitService);
    }

    public function reset(string $commit = 'HEAD', bool $hard = false): self
    {
        $command = $hard ? 'reset --hard' : 'reset';
        $this->gitService->runCommand(sprintf('%s %s', $command, escapeshellarg($commit)));
        return $this;
    }

    public function merge(string $branch): self
    {
        $this->gitService->runCommand(sprintf('merge %s', escapeshellarg($branch)));
        return $this;
    }

    public function rebase(string $branch): self
    {
        $this->gitService->runCommand(sprintf('rebase %s', escapeshellarg($branch)));
        return $this;
    }

    public function status(): array
    {
        $status = $this->gitService->runCommand('status --short');
        return array_filter(array_map('trim', explode("\n", $status)));
    }

    public function remote(): Remote
    {
        return new Remote($this->gitService);
    }

    public function stash(): Stash
    {
        return new Stash($this->gitService);
    }

    public function diff(): Diff
    {
        return new Diff($this->gitService);
    }

    public function file(): File
    {
        return new File($this->gitService);
    }
}
