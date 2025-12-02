<?php

declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Infrastructure\Execution;

use Cognesy\Utils\Sandbox\Config\ExecutionPolicy as SandboxPolicy;

/**
 * Execution Policy Configuration
 *
 * Wraps Cognesy\Utils\Sandbox ExecutionPolicy with sensible defaults for bd/bv execution.
 */
final readonly class ExecutionPolicy
{
    public function __construct(
        private SandboxPolicy $policy,
    ) {}

    /**
     * Create default policy for bd/bv execution
     *
     * Optimized for CLI tool execution:
     * - 30 second timeout (bd commands can be slow)
     * - Network enabled (bd sync needs it)
     * - Current directory as base (read .beads/)
     * - 10MB output limits (for large bd list --json)
     */
    public static function forBeads(): self
    {
        $baseDir = getcwd() ?: '/tmp';

        $policy = SandboxPolicy::in($baseDir)
            ->withTimeout(30)
            ->withNetwork(true)
            ->withOutputCaps(10 * 1024 * 1024, 1 * 1024 * 1024) // 10MB stdout, 1MB stderr
            ->withReadablePaths($baseDir)
            ->withWritablePaths($baseDir.'/.beads');

        return new self($policy);
    }

    /**
     * Create custom policy
     */
    public static function custom(
        int $timeoutSeconds = 30,
        bool $networkEnabled = true,
        int $stdoutLimitMB = 10,
        int $stderrLimitMB = 1,
    ): self {
        $baseDir = getcwd() ?: '/tmp';

        $policy = SandboxPolicy::in($baseDir)
            ->withTimeout($timeoutSeconds)
            ->withOutputCaps($stdoutLimitMB * 1024 * 1024, $stderrLimitMB * 1024 * 1024)
            ->withNetwork($networkEnabled)
            ->withReadablePaths($baseDir)
            ->withWritablePaths($baseDir.'/.beads');

        return new self($policy);
    }

    /**
     * Get underlying Sandbox policy
     */
    public function toSandboxPolicy(): SandboxPolicy
    {
        return $this->policy;
    }
}
