<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\ClaudeCodeCli\Infrastructure\Execution;

use Cognesy\Auxiliary\ClaudeCodeCli\Domain\Value\Argv;
use Cognesy\Auxiliary\ClaudeCodeCli\Domain\Value\CommandSpec;
use Cognesy\Utils\Sandbox\Data\ExecResult;

interface CommandExecutor
{
    public function execute(CommandSpec $command) : ExecResult;

    public function policy() : ExecutionPolicy;
}
