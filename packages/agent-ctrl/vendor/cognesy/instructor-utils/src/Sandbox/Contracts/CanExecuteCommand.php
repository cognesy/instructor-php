<?php declare(strict_types=1);

namespace Cognesy\Utils\Sandbox\Contracts;

use Cognesy\Utils\Sandbox\Config\ExecutionPolicy;
use Cognesy\Utils\Sandbox\Data\ExecResult;

interface CanExecuteCommand
{
    public function policy(): ExecutionPolicy;

    /**
     * Execute arbitrary command (argv form) in the driver's environment.
     * Stdout/stderr are bounded per policy. Returns ExecResult on success and throws on failure.
     *
     * @param list<string> $argv
     * @throws \Throwable On setup or execution failures
     */
    public function execute(array $argv, ?string $stdin = null): ExecResult;
}
