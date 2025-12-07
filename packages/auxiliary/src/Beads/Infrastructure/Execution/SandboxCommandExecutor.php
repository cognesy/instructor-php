<?php

declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Infrastructure\Execution;

use Cognesy\Utils\Sandbox\Contracts\CanExecuteCommand;
use Cognesy\Utils\Sandbox\Data\ExecResult;
use Cognesy\Utils\Sandbox\Sandbox;
use Throwable;

/**
 * Sandbox Command Executor
 *
 * Executes shell commands using instructor-php Sandbox with HostSandbox driver.
 * Provides timeout handling, output capping, and optional retry logic.
 */
final class SandboxCommandExecutor implements CommandExecutor
{
    private CanExecuteCommand $sandbox;

    private int $maxRetries;

    public function __construct(
        ExecutionPolicy $policy,
        int $maxRetries = 0,
    ) {
        $this->sandbox = Sandbox::host($policy->toSandboxPolicy());
        $this->maxRetries = max(0, $maxRetries);
    }

    /**
     * Create with default policy
     */
    public static function default(): self
    {
        return new self(ExecutionPolicy::forBeads());
    }

    /**
     * Execute command with optional retry
     *
     * @param  list<string>  $argv
     */
    #[\Override]
    public function execute(array $argv, ?string $stdin = null): ExecResult
    {
        $attempt = 0;
        $lastError = null;

        while ($attempt <= $this->maxRetries) {
            try {
                /** @var list<string> $argv */
                return $this->sandbox->execute($argv, $stdin);
            } catch (Throwable $e) {
                $lastError = $e;
                $attempt++;

                // Don't retry if we've exhausted retries
                if ($attempt > $this->maxRetries) {
                    break;
                }

                // Exponential backoff: 100ms, 200ms, 400ms, etc.
                $exponent = 2 ** ($attempt - 1);
                $backoffMs = 100 * $exponent;
                usleep($backoffMs * 1000);
            }
        }

        // Re-throw last error (we know it's not null because loop only exits after error)
        /** @var Throwable $lastError */
        throw $lastError;
    }

    /**
     * Get execution policy
     */
    #[\Override]
    public function policy(): ExecutionPolicy
    {
        return new ExecutionPolicy($this->sandbox->policy());
    }
}
