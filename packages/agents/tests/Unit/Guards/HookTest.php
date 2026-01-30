<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Guards;

use Cognesy\Agents\AgentHooks\Enums\HookType;
use Cognesy\Agents\AgentHooks\Guards\StepsLimitHook;
use Cognesy\Agents\AgentHooks\Stack\HookStack;
use Cognesy\Agents\Core\Continuation\Enums\ContinuationDecision;
use Cognesy\Agents\Core\Continuation\Enums\StopReason;
use Cognesy\Agents\Core\Data\AgentState;

function createTestStateForEvalHook(): AgentState
{
    return (new AgentState())->withNewStepExecution();
}

// StepsLimitHook tests
test('StepsLimitHook writes AllowStop when under limit', function () {
    // Guard hooks use AllowStop (not AllowContinuation) when limits are not exceeded.
    // This ensures guards don't drive continuation - they only block when limits are reached.
    $hook = new StepsLimitHook(
        maxSteps: 10,
        stepCounter: fn(AgentState $state) => $state->stepCount(),
    );

    $state = createTestStateForEvalHook();

    $result = $hook->process($state, HookType::BeforeStep);

    $evaluations = $result->evaluations();
    expect($evaluations)->toHaveCount(1);
    expect($evaluations[0]->decision)->toBe(ContinuationDecision::AllowStop);
    expect($evaluations[0]->criterionClass)->toBe(StepsLimitHook::class);
});

test('StepsLimitHook writes ForbidContinuation when limit reached', function () {
    $hook = new StepsLimitHook(
        maxSteps: 5,
        stepCounter: fn(AgentState $state) => 5, // At limit
    );

    $state = createTestStateForEvalHook();

    $result = $hook->process($state, HookType::BeforeStep);

    $evaluations = $result->evaluations();
    expect($evaluations)->toHaveCount(1);
    expect($evaluations[0]->decision)->toBe(ContinuationDecision::ForbidContinuation);
    expect($evaluations[0]->stopReason)->toBe(StopReason::StepsLimitReached);
});

test('StepsLimitHook appliesTo returns BeforeStep only', function () {
    $hook = new StepsLimitHook(
        maxSteps: 10,
        stepCounter: fn(AgentState $state) => 0,
    );

    expect($hook->appliesTo())->toBe([HookType::BeforeStep]);
});

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

    $evaluations = $result->evaluations();
    expect($evaluations)->toHaveCount(1);
    expect($evaluations[0]->decision)->toBe(ContinuationDecision::AllowStop);
});

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

    // No evaluation should be written since event type doesn't match
    $evaluations = $result->evaluations();
    expect($evaluations)->toBe([]);
});

test('HookStack can process multiple hooks and accumulate evaluations', function () {
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

    $evaluations = $result->evaluations();
    // Both hooks should have written evaluations
    expect($evaluations)->toHaveCount(2);
    expect($evaluations[0]->decision)->toBe(ContinuationDecision::AllowStop);
    expect($evaluations[1]->decision)->toBe(ContinuationDecision::ForbidContinuation);
});
