<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Common\Contract;

use Cognesy\AgentCtrl\Common\Execution\ExecutionPolicy;
use Cognesy\AgentCtrl\Common\Value\CommandSpec;
use Cognesy\Utils\Sandbox\Data\ExecResult;

interface CommandExecutor
{
    public function execute(CommandSpec $command) : ExecResult;

    public function policy() : ExecutionPolicy;
}
