<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Git;

use RuntimeException;

class GitService
{
    protected string $repoPath;

    public function __construct(string $repoPath) {
        $this->repoPath = $repoPath;
    }

    public function runCommand(string $command): string {
        $fullCommand = sprintf('cd %s && git %s', escapeshellarg($this->repoPath), $command);
        $output = shell_exec($fullCommand);
        if (!is_string($output)) {
            throw new RuntimeException("Git command failed: {$command}");
        }

        return trim($output);
    }
}
