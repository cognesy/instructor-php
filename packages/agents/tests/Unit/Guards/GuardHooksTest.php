<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Guards;

use Cognesy\Agents\AgentHooks\Enums\HookType;
use Cognesy\Agents\AgentHooks\Guards\ExecutionTimeLimitHook;
use Cognesy\Agents\AgentHooks\Guards\StepsLimitHook;
use Cognesy\Agents\AgentHooks\Guards\TokenUsageLimitHook;
use Cognesy\Agents\AgentHooks\Stack\HookStack;
use Cognesy\Agents\Core\Stop\StopReason;
use Cognesy\Agents\Core\Data\AgentState;
use DateTimeImmutable;

/**
 * Helper to create an AgentState with an active execution and current execution context.
 */
function createTestState(): AgentState
{
    return (new AgentState())->withNewStepExecution();
}

// StepsLimitHook tests
test('StepsLimitHook emits no stop signal when under limit', function () {
    $hook = new StepsLimitHook(
        maxSteps: 10,
        stepCounter: fn(AgentState $state) => $state->stepCount(),
    );

    $state = createTestState();

    $result = $hook->process($state, HookType::BeforeStep);

    expect($result->pendingStopSignal())->toBeNull();
})->skip('hooks not integrated yet');

test('StepsLimitHook emits stop signal when limit reached', function () {
    $hook = new StepsLimitHook(
        maxSteps: 5,
        stepCounter: fn(AgentState $state) => 5, // At limit
    );

    $state = createTestState();

    $result = $hook->process($state, HookType::BeforeStep);

    expect($result->pendingStopSignal()?->reason)->toBe(StopReason::StepsLimitReached);
})->skip('hooks not integrated yet');

test('StepsLimitHook appliesTo only BeforeStep', function () {
    $hook = new StepsLimitHook(
        maxSteps: 10,
        stepCounter: fn(AgentState $state) => 0,
    );

    expect($hook->appliesTo())->toBe([HookType::BeforeStep]);
})->skip('hooks not integrated yet');

// TokenUsageLimitHook tests
test('TokenUsageLimitHook emits no stop signal when under limit', function () {
    $hook = new TokenUsageLimitHook(
        maxTotalTokens: 10000,
    );

    $state = createTestState();

    $result = $hook->process($state, HookType::BeforeStep);

    expect($result->pendingStopSignal())->toBeNull();
})->skip('hooks not integrated yet');

// ExecutionTimeLimitHook tests
test('ExecutionTimeLimitHook emits no stop signal when under limit', function () {
    $hook = new ExecutionTimeLimitHook(maxSeconds: 60);
    $hook->executionStarted(new DateTimeImmutable('-30 seconds'));

    $state = createTestState();

    $result = $hook->process($state, HookType::BeforeStep);

    expect($result->pendingStopSignal())->toBeNull();
})->skip('hooks not integrated yet');

test('ExecutionTimeLimitHook emits stop signal when time limit exceeded', function () {
    $hook = new ExecutionTimeLimitHook(maxSeconds: 60);
    $hook->executionStarted(new DateTimeImmutable('-120 seconds')); // 2 minutes ago

    $state = createTestState();

    $result = $hook->process($state, HookType::BeforeStep);

    expect($result->pendingStopSignal()?->reason)->toBe(StopReason::TimeLimitReached);
})->skip('hooks not integrated yet');

test('ExecutionTimeLimitHook emits no stop signal when execution not started', function () {
    $hook = new ExecutionTimeLimitHook(maxSeconds: 60);
    // Don't call executionStarted()

    $state = createTestState();

    $result = $hook->process($state, HookType::BeforeStep);

    expect($result->pendingStopSignal())->toBeNull();
})->skip('hooks not integrated yet');

// HookStack integration tests
test('HookStack notifies guards of execution start', function () {
    $hook = new ExecutionTimeLimitHook(maxSeconds: 60);

    $stack = new HookStack();
    $stack = $stack->with($hook, priority: 100);

    // Notify execution started (30 seconds ago)
    $executionStart = new DateTimeImmutable('-30 seconds');
    $stack->executionStarted($executionStart);

    $state = createTestState();

    $result = $stack->process($state, HookType::BeforeStep);

    // 30 seconds elapsed, under 60 second limit
    expect($result->pendingStopSignal())->toBeNull();
})->skip('hooks not integrated yet');

test('Multiple guards emit no stop signals when under limits', function () {
    $stepsHook = new StepsLimitHook(
        maxSteps: 10,
        stepCounter: fn(AgentState $state) => 5,
    );

    $tokenHook = new TokenUsageLimitHook(
        maxTotalTokens: 10000,
    );

    $stack = new HookStack();
    $stack = $stack->with($stepsHook, priority: 100);
    $stack = $stack->with($tokenHook, priority: 100);

    $state = createTestState();

    $result = $stack->process($state, HookType::BeforeStep);

    expect($result->pendingStopSignal())->toBeNull();
})->skip('hooks not integrated yet');

test('Guard stop signal still allows other guards to run', function () {
    $stepsHook = new StepsLimitHook(
        maxSteps: 3,
        stepCounter: fn(AgentState $state) => 5, // Over limit!
    );

    $tokenHook = new TokenUsageLimitHook(
        maxTotalTokens: 10000,
    );

    $stack = new HookStack();
    $stack = $stack->with($stepsHook, priority: 100);
    $stack = $stack->with($tokenHook, priority: 100);

    $state = createTestState();

    $result = $stack->process($state, HookType::BeforeStep);

    expect($result->pendingStopSignal()?->reason)->toBe(StopReason::StepsLimitReached);
})->skip('hooks not integrated yet');
