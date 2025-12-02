<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\ClaudeCodeCli\Infrastructure\Execution;

use Cognesy\Utils\Sandbox\Config\ExecutionPolicy as SandboxPolicy;

/**
 * Execution Policy Configuration for Claude CLI
 */
final readonly class ExecutionPolicy
{
    public function __construct(private SandboxPolicy $policy) {}

    public static function default(): self {
        $baseDir = getcwd() ?: '/tmp';
        $policy = SandboxPolicy::in($baseDir)
            ->withTimeout(60)
            ->withNetwork(true)
            ->withOutputCaps(5 * 1024 * 1024, 1 * 1024 * 1024)
            ->withReadablePaths($baseDir)
            ->withWritablePaths($baseDir . '/.claude');
        return new self($policy);
    }

    public static function custom(
        int $timeoutSeconds,
        bool $networkEnabled,
        int $stdoutLimitBytes,
        int $stderrLimitBytes,
        string $baseDir,
        ?bool $inheritEnv = null,
    ) : self {
        $policy = SandboxPolicy::in($baseDir)
            ->withTimeout($timeoutSeconds)
            ->withNetwork($networkEnabled)
            ->withOutputCaps($stdoutLimitBytes, $stderrLimitBytes)
            ->withReadablePaths($baseDir)
            ->withWritablePaths($baseDir . '/.claude');
        if ($inheritEnv === true) {
            $policy = $policy->inheritEnvironment();
        }
        return new self($policy);
    }

    public function toSandboxPolicy() : SandboxPolicy {
        return $this->policy;
    }
}
