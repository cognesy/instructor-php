<?php declare(strict_types=1);

namespace Cognesy\Doctor\Freeze\Execution;

interface CommandExecutorInterface
{
    public function execute(array $commandArray, string $commandString): ExecutionResult;
}