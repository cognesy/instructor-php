<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Common\Execution;

use Cognesy\AgentCtrl\Common\Contract\CommandExecutor;
use Cognesy\AgentCtrl\Common\Enum\SandboxDriver;
use Cognesy\AgentCtrl\Common\Value\CommandSpec;
use Cognesy\Utils\Sandbox\Contracts\CanExecuteCommand;
use Cognesy\Utils\Sandbox\Contracts\CanStreamCommand;
use Cognesy\Utils\Sandbox\Data\ExecResult;
use Cognesy\Utils\Sandbox\Sandbox;
use Throwable;

/**
 * Executes CLI commands inside instructor-php Sandbox with optional retries and streaming support.
 * Generic executor that works with any CLI-based code agent.
 */
final class SandboxCommandExecutor implements CommandExecutor
{
    private CanStreamCommand $sandbox;
    private ExecutionPolicy $policy;
    private int $maxRetries;

    public function __construct(
        ExecutionPolicy $policy,
        SandboxDriver $driver = SandboxDriver::Host,
        int $maxRetries = 0,
        int $timeout = 120,
    ) {
        $this->policy = $timeout !== 120
            ? $this->withTimeout($policy, $timeout)
            : $policy;
        $this->sandbox = $this->makeSandbox($this->policy, $driver);
        $this->maxRetries = max(0, $maxRetries);
    }

    /**
     * Create default executor (Host sandbox, no retries).
     */
    public static function default(): self {
        return new self(ExecutionPolicy::default(), SandboxDriver::Host, 0, 120);
    }

    /**
     * Create executor configured for Claude Code CLI.
     */
    public static function forClaudeCode(
        SandboxDriver $driver = SandboxDriver::Host,
        int $maxRetries = 0,
        int $timeout = 120,
    ): self {
        return new self(ExecutionPolicy::forClaudeCode(), $driver, $maxRetries, $timeout);
    }

    /**
     * Create executor configured for OpenAI Codex CLI.
     */
    public static function forCodex(
        SandboxDriver $driver = SandboxDriver::Host,
        int $maxRetries = 0,
        int $timeout = 120,
    ): self {
        return new self(ExecutionPolicy::forCodex(), $driver, $maxRetries, $timeout);
    }

    /**
     * Create executor configured for OpenCode CLI.
     */
    public static function forOpenCode(
        SandboxDriver $driver = SandboxDriver::Host,
        int $maxRetries = 0,
        int $timeout = 120,
    ): self {
        return new self(ExecutionPolicy::forOpenCode(), $driver, $maxRetries, $timeout);
    }

    /**
     * Apply custom timeout to an existing policy.
     */
    private function withTimeout(ExecutionPolicy $policy, int $timeout): ExecutionPolicy {
        $baseDir = getcwd() ?: '/tmp';
        return ExecutionPolicy::custom(
            timeoutSeconds: $timeout,
            networkEnabled: true,
            stdoutLimitBytes: 5 * 1024 * 1024,
            stderrLimitBytes: 1 * 1024 * 1024,
            baseDir: $baseDir,
        );
    }

    #[\Override]
    public function execute(CommandSpec $command) : ExecResult {
        return $this->executeStreaming($command, null);
    }

    /**
     * Execute command with real-time output streaming.
     *
     * @param callable(string $type, string $chunk): void|null $onOutput
     */
    public function executeStreaming(CommandSpec $command, ?callable $onOutput) : ExecResult {
        $attempt = 0;
        $lastError = null;

        while ($attempt <= $this->maxRetries) {
            try {
                return $this->sandbox->executeStreaming(
                    $command->argv()->toArray(),
                    $onOutput,
                    $command->stdin()
                );
            } catch (Throwable $error) {
                $lastError = $error;
                $attempt++;
                if ($attempt > $this->maxRetries) {
                    break;
                }
                $this->backoff($attempt);
            }
        }

        /** @var Throwable $lastError */
        throw $lastError;
    }

    #[\Override]
    public function policy() : ExecutionPolicy {
        return $this->policy;
    }

    private function makeSandbox(ExecutionPolicy $policy, SandboxDriver $driver) : CanExecuteCommand {
        $sandboxPolicy = $policy->toSandboxPolicy();
        return match ($driver) {
            SandboxDriver::Host => Sandbox::host($sandboxPolicy),
            SandboxDriver::Docker => Sandbox::docker($sandboxPolicy),
            SandboxDriver::Podman => Sandbox::podman($sandboxPolicy),
            SandboxDriver::Firejail => Sandbox::firejail($sandboxPolicy),
            SandboxDriver::Bubblewrap => Sandbox::bubblewrap($sandboxPolicy),
        };
    }

    private function backoff(int $attempt) : void {
        $exponent = 2 ** ($attempt - 1);
        $delayMs = 100 * $exponent;
        usleep($delayMs * 1000);
    }
}
