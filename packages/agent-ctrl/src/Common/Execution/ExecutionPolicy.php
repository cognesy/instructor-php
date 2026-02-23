<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Common\Execution;

use Cognesy\Sandbox\Config\ExecutionPolicy as SandboxPolicy;

/**
 * Execution Policy Configuration for CLI-based code agents.
 * Generalized to support multiple CLI tools (Claude Code, Codex, OpenCode, etc.)
 */
final readonly class ExecutionPolicy
{
    public const DEFAULT_TIMEOUT_SECONDS = 120;

    public function __construct(private SandboxPolicy $policy) {}

    /**
     * Create default policy for a CLI agent.
     *
     * CLI agents need PATH to find executables like claude, codex, opencode etc.,
     * so environment inheritance is enabled by default.
     *
     * @param string $cacheSubdir The CLI-specific cache directory (e.g., '.claude', '.codex')
     */
    public static function default(string $cacheSubdir = ''): self {
        $baseDir = getcwd() ?: '/tmp';
        $writablePaths = $cacheSubdir !== ''
            ? $baseDir . '/' . $cacheSubdir
            : $baseDir;

        $policy = SandboxPolicy::in($baseDir)
            ->withTimeout(self::DEFAULT_TIMEOUT_SECONDS)
            ->withNetwork(true)
            ->withOutputCaps(5 * 1024 * 1024, 1 * 1024 * 1024)
            ->withReadablePaths($baseDir)
            ->withWritablePaths($writablePaths)
            ->inheritEnvironment();

        return new self($policy);
    }

    /**
     * Create default policy for Claude Code CLI.
     */
    public static function forClaudeCode(): self {
        return self::default('.claude');
    }

    /**
     * Create default policy for OpenAI Codex CLI.
     */
    public static function forCodex(): self {
        return self::default('.codex');
    }

    /**
     * Create default policy for OpenCode CLI.
     */
    public static function forOpenCode(): self {
        return self::default('.opencode');
    }

    /**
     * Create custom policy with full control over all parameters.
     */
    public static function custom(
        int $timeoutSeconds,
        bool $networkEnabled,
        int $stdoutLimitBytes,
        int $stderrLimitBytes,
        string $baseDir,
        string $cacheSubdir = '',
        ?bool $inheritEnv = null,
    ) : self {
        $writablePaths = $cacheSubdir !== ''
            ? $baseDir . '/' . $cacheSubdir
            : $baseDir;

        $policy = SandboxPolicy::in($baseDir)
            ->withTimeout($timeoutSeconds)
            ->withNetwork($networkEnabled)
            ->withOutputCaps($stdoutLimitBytes, $stderrLimitBytes)
            ->withReadablePaths($baseDir)
            ->withWritablePaths($writablePaths);

        if ($inheritEnv !== null) {
            $policy = $policy->inheritEnvironment($inheritEnv);
        }

        return new self($policy);
    }

    public function withTimeout(int $timeoutSeconds): self {
        return new self($this->policy->withTimeout($timeoutSeconds));
    }

    public function timeoutSeconds(): int {
        return $this->policy->timeoutSeconds();
    }

    public function toSandboxPolicy() : SandboxPolicy {
        return $this->policy;
    }
}
