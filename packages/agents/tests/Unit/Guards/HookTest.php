<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Guards;

use Cognesy\Agents\Continuation\StopReason;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Hook\Collections\HookTriggers;
use Cognesy\Agents\Hook\Collections\RegisteredHooks;
use Cognesy\Agents\Hook\Data\HookContext;
use Cognesy\Agents\Hook\Enums\HookTrigger;
use Cognesy\Agents\Hook\Hooks\CallableHook;
use Cognesy\Agents\Hook\Hooks\StepsLimitHook;
use Cognesy\Agents\Hook\HookStack;

it('HookStack processes hooks for matching triggers', function () {
    $stack = (new HookStack(new RegisteredHooks()))
        ->with(
            new StepsLimitHook(maxSteps: 10, stepCounter: fn() => 5),
            HookTriggers::beforeStep(),
            priority: 100,
        );

    $result = $stack->intercept(HookContext::beforeStep(state: AgentState::empty()));

    expect($result->state()->executionContinuation()->stopSignals()->hasAny())->toBeFalse();
});

it('HookStack skips hooks for non-matching triggers', function () {
    $stack = (new HookStack(new RegisteredHooks()))
        ->with(
            new StepsLimitHook(maxSteps: 5, stepCounter: fn() => 10),
            HookTriggers::beforeStep(),
            priority: 100,
        );

    // AfterStep won't trigger a BeforeStep-registered hook
    $result = $stack->intercept(HookContext::afterStep(state: AgentState::empty()));

    expect($result->state()->executionContinuation()->stopSignals()->hasAny())->toBeFalse();
});

it('HookStack resolves stop signal from multiple hooks', function () {
    $stack = (new HookStack(new RegisteredHooks()))
        ->with(
            new StepsLimitHook(maxSteps: 10, stepCounter: fn() => 5),
            HookTriggers::beforeStep(),
            priority: 100,
        )
        ->with(
            new StepsLimitHook(maxSteps: 3, stepCounter: fn() => 5),
            HookTriggers::beforeStep(),
            priority: 50,
        );

    $result = $stack->intercept(HookContext::beforeStep(state: AgentState::empty()));

    $signals = $result->state()->executionContinuation()->stopSignals();
    expect($signals->hasAny())->toBeTrue();
    expect($signals->first()->reason)->toBe(StopReason::StepsLimitReached);
});

it('CallableHook wraps a closure as a hook', function () {
    $called = false;

    $hook = new CallableHook(function (HookContext $context) use (&$called) {
        $called = true;
        return $context;
    });

    $context = HookContext::beforeStep(state: AgentState::empty());
    $result = $hook->handle($context);

    expect($called)->toBeTrue();
    expect($result)->toBe($context);
});

it('HookStack with callable hook modifies state via withMetadata', function () {
    $stack = (new HookStack(new RegisteredHooks()))
        ->with(
            new CallableHook(fn(HookContext $ctx) => $ctx->withState(
                $ctx->state()->withMetadata('touched', true),
            )),
            HookTriggers::afterStep(),
            priority: 10,
        );

    $result = $stack->intercept(HookContext::afterStep(state: AgentState::empty()));

    expect($result->state()->metadata()->get('touched'))->toBeTrue();
});

it('HookTriggers::all matches every trigger type', function () {
    $triggers = HookTriggers::all();

    expect($triggers->triggersOn(HookTrigger::BeforeExecution))->toBeTrue();
    expect($triggers->triggersOn(HookTrigger::BeforeStep))->toBeTrue();
    expect($triggers->triggersOn(HookTrigger::AfterStep))->toBeTrue();
    expect($triggers->triggersOn(HookTrigger::AfterExecution))->toBeTrue();
    expect($triggers->triggersOn(HookTrigger::BeforeToolUse))->toBeTrue();
    expect($triggers->triggersOn(HookTrigger::AfterToolUse))->toBeTrue();
    expect($triggers->triggersOn(HookTrigger::OnError))->toBeTrue();
});

it('HookTriggers::none matches no trigger type', function () {
    $triggers = HookTriggers::none();

    expect($triggers->triggersOn(HookTrigger::BeforeStep))->toBeFalse();
    expect($triggers->triggersOn(HookTrigger::AfterStep))->toBeFalse();
});
