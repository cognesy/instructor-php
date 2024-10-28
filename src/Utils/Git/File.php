<?php

namespace Cognesy\Instructor\Utils\Git;

class File
{
    protected GitService $gitService;
    protected string $path;

    public function __construct(
        string $path,
        GitService $gitService,
    ) {
        $this->path = $path;
        $this->gitService = $gitService;
    }

    /**
     * Retrieves a list of commit hashes for a specified path in the git repository.
     *
     * @param string $path The file path to get the log for.
     * @param int $number (optional) The number of commits to retrieve. Default is 10.
     * @return array An array of Commit objects corresponding to the retrieved hashes.
     */
    public function log(int $number = 10): array {
        $log = $this->gitService->runCommand(sprintf('log -n %d --pretty=format:%%H -- %s', $number, escapeshellarg($this->path)));
        $hashes = array_filter(array_map('trim', explode("\n", $log)));
        return array_map(fn($hash) => new Commit($hash, $this->gitService), $hashes);
    }

    /**
     * Retrieves a list of file versions from a git repository.
     *
     * @param string $path The file path for which to retrieve versions.
     * @param int $number The number of versions to retrieve. Defaults to 10.
     * @return array An array of file versions, where each version is an associative array containing the hash and content.
     */
    public function versions(int $number = 10): array {
        $log = $this->gitService->runCommand(sprintf('log -n %d --pretty=format:%%H -- %s', $number, escapeshellarg($this->path)));
        $hashes = array_filter(array_map('trim', explode("\n", $log)));
        $versions = [];

        foreach ($hashes as $hash) {
            $content = $this->gitService->runCommand(sprintf('show %s:%s', escapeshellarg($hash), escapeshellarg($this->path)));
            $versions[] = [
                'hash' => $hash,
                'content' => $content,
            ];
        }

        return $versions;
    }

    /**
     * Generates a git diff for the provided file path.
     *
     * @param string $path The file path for which to generate the git diff.
     * @return string The result of the git diff command.
     */
    public function diff(): string {
        return $this->gitService->runCommand(sprintf('diff HEAD -- %s', escapeshellarg($this->path)));
    }
}