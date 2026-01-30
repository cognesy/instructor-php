<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Guards;

use Cognesy\Agents\AgentHooks\Enums\HookType;
use Cognesy\Agents\AgentHooks\Guards\ExecutionTimeLimitHook;
use Cognesy\Agents\AgentHooks\Guards\StepsLimitHook;
use Cognesy\Agents\AgentHooks\Guards\TokenUsageLimitHook;
use Cognesy\Agents\AgentHooks\Stack\HookStack;
use Cognesy\Agents\Core\Continuation\Enums\ContinuationDecision;
use Cognesy\Agents\Core\Continuation\Enums\StopReason;
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
test('StepsLimitHook writes AllowStop when under limit', function () {
    $hook = new StepsLimitHook(
        maxSteps: 10,
        stepCounter: fn(AgentState $state) => $state->stepCount(),
    );

    $state = createTestState();

    $result = $hook->process($state, HookType::BeforeStep);

    $evaluations = $result->evaluations();

    expect($evaluations)->toHaveCount(1);
    // Guard hooks use AllowStop (not AllowContinuation) when limits not exceeded.
    // This ensures guards don't drive continuation when there's no work to do.
    expect($evaluations[0]->decision)->toBe(ContinuationDecision::AllowStop);
    expect($evaluations[0]->criterionClass)->toBe(StepsLimitHook::class);
});

test('StepsLimitHook writes ForbidContinuation when limit reached', function () {
    $hook = new StepsLimitHook(
        maxSteps: 5,
        stepCounter: fn(AgentState $state) => 5, // At limit
    );

    $state = createTestState();

    $result = $hook->process($state, HookType::BeforeStep);

    $evaluations = $result->evaluations();

    expect($evaluations)->toHaveCount(1);
    expect($evaluations[0]->decision)->toBe(ContinuationDecision::ForbidContinuation);
    expect($evaluations[0]->stopReason)->toBe(StopReason::StepsLimitReached);
});

test('StepsLimitHook appliesTo only BeforeStep', function () {
    $hook = new StepsLimitHook(
        maxSteps: 10,
        stepCounter: fn(AgentState $state) => 0,
    );

    expect($hook->appliesTo())->toBe([HookType::BeforeStep]);
});

// TokenUsageLimitHook tests
test('TokenUsageLimitHook writes AllowStop when under limit', function () {
    $hook = new TokenUsageLimitHook(
        maxTotalTokens: 10000,
    );

    $state = createTestState();

    $result = $hook->process($state, HookType::BeforeStep);

    $evaluations = $result->evaluations();

    expect($evaluations)->toHaveCount(1);
    // Guard hooks use AllowStop (not AllowContinuation) when limits not exceeded.
    expect($evaluations[0]->decision)->toBe(ContinuationDecision::AllowStop);
    expect($evaluations[0]->criterionClass)->toBe(TokenUsageLimitHook::class);
});

// ExecutionTimeLimitHook tests
test('ExecutionTimeLimitHook writes AllowStop when under limit', function () {
    $hook = new ExecutionTimeLimitHook(maxSeconds: 60);
    $hook->executionStarted(new DateTimeImmutable('-30 seconds'));

    $state = createTestState();

    $result = $hook->process($state, HookType::BeforeStep);

    $evaluations = $result->evaluations();

    expect($evaluations)->toHaveCount(1);
    // Guard hooks use AllowStop (not AllowContinuation) when limits not exceeded.
    expect($evaluations[0]->decision)->toBe(ContinuationDecision::AllowStop);
    expect($evaluations[0]->criterionClass)->toBe(ExecutionTimeLimitHook::class);
});

test('ExecutionTimeLimitHook writes ForbidContinuation when time limit exceeded', function () {
    $hook = new ExecutionTimeLimitHook(maxSeconds: 60);
    $hook->executionStarted(new DateTimeImmutable('-120 seconds')); // 2 minutes ago

    $state = createTestState();

    $result = $hook->process($state, HookType::BeforeStep);

    $evaluations = $result->evaluations();

    expect($evaluations)->toHaveCount(1);
    expect($evaluations[0]->decision)->toBe(ContinuationDecision::ForbidContinuation);
    expect($evaluations[0]->stopReason)->toBe(StopReason::TimeLimitReached);
});

test('ExecutionTimeLimitHook allows continuation when execution not started', function () {
    $hook = new ExecutionTimeLimitHook(maxSeconds: 60);
    // Don't call executionStarted()

    $state = createTestState();

    $result = $hook->process($state, HookType::BeforeStep);

    $evaluations = $result->evaluations();

    // Should be under limit (execution not started) - guards use AllowStop
    expect($evaluations[0]->decision)->toBe(ContinuationDecision::AllowStop);
});

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

    $evaluations = $result->evaluations();

    // 30 seconds elapsed, under 60 second limit - guards use AllowStop
    expect($evaluations)->toHaveCount(1);
    expect($evaluations[0]->decision)->toBe(ContinuationDecision::AllowStop);
});

test('Multiple guards write multiple evaluations', function () {
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

    $evaluations = $result->evaluations();

    expect($evaluations)->toHaveCount(2);
    // Both should be AllowStop (under limits - guards permit but don't drive continuation)
    expect($evaluations[0]->decision)->toBe(ContinuationDecision::AllowStop);
    expect($evaluations[1]->decision)->toBe(ContinuationDecision::AllowStop);
});

test('Guard with ForbidContinuation still allows other guards to run', function () {
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

    $evaluations = $result->evaluations();

    // Both evaluations should be present
    expect($evaluations)->toHaveCount(2);

    // One should be ForbidContinuation (steps), one AllowStop (tokens)
    $decisions = array_map(fn($e) => $e->decision, $evaluations);
    expect($decisions)->toContain(ContinuationDecision::ForbidContinuation);
    expect($decisions)->toContain(ContinuationDecision::AllowStop);
});
