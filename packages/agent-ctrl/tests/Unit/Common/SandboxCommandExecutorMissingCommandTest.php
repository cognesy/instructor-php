<?php declare(strict_types=1);

use Cognesy\AgentCtrl\Common\Execution\SandboxCommandExecutor;
use Cognesy\AgentCtrl\ValueObject\AgentCtrlExecutionId;
use Cognesy\Sandbox\Value\Argv;
use Cognesy\Sandbox\Value\CommandSpec;

it('reports non-zero exit code for missing commands', function () {
    $executor = SandboxCommandExecutor::default(AgentCtrlExecutionId::fresh());
    $spec = new CommandSpec(Argv::of(['__missing_command__']));

    $result = $executor->execute($spec);

    expect($result->exitCode())->not->toBe(0);
});
