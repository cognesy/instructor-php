<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Common\Execution;

use Cognesy\AgentCtrl\Common\Contract\CommandExecutor;
use Cognesy\Sandbox\Enums\SandboxDriver;
use Cognesy\Sandbox\Value\CommandSpec;
use Cognesy\AgentCtrl\Enum\AgentType;
use Cognesy\AgentCtrl\Event\ExecutionAttempted;
use Cognesy\AgentCtrl\Event\ProcessExecutionCompleted;
use Cognesy\AgentCtrl\Event\SandboxInitialized;
use Cognesy\AgentCtrl\Event\SandboxPolicyConfigured;
use Cognesy\AgentCtrl\Event\SandboxReady;
use Cognesy\AgentCtrl\Event\AgentEvent;
use Cognesy\AgentCtrl\Telemetry\AgentCtrlEventTelemetry;
use Cognesy\AgentCtrl\ValueObject\AgentCtrlExecutionId;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Sandbox\Contracts\CanExecuteCommand;
use Cognesy\Sandbox\Data\ExecResult;
use Cognesy\Sandbox\Sandbox;
use Throwable;

/**
 * Executes CLI commands inside instructor-php Sandbox with streaming support.
 * Generic executor that works with any CLI-based code agent.
 */
final class SandboxCommandExecutor implements CommandExecutor
{
    private CanExecuteCommand $sandbox;
    private ExecutionPolicy $policy;
    private AgentType $agentType;

    public function __construct(
        ExecutionPolicy $policy,
        private AgentCtrlExecutionId $executionId,
        SandboxDriver $driver = SandboxDriver::Host,
        int $timeout = ExecutionPolicy::DEFAULT_TIMEOUT_SECONDS,
        private ?CanHandleEvents $events = null,
        AgentType $agentType = AgentType::ClaudeCode,
    ) {
        $this->agentType = $agentType;
        $setupStart = microtime(true);

        // Configure policy with timing
        $policyStart = microtime(true);
        $this->policy = $timeout !== ExecutionPolicy::DEFAULT_TIMEOUT_SECONDS
            ? $this->withTimeout($policy, $timeout)
            : $policy;
        $effectiveTimeout = $this->policy->timeoutSeconds();
        $policyDuration = (microtime(true) - $policyStart) * 1000;
        $this->dispatch(new SandboxPolicyConfigured(
            $this->agentType,
            $this->executionId,
            $driver->value,
            $effectiveTimeout,
            true, // networkEnabled - could be extracted from policy
            $policyDuration
        ));

        // Initialize sandbox with timing
        $initStart = microtime(true);
        $this->sandbox = $this->makeSandbox($this->policy, $driver);
        $initDuration = (microtime(true) - $initStart) * 1000;
        $this->dispatch(new SandboxInitialized($this->agentType, $this->executionId, $driver->value, $initDuration));

        // Emit ready event
        $totalSetupDuration = (microtime(true) - $setupStart) * 1000;
        $this->dispatch(new SandboxReady($this->agentType, $this->executionId, $driver->value, $totalSetupDuration));
    }

    private function dispatch(object $event): void
    {
        $enriched = match (true) {
            $event instanceof AgentEvent => AgentCtrlEventTelemetry::attach($event),
            default => $event,
        };

        $this->events?->dispatch($enriched);
    }

    /**
     * Create default executor (Host sandbox).
     */
    public static function default(
        AgentCtrlExecutionId $executionId,
        ?CanHandleEvents $events = null,
        AgentType $agentType = AgentType::ClaudeCode,
    ): self {
        return new self(
            ExecutionPolicy::default(),
            $executionId,
            SandboxDriver::Host,
            ExecutionPolicy::DEFAULT_TIMEOUT_SECONDS,
            $events,
            $agentType,
        );
    }

    /**
     * Create executor configured for Claude Code CLI.
     */
    public static function forClaudeCode(
        AgentCtrlExecutionId $executionId,
        SandboxDriver $driver = SandboxDriver::Host,
        int $timeout = ExecutionPolicy::DEFAULT_TIMEOUT_SECONDS,
        ?CanHandleEvents $events = null,
    ): self {
        return new self(ExecutionPolicy::forClaudeCode(), $executionId, $driver, $timeout, $events, AgentType::ClaudeCode);
    }

    /**
     * Create executor configured for OpenAI Codex CLI.
     */
    public static function forCodex(
        AgentCtrlExecutionId $executionId,
        SandboxDriver $driver = SandboxDriver::Host,
        int $timeout = ExecutionPolicy::DEFAULT_TIMEOUT_SECONDS,
        ?CanHandleEvents $events = null,
    ): self {
        return new self(ExecutionPolicy::forCodex(), $executionId, $driver, $timeout, $events, AgentType::Codex);
    }

    /**
     * Create executor configured for OpenCode CLI.
     */
    public static function forOpenCode(
        AgentCtrlExecutionId $executionId,
        SandboxDriver $driver = SandboxDriver::Host,
        int $timeout = ExecutionPolicy::DEFAULT_TIMEOUT_SECONDS,
        ?CanHandleEvents $events = null,
    ): self {
        return new self(ExecutionPolicy::forOpenCode(), $executionId, $driver, $timeout, $events, AgentType::OpenCode);
    }

    /**
     * Create executor configured for Pi CLI.
     */
    public static function forPi(
        AgentCtrlExecutionId $executionId,
        SandboxDriver $driver = SandboxDriver::Host,
        int $timeout = ExecutionPolicy::DEFAULT_TIMEOUT_SECONDS,
        ?CanHandleEvents $events = null,
    ): self {
        return new self(ExecutionPolicy::forPi(), $executionId, $driver, $timeout, $events, AgentType::Pi);
    }

    /**
     * Create executor configured for Gemini CLI.
     */
    public static function forGemini(
        AgentCtrlExecutionId $executionId,
        SandboxDriver $driver = SandboxDriver::Host,
        int $timeout = ExecutionPolicy::DEFAULT_TIMEOUT_SECONDS,
        ?CanHandleEvents $events = null,
    ): self {
        return new self(ExecutionPolicy::forGemini(), $executionId, $driver, $timeout, $events, AgentType::Gemini);
    }

    /**
     * Apply custom timeout to an existing policy.
     */
    private function withTimeout(ExecutionPolicy $policy, int $timeout): ExecutionPolicy {
        return $policy->withTimeout($timeout);
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
        $executionStart = microtime(true);
        $attemptStart = microtime(true);
        $attempt = 1;

        try {
            $result = $this->sandbox->execute(
                $command->argv()->toArray(),
                $command->stdin(),
                $onOutput
            );

            $attemptDuration = (microtime(true) - $attemptStart) * 1000;
            $this->dispatch(new ExecutionAttempted(
                $this->agentType,
                $this->executionId,
                $attempt,
                $attemptDuration
            ));

            $totalDuration = (microtime(true) - $executionStart) * 1000;
            $this->dispatch(new ProcessExecutionCompleted(
                $this->agentType,
                $this->executionId,
                $attempt,
                $totalDuration,
                $attempt
            ));

            return $result;
        } catch (Throwable $error) {
            $attemptDuration = (microtime(true) - $attemptStart) * 1000;
            $this->dispatch(new ExecutionAttempted(
                $this->agentType,
                $this->executionId,
                $attempt,
                $attemptDuration,
                $error->getMessage()
            ));
            throw $error;
        }
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

}
