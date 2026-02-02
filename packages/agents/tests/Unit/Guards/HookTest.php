<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Guards;

use Cognesy\Agents\AgentHooks\Enums\HookType;
use Cognesy\Agents\AgentHooks\Guards\StepsLimitHook;
use Cognesy\Agents\AgentHooks\Stack\HookStack;
use Cognesy\Agents\Core\Stop\StopReason;
use Cognesy\Agents\Core\Data\AgentState;

function createTestStateForEvalHook(): AgentState
{
    return (new AgentState())->withNewStepExecution();
}

// StepsLimitHook tests
test('StepsLimitHook emits no stop signal when under limit', function () {
    $hook = new StepsLimitHook(
        maxSteps: 10,
        stepCounter: fn(AgentState $state) => $state->stepCount(),
    );

    $state = createTestStateForEvalHook();

    $result = $hook->process($state, HookType::BeforeStep);

    expect($result->pendingStopSignal())->toBeNull();
})->skip('hooks not integrated yet');

test('StepsLimitHook emits stop signal when limit reached', function () {
    $hook = new StepsLimitHook(
        maxSteps: 5,
        stepCounter: fn(AgentState $state) => 5, // At limit
    );

    $state = createTestStateForEvalHook();

    $result = $hook->process($state, HookType::BeforeStep);

    expect($result->pendingStopSignal()?->reason)->toBe(StopReason::StepsLimitReached);
})->skip('hooks not integrated yet');

test('StepsLimitHook appliesTo returns BeforeStep only', function () {
    $hook = new StepsLimitHook(
        maxSteps: 10,
        stepCounter: fn(AgentState $state) => 0,
    );

    expect($hook->appliesTo())->toBe([HookType::BeforeStep]);
})->skip('hooks not integrated yet');

// HookStack integration tests
test('HookStack processes hooks for matching events', function () {
    $hook = new StepsLimitHook(
        maxSteps: 10,
        stepCounter: fn(AgentState $state) => 5,
    );

    $stack = new HookStack();
    $stack = $stack->with($hook, priority: 100);

    $state = createTestStateForEvalHook();

    $result = $stack->process($state, HookType::BeforeStep);

    expect($result->pendingStopSignal())->toBeNull();
})->skip('hooks not integrated yet');

test('HookStack skips hooks for non-matching events', function () {
    $hook = new StepsLimitHook(
        maxSteps: 5,
        stepCounter: fn(AgentState $state) => 10, // Over limit
    );

    $stack = new HookStack();
    $stack = $stack->with($hook, priority: 100);

    $state = createTestStateForEvalHook();

    // Use AfterStep event - hook only applies to BeforeStep
    $result = $stack->process($state, HookType::AfterStep);

    expect($result->pendingStopSignal())->toBeNull();
})->skip('hooks not integrated yet');

test('HookStack resolves stop signal from multiple hooks', function () {
    $hook1 = new StepsLimitHook(
        maxSteps: 10,
        stepCounter: fn(AgentState $state) => 5,
    );

    $hook2 = new StepsLimitHook(
        maxSteps: 3,
        stepCounter: fn(AgentState $state) => 5, // Over limit
    );

    $stack = new HookStack();
    $stack = $stack
        ->with($hook1, priority: 100)
        ->with($hook2, priority: 50);

    $state = createTestStateForEvalHook();

    $result = $stack->process($state, HookType::BeforeStep);

    expect($result->pendingStopSignal()?->reason)->toBe(StopReason::StepsLimitReached);
})->skip('hooks not integrated yet');
