<?php

namespace Cognesy\Aux\Git;

class Git
{
    protected string $repoPath;
    protected GitService $gitService;

    public function __construct(string $repoPath) {
        $this->repoPath = $repoPath;
        $this->gitService = new GitService($repoPath);
    }

    public static function dir(string $repoPath): self {
        return new self($repoPath);
    }

    public function add(string $path): self {
        $this->gitService->runCommand(sprintf('add %s', escapeshellarg($path)));
        return $this;
    }

    public function commit(string $message): self {
        $this->gitService->runCommand(sprintf('commit -m %s', escapeshellarg($message)));
        return $this;
    }

    public function push(string $remote = 'origin', string $branch = 'HEAD'): self {
        $this->gitService->runCommand(sprintf('push %s %s', escapeshellarg($remote), escapeshellarg($branch)));
        return $this;
    }

    public function pull(string $remote = 'origin', string $branch = 'HEAD'): self {
        $this->gitService->runCommand(sprintf('pull %s %s', escapeshellarg($remote), escapeshellarg($branch)));
        return $this;
    }

    public function reset(string $commit = 'HEAD', bool $hard = false): self {
        $command = $hard ? 'reset --hard' : 'reset';
        $this->gitService->runCommand(sprintf('%s %s', $command, escapeshellarg($commit)));
        return $this;
    }

    public function status(): array {
        $status = $this->gitService->runCommand('status --short');
        return array_filter(array_map('trim', explode("\n", $status)));
    }

    public function branch(string $branchName): Branch {
        return new Branch($branchName, $this->gitService);
    }

    public function branches(): Branches {
        return new Branches($this->gitService);
    }

    public function commits(): Commits {
        return new Commits($this->gitService);
    }

    public function remote(string $remote = 'origin'): Remote {
        return new Remote($remote, $this->gitService);
    }

    public function stash(): Stash {
        return new Stash($this->gitService);
    }

    public function diff(): Diff {
        return new Diff($this->gitService);
    }

    public function file(string $path): File {
        return new File($path, $this->gitService);
    }
}
