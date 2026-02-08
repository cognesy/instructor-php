<?php declare(strict_types=1);

namespace Cognesy\Sandbox\Contracts;

use Cognesy\Sandbox\Config\ExecutionPolicy;
use Cognesy\Sandbox\Data\ExecResult;

interface CanExecuteCommand
{
    public function policy(): ExecutionPolicy;

    /**
     * Execute arbitrary command (argv form) in the driver's environment.
     * Stdout/stderr are bounded per policy. Returns ExecResult on success and throws on failure.
     *
     * @param list<string> $argv
     * @param string|null $stdin Optional input to send to command
     * @param callable(string $type, string $chunk): void|null $onOutput Optional callback for streaming output
     *        - $type: 'out' for stdout, 'err' for stderr
     *        - $chunk: Raw output bytes
     * @throws \Throwable On setup or execution failures
     */
    public function execute(array $argv, ?string $stdin = null, ?callable $onOutput = null): ExecResult;
}
