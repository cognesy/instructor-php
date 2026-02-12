<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Common\Execution;

use Cognesy\AgentCtrl\Common\Contract\CommandExecutor;
use Cognesy\AgentCtrl\Common\Enum\SandboxDriver;
use Cognesy\AgentCtrl\Common\Value\CommandSpec;
use Cognesy\AgentCtrl\Enum\AgentType;
use Cognesy\AgentCtrl\Event\ExecutionAttempted;
use Cognesy\AgentCtrl\Event\ProcessExecutionCompleted;
use Cognesy\AgentCtrl\Event\SandboxInitialized;
use Cognesy\AgentCtrl\Event\SandboxPolicyConfigured;
use Cognesy\AgentCtrl\Event\SandboxReady;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Sandbox\Contracts\CanExecuteCommand;
use Cognesy\Sandbox\Data\ExecResult;
use Cognesy\Sandbox\Sandbox;
use Throwable;

/**
 * Executes CLI commands inside instructor-php Sandbox with optional retries and streaming support.
 * Generic executor that works with any CLI-based code agent.
 */
final class SandboxCommandExecutor implements CommandExecutor
{
    private CanExecuteCommand $sandbox;
    private ExecutionPolicy $policy;
    private int $maxRetries;
    private SandboxDriver $driver;
    private AgentType $agentType;

    public function __construct(
        ExecutionPolicy $policy,
        SandboxDriver $driver = SandboxDriver::Host,
        int $maxRetries = 0,
        int $timeout = 120,
        private ?CanHandleEvents $events = null,
        AgentType $agentType = AgentType::ClaudeCode,
    ) {
        $this->agentType = $agentType;
        $setupStart = microtime(true);

        // Configure policy with timing
        $policyStart = microtime(true);
        $this->policy = $timeout !== 120
            ? $this->withTimeout($policy, $timeout)
            : $policy;
        $this->driver = $driver;
        $policyDuration = (microtime(true) - $policyStart) * 1000;
        $this->dispatch(new SandboxPolicyConfigured(
            $this->agentType,
            $driver->value,
            $timeout,
            true, // networkEnabled - could be extracted from policy
            $policyDuration
        ));

        // Initialize sandbox with timing
        $initStart = microtime(true);
        $this->sandbox = $this->makeSandbox($this->policy, $driver);
        $initDuration = (microtime(true) - $initStart) * 1000;
        $this->dispatch(new SandboxInitialized($this->agentType, $driver->value, $initDuration));

        $this->maxRetries = max(0, $maxRetries);

        // Emit ready event
        $totalSetupDuration = (microtime(true) - $setupStart) * 1000;
        $this->dispatch(new SandboxReady($this->agentType, $driver->value, $totalSetupDuration));
    }

    private function dispatch(object $event): void
    {
        $this->events?->dispatch($event);
    }

    /**
     * Create default executor (Host sandbox, no retries).
     */
    public static function default(?CanHandleEvents $events = null, AgentType $agentType = AgentType::ClaudeCode): self {
        return new self(ExecutionPolicy::default(), SandboxDriver::Host, 0, 120, $events, $agentType);
    }

    /**
     * Create executor configured for Claude Code CLI.
     */
    public static function forClaudeCode(
        SandboxDriver $driver = SandboxDriver::Host,
        int $maxRetries = 0,
        int $timeout = 120,
        ?CanHandleEvents $events = null,
    ): self {
        return new self(ExecutionPolicy::forClaudeCode(), $driver, $maxRetries, $timeout, $events, AgentType::ClaudeCode);
    }

    /**
     * Create executor configured for OpenAI Codex CLI.
     */
    public static function forCodex(
        SandboxDriver $driver = SandboxDriver::Host,
        int $maxRetries = 0,
        int $timeout = 120,
        ?CanHandleEvents $events = null,
    ): self {
        return new self(ExecutionPolicy::forCodex(), $driver, $maxRetries, $timeout, $events, AgentType::Codex);
    }

    /**
     * Create executor configured for OpenCode CLI.
     */
    public static function forOpenCode(
        SandboxDriver $driver = SandboxDriver::Host,
        int $maxRetries = 0,
        int $timeout = 120,
        ?CanHandleEvents $events = null,
    ): self {
        return new self(ExecutionPolicy::forOpenCode(), $driver, $maxRetries, $timeout, $events, AgentType::OpenCode);
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
        $executionStart = microtime(true);

        while ($attempt <= $this->maxRetries) {
            $attemptStart = microtime(true);
            $attempt++;

            try {
                $result = $this->sandbox->execute(
                    $command->argv()->toArray(),
                    $command->stdin(),
                    $onOutput
                );

                $attemptDuration = (microtime(true) - $attemptStart) * 1000;
                $this->dispatch(new ExecutionAttempted(
                    $this->agentType,
                    $attempt,
                    false, // willRetry = false (success)
                    $attemptDuration
                ));

                $totalDuration = (microtime(true) - $executionStart) * 1000;
                $this->dispatch(new ProcessExecutionCompleted(
                    $this->agentType,
                    $attempt,
                    $totalDuration,
                    $attempt // successAttempt
                ));

                return $result;
            } catch (Throwable $error) {
                $lastError = $error;
                $attemptDuration = (microtime(true) - $attemptStart) * 1000;
                $willRetry = $attempt <= $this->maxRetries;

                $this->dispatch(new ExecutionAttempted(
                    $this->agentType,
                    $attempt,
                    $willRetry,
                    $attemptDuration,
                    $error->getMessage()
                ));

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
