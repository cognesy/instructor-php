<?php declare(strict_types=1);

use Cognesy\AgentCtrl\Common\Execution\SandboxCommandExecutor;
use Cognesy\AgentCtrl\Common\Value\Argv;
use Cognesy\AgentCtrl\Common\Value\CommandSpec;

it('reports non-zero exit code for missing commands', function () {
    $executor = SandboxCommandExecutor::default();
    $spec = new CommandSpec(Argv::of(['__missing_command__']));

    $result = $executor->execute($spec);

    expect($result->exitCode())->not->toBe(0);
});
