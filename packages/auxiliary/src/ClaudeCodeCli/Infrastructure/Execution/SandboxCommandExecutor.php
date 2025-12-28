<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\ClaudeCodeCli\Infrastructure\Execution;

use Cognesy\Auxiliary\ClaudeCodeCli\Domain\Value\CommandSpec;
use Cognesy\Utils\Sandbox\Contracts\CanExecuteCommand;
use Cognesy\Utils\Sandbox\Contracts\CanStreamCommand;
use Cognesy\Utils\Sandbox\Data\ExecResult;
use Cognesy\Utils\Sandbox\Sandbox;
use Throwable;

/**
 * Executes claude CLI commands inside instructor-php Sandbox with optional retries and streaming support.
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
    ) {
        $this->policy = $policy;
        $this->sandbox = $this->makeSandbox($policy, $driver);
        $this->maxRetries = max(0, $maxRetries);
    }

    public static function default(): self {
        return new self(ExecutionPolicy::default(), SandboxDriver::Host, 0);
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
