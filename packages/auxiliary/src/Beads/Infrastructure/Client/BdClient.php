<?php

declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Infrastructure\Client;

use Cognesy\Auxiliary\Beads\Infrastructure\Execution\CommandExecutor;
use RuntimeException;

/**
 * Bd CLI Client
 *
 * Thin wrapper around bd CLI commands with JSON parsing and error handling.
 * Delegates actual execution to CommandExecutor (which handles safety/timeouts).
 */
final readonly class BdClient
{
    private string $bdBinary;

    public function __construct(
        private CommandExecutor $executor,
        ?string $bdBinary = null,
    ) {
        $this->bdBinary = $bdBinary ?? $this->findBdBinary();
    }

    /**
     * Execute bd command with automatic JSON parsing
     *
     * @param  list<string>  $args  Command arguments (without 'bd' prefix)
     * @return array<mixed> Parsed JSON response
     *
     * @throws RuntimeException On execution or parse failure
     */
    public function execute(array $args): array
    {
        // Ensure --json flag is present
        if (! in_array('--json', $args, true)) {
            $args[] = '--json';
        }

        // Build argv: ['bd', ...args]
        $argv = [$this->bdBinary, ...$args];

        $result = $this->executor->execute($argv);

        if (! $result->success()) {
            throw new RuntimeException(
                "bd command failed: {$result->stderr()}",
                $result->exitCode()
            );
        }

        $stdout = trim($result->stdout());
        if ($stdout === '') {
            return [];
        }

        $decoded = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($decoded)) {
            throw new RuntimeException('bd command did not return JSON array');
        }

        return $decoded;
    }

    /**
     * List tasks with optional filters
     *
     * @param  array<string, mixed>  $filters
     * @return array<mixed>
     */
    public function list(array $filters = []): array
    {
        $args = ['list'];

        foreach ($filters as $key => $value) {
            $args[] = "--{$key}={$value}";
        }

        return $this->execute($args);
    }

    /**
     * Show task details
     *
     * @return array<mixed>
     */
    public function show(string $taskId): array
    {
        return $this->execute(['show', $taskId]);
    }

    /**
     * Create new task
     *
     * @param  array<string, mixed>  $data
     * @return array<mixed>
     */
    public function create(array $data): array
    {
        $args = ['create'];

        foreach ($data as $key => $value) {
            if ($value === null) {
                continue;
            }

            $args[] = "--{$key}=".(string) $value;
        }

        return $this->execute($args);
    }

    /**
     * Update task
     *
     * @param  array<string, mixed>  $data
     * @return array<mixed>
     */
    public function update(string $taskId, array $data): array
    {
        $args = ['update', $taskId];

        foreach ($data as $key => $value) {
            if ($value === null) {
                continue;
            }

            $args[] = "--{$key}=".(string) $value;
        }

        return $this->execute($args);
    }

    /**
     * Close task
     *
     * @return array<mixed>
     */
    public function close(string $taskId, string $reason): array
    {
        return $this->execute(['close', $taskId, '--reason='.$reason]);
    }

    /**
     * Add dependency
     *
     * @return array<mixed>
     */
    public function addDependency(string $blockedTaskId, string $blockerTaskId): array
    {
        return $this->execute(['dep', 'add', $blockedTaskId, $blockerTaskId]);
    }

    /**
     * List task comments
     *
     * @return array<mixed>
     */
    public function getComments(string $taskId): array
    {
        return $this->execute(['comments', $taskId]);
    }

    /**
     * Add comment to task
     *
     * @return array<mixed>
     */
    public function addComment(string $taskId, string $text): array
    {
        return $this->execute(['comments', 'add', $taskId, $text]);
    }

    /**
     * Get tasks ready to work on (no blockers)
     *
     * @return array<mixed>
     */
    public function ready(int $limit = 10): array
    {
        return $this->execute(['ready', '--limit='.$limit]);
    }

    /**
     * Get blocked tasks
     *
     * @return array<mixed>
     */
    public function blocked(): array
    {
        return $this->execute(['blocked']);
    }

    /**
     * Find bd binary on system
     *
     * @throws RuntimeException If bd binary not found
     */
    private function findBdBinary(): string
    {
        // Check common locations
        $locations = [
            '/usr/local/bin/bd',
            '/usr/bin/bd',
            '/opt/homebrew/bin/bd',
        ];

        foreach ($locations as $path) {
            if (file_exists($path) && is_executable($path)) {
                return $path;
            }
        }

        // Check PATH environment variable
        $path = getenv('PATH');
        if ($path !== false) {
            $paths = explode(PATH_SEPARATOR, $path);
            foreach ($paths as $dir) {
                $candidate = $dir.'/bd';
                if (file_exists($candidate) && is_executable($candidate)) {
                    return $candidate;
                }
            }
        }

        // Fallback: assume bd is in PATH and let executor handle errors
        return 'bd';
    }
}
