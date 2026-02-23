<?php declare(strict_types=1);

use Cognesy\AgentCtrl\Common\Execution\ExecutionPolicy;
use Cognesy\AgentCtrl\Common\Execution\SandboxCommandExecutor;

it('uses 120s timeout in default execution policy', function () {
    $policy = ExecutionPolicy::default()->toSandboxPolicy();

    expect($policy->timeoutSeconds())->toBe(ExecutionPolicy::DEFAULT_TIMEOUT_SECONDS)
        ->and($policy->inheritEnv())->toBeTrue();
});

it('uses 120s timeout for codex executor by default', function () {
    $executor = SandboxCommandExecutor::forCodex();

    expect($executor->policy()->toSandboxPolicy()->timeoutSeconds())
        ->toBe(ExecutionPolicy::DEFAULT_TIMEOUT_SECONDS);
});

it('preserves inherited env and cache writable path when timeout is overridden', function () {
    $executor = SandboxCommandExecutor::forCodex(timeout: 30);
    $policy = $executor->policy()->toSandboxPolicy();
    $baseDir = getcwd() ?: '/tmp';

    expect($policy->timeoutSeconds())->toBe(30)
        ->and($policy->inheritEnv())->toBeTrue()
        ->and($policy->writablePaths())->toContain($baseDir . '/.codex');
});
