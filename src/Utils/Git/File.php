<?php

namespace Cognesy\Instructor\Utils\Git;

class File
{
    protected GitService $gitService;

    public function __construct(GitService $gitService)
    {
        $this->gitService = $gitService;
    }

    public function log(string $path, int $number = 10): array
    {
        $log = $this->gitService->runCommand(sprintf('log -n %d --pretty=format:%%H -- %s', $number, escapeshellarg($path)));
        $hashes = array_filter(array_map('trim', explode("\n", $log)));
        return array_map(fn($hash) => new Commit($hash, $this->gitService), $hashes);
    }

    public function diff(string $path): string
    {
        return $this->gitService->runCommand(sprintf('diff HEAD -- %s', escapeshellarg($path)));
    }

    public function versions(string $path, int $number = 10): array
    {
        $log = $this->gitService->runCommand(sprintf('log -n %d --pretty=format:%%H -- %s', $number, escapeshellarg($path)));
        $hashes = array_filter(array_map('trim', explode("\n", $log)));
        $versions = [];

        foreach ($hashes as $hash) {
            $content = $this->gitService->runCommand(sprintf('show %s:%s', escapeshellarg($hash), escapeshellarg($path)));
            $versions[] = [
                'hash' => $hash,
                'content' => $content,
            ];
        }

        return $versions;
    }
}