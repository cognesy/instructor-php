<?php

declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Infrastructure\Client;

use Cognesy\Auxiliary\Beads\Infrastructure\Execution\CommandExecutor;
use RuntimeException;

/**
 * Bv CLI Client
 *
 * Thin wrapper around bv (beads viewer) CLI robot commands for graph analysis.
 * Provides access to dependency insights, execution planning, and priority recommendations.
 */
final readonly class BvClient
{
    private string $bvBinary;

    public function __construct(
        private CommandExecutor $executor,
        ?string $bvBinary = null,
    ) {
        $this->bvBinary = $bvBinary ?? $this->findBvBinary();
    }

    /**
     * Execute bv command with automatic JSON parsing
     *
     * @param  list<string>  $args  Command arguments (without 'bv' prefix)
     * @return array<mixed> Parsed JSON response
     *
     * @throws RuntimeException On execution or parse failure
     */
    private function execute(array $args): array
    {
        // Build argv: ['bv', ...args]
        $argv = [$this->bvBinary, ...$args];

        $result = $this->executor->execute($argv);

        if (! $result->success()) {
            throw new RuntimeException(
                "bv command failed: {$result->stderr()}",
                $result->exitCode()
            );
        }

        $stdout = trim($result->stdout());
        if ($stdout === '') {
            return [];
        }

        $decoded = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($decoded)) {
            throw new RuntimeException('bv command did not return JSON array');
        }

        return $decoded;
    }

    /**
     * Get dependency insights (graph metrics)
     *
     * Returns PageRank, betweenness centrality, HITS, critical path, and cycles.
     *
     * @return array<mixed>
     */
    public function getInsights(): array
    {
        return $this->execute(['--robot-insights']);
    }

    /**
     * Get execution plan (parallel tracks)
     *
     * Returns execution plan showing which tasks can be worked on in parallel.
     *
     * @return array<mixed>
     */
    public function getExecutionPlan(): array
    {
        return $this->execute(['--robot-plan']);
    }

    /**
     * Get priority recommendations
     *
     * Returns AI-powered priority recommendations with reasoning.
     *
     * @return array<mixed>
     */
    public function getPriorityRecommendations(): array
    {
        return $this->execute(['--robot-priority']);
    }

    /**
     * Get task dependency diff since commit/date
     *
     * @return array<mixed>
     */
    public function getDiff(string $since): array
    {
        return $this->execute(['--robot-diff', '--diff-since='.$since]);
    }

    /**
     * List available recipes (filters/views)
     *
     * @return array<mixed>
     */
    public function listRecipes(): array
    {
        return $this->execute(['--robot-recipes']);
    }

    /**
     * Find bv binary on system
     */
    private function findBvBinary(): string
    {
        // Check common locations
        $locations = [
            '/usr/local/bin/bv',
            '/usr/bin/bv',
            '/opt/homebrew/bin/bv',
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
                $candidate = $dir.'/bv';
                if (file_exists($candidate) && is_executable($candidate)) {
                    return $candidate;
                }
            }
        }

        // Fallback: assume bv is in PATH and let executor handle errors
        return 'bv';
    }
}
