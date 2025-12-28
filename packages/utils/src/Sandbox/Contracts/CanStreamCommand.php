<?php declare(strict_types=1);

namespace Cognesy\Utils\Sandbox\Contracts;

use Cognesy\Utils\Sandbox\Data\ExecResult;

/**
 * Capability interface for command executors that support real-time output streaming.
 * Extends CanExecuteCommand to provide streaming in addition to blocking execution.
 */
interface CanStreamCommand extends CanExecuteCommand
{
    /**
     * Execute command with real-time output streaming.
     *
     * The callback receives output chunks as they arrive from stdout/stderr.
     * Useful for long-running commands where you need to process output incrementally
     * or provide user feedback before the command completes.
     *
     * @param list<string> $argv Command and arguments
     * @param callable(string $type, string $chunk): void $onOutput Called for each output chunk
     *        - $type: 'out' for stdout, 'err' for stderr
     *        - $chunk: Raw output bytes
     * @param string|null $stdin Optional input to send to command
     * @return ExecResult Final result after command completes (with full buffered output)
     * @throws \Throwable On setup or execution failures
     */
    public function executeStreaming(array $argv, callable $onOutput, ?string $stdin = null): ExecResult;
}
