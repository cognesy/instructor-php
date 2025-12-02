<?php

declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Infrastructure\Execution;

use Cognesy\Utils\Sandbox\Data\ExecResult;

/**
 * Command Executor Interface
 *
 * Abstract interface for executing shell commands with safety and monitoring.
 */
interface CommandExecutor
{
    /**
     * Execute command with arguments
     *
     * @param  list<string>  $argv  Command and arguments in array form (no shell)
     * @param  string|null  $stdin  Optional stdin input
     * @return ExecResult Execution result with stdout, stderr, exit code
     *
     * @throws \Throwable On execution failure
     */
    public function execute(array $argv, ?string $stdin = null): ExecResult;

    /**
     * Get execution policy
     */
    public function policy(): ExecutionPolicy;
}
