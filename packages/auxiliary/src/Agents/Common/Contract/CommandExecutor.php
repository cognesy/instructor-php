<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Agents\Common\Contract;

use Cognesy\Auxiliary\Agents\Common\Execution\ExecutionPolicy;
use Cognesy\Auxiliary\Agents\Common\Value\CommandSpec;
use Cognesy\Utils\Sandbox\Data\ExecResult;

interface CommandExecutor
{
    public function execute(CommandSpec $command) : ExecResult;

    public function policy() : ExecutionPolicy;
}
