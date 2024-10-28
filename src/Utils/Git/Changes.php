<?php

namespace Cognesy\Instructor\Utils\Git;

use DateTime;

class Changes
{
    protected GitService $gitService;
    protected array $commits = [];

    public function __construct(
        GitService $gitService,
    ) {
        $this->gitService = $gitService;
    }

    public function last(int $days): self
    {
        $since = (new DateTime())->modify(sprintf('-%d days', $days))->format('Y-m-d');
        $command = sprintf("log --since=%s --pretty=format:%%H", escapeshellarg($since));
        $this->commits = array_filter(array_map('trim', explode("\n", $this->gitService->runCommand($command))));
        return $this;
    }

    public function files(): array
    {
        $files = [];
        foreach ($this->commits as $commit) {
            $command = sprintf('diff-tree --no-commit-id --name-only -r %s', escapeshellarg($commit));
            $changedFiles = array_filter(array_map('trim', explode("\n", $this->gitService->runCommand($command))));
            foreach($changedFiles as $file) {
                $files[] = $file;
            }
        }
        return array_unique($files);
    }

    public function content(): array
    {
        $contentChanges = [];
        foreach ($this->files() as $file) {
            $command = sprintf('show %s:%s', escapeshellarg($this->commits[0]), escapeshellarg($file));
            $content = $this->gitService->runCommand($command);
            $contentChanges[] = ['file' => $file, 'content' => $content];
        }
        return $contentChanges;
    }

    public function diffs(): array
    {
        $diffs = [];
        foreach ($this->commits as $commit) {
            foreach ($this->files() as $file) {
                $command = sprintf('diff %s %s -- %s', escapeshellarg($commit . "^"), escapeshellarg($commit), escapeshellarg($file));
                $diff = $this->gitService->runCommand($command);
                $diffs[] = ['file' => $file, 'diff' => $diff];
            }
        }
        return $diffs;
    }
}
