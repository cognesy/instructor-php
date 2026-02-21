<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Common\Contract;

use Cognesy\AgentCtrl\Common\Execution\ExecutionPolicy;
use Cognesy\Sandbox\Value\CommandSpec;
use Cognesy\Sandbox\Data\ExecResult;

interface CommandExecutor
{
    public function execute(CommandSpec $command) : ExecResult;

    public function policy() : ExecutionPolicy;
}
