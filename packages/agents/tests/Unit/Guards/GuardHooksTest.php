<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Guards;

use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Stop\StopReason;
use Cognesy\Agents\Hooks\Collections\HookTriggers;
use Cognesy\Agents\Hooks\Collections\RegisteredHooks;
use Cognesy\Agents\Hooks\Data\HookContext;
use Cognesy\Agents\Hooks\Enums\HookTrigger;
use Cognesy\Agents\Hooks\Guards\ExecutionTimeLimitHook;
use Cognesy\Agents\Hooks\Guards\StepsLimitHook;
use Cognesy\Agents\Hooks\Guards\TokenUsageLimitHook;
use Cognesy\Agents\Hooks\Interceptors\HookStack;

// StepsLimitHook tests

it('StepsLimitHook emits no stop signal when under limit', function () {
    $hook = new StepsLimitHook(
        maxSteps: 10,
        stepCounter: fn(AgentState $state) => $state->stepCount(),
    );

    $result = $hook->handle(HookContext::beforeStep(state: AgentState::empty()));

    expect($result->state()->executionContinuation()->stopSignals()->hasAny())->toBeFalse();
});

it('StepsLimitHook emits stop signal when limit reached', function () {
    $hook = new StepsLimitHook(
        maxSteps: 5,
        stepCounter: fn() => 5,
    );

    $result = $hook->handle(HookContext::beforeStep(state: AgentState::empty()));

    $signals = $result->state()->executionContinuation()->stopSignals();
    expect($signals->hasAny())->toBeTrue();
    expect($signals->first()->reason)->toBe(StopReason::StepsLimitReached);
});

it('StepsLimitHook emits stop signal when over limit', function () {
    $hook = new StepsLimitHook(
        maxSteps: 3,
        stepCounter: fn() => 7,
    );

    $result = $hook->handle(HookContext::beforeStep(state: AgentState::empty()));

    $signals = $result->state()->executionContinuation()->stopSignals();
    expect($signals->hasAny())->toBeTrue();
    expect($signals->first()->reason)->toBe(StopReason::StepsLimitReached);
});

it('StepsLimitHook includes context in stop signal', function () {
    $hook = new StepsLimitHook(
        maxSteps: 3,
        stepCounter: fn() => 5,
    );

    $result = $hook->handle(HookContext::beforeStep(state: AgentState::empty()));

    $signal = $result->state()->executionContinuation()->stopSignals()->first();
    expect($signal->context)->toMatchArray([
        'currentSteps' => 5,
        'maxSteps' => 3,
    ]);
});

// TokenUsageLimitHook tests

it('TokenUsageLimitHook emits no stop signal when under limit', function () {
    $hook = new TokenUsageLimitHook(maxTotalTokens: 10000);

    $result = $hook->handle(HookContext::beforeStep(state: AgentState::empty()));

    expect($result->state()->executionContinuation()->stopSignals()->hasAny())->toBeFalse();
});

// ExecutionTimeLimitHook tests

it('ExecutionTimeLimitHook emits no stop signal when under limit', function () {
    $hook = new ExecutionTimeLimitHook(maxSeconds: 60);

    // First call captures the start time via BeforeExecution
    $hook->handle(HookContext::beforeExecution(state: AgentState::empty()));

    // Second call checks elapsed time — well under 60s
    $result = $hook->handle(HookContext::beforeStep(state: AgentState::empty()));

    expect($result->state()->executionContinuation()->stopSignals()->hasAny())->toBeFalse();
});

it('ExecutionTimeLimitHook emits no stop signal when execution not started', function () {
    $hook = new ExecutionTimeLimitHook(maxSeconds: 60);

    // BeforeStep without prior BeforeExecution — no start time captured
    $result = $hook->handle(HookContext::beforeStep(state: AgentState::empty()));

    expect($result->state()->executionContinuation()->stopSignals()->hasAny())->toBeFalse();
});

it('ExecutionTimeLimitHook passes through non-matching trigger types', function () {
    $hook = new ExecutionTimeLimitHook(maxSeconds: 60);

    // AfterStep is not handled by this hook
    $result = $hook->handle(HookContext::afterStep(state: AgentState::empty()));

    expect($result->state()->executionContinuation()->stopSignals()->hasAny())->toBeFalse();
});

// HookStack integration tests

it('HookStack runs time limit hook via BeforeExecution then BeforeStep', function () {
    $hook = new ExecutionTimeLimitHook(maxSeconds: 60);
    $triggers = HookTriggers::with(HookTrigger::BeforeExecution, HookTrigger::BeforeStep);

    $stack = (new HookStack(new RegisteredHooks()))
        ->with($hook, $triggers, priority: 100);

    $state = AgentState::empty();

    // Capture start time, then check — under limit
    $stack->intercept(HookContext::beforeExecution(state: $state));
    $result = $stack->intercept(HookContext::beforeStep(state: $state));

    expect($result->state()->executionContinuation()->stopSignals()->hasAny())->toBeFalse();
});

it('multiple guards emit no stop signals when under limits', function () {
    $stack = (new HookStack(new RegisteredHooks()))
        ->with(new StepsLimitHook(maxSteps: 10, stepCounter: fn() => 5), HookTriggers::beforeStep(), priority: 100)
        ->with(new TokenUsageLimitHook(maxTotalTokens: 10000), HookTriggers::beforeStep(), priority: 100);

    $result = $stack->intercept(HookContext::beforeStep(state: AgentState::empty()));

    expect($result->state()->executionContinuation()->stopSignals()->hasAny())->toBeFalse();
});

it('guard stop signal propagates through the stack', function () {
    $stack = (new HookStack(new RegisteredHooks()))
        ->with(new StepsLimitHook(maxSteps: 3, stepCounter: fn() => 5), HookTriggers::beforeStep(), priority: 100)
        ->with(new TokenUsageLimitHook(maxTotalTokens: 10000), HookTriggers::beforeStep(), priority: 50);

    $result = $stack->intercept(HookContext::beforeStep(state: AgentState::empty()));

    $signals = $result->state()->executionContinuation()->stopSignals();
    expect($signals->hasAny())->toBeTrue();
    expect($signals->first()->reason)->toBe(StopReason::StepsLimitReached);
});
