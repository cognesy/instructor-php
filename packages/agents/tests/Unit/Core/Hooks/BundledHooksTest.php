<?php declare(strict_types=1);

use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Stop\StopReason;
use Cognesy\Agents\Hooks\Collections\HookTriggers;
use Cognesy\Agents\Hooks\Collections\RegisteredHooks;
use Cognesy\Agents\Hooks\Data\HookContext;
use Cognesy\Agents\Hooks\Defaults\CallableHook;
use Cognesy\Agents\Hooks\Defaults\FinishReasonHook;
use Cognesy\Agents\Hooks\Enums\HookTrigger;
use Cognesy\Agents\Hooks\Guards\ExecutionTimeLimitHook;
use Cognesy\Agents\Hooks\Guards\StepsLimitHook;
use Cognesy\Agents\Hooks\Guards\TokenUsageLimitHook;
use Cognesy\Agents\Hooks\Interceptors\HookStack;
use Cognesy\Agents\Hooks\Interceptors\PassThroughInterceptor;
use Cognesy\Polyglot\Inference\Enums\InferenceFinishReason;

// ── FinishReasonHook ──────────────────────────────────────────────

describe('FinishReasonHook', function () {
    it('emits stop signal when finish reason matches a stop reason', function () {
        $hook = new FinishReasonHook(
            stopReasons: [InferenceFinishReason::Stop],
            finishReasonResolver: fn() => InferenceFinishReason::Stop,
        );

        $result = $hook->handle(HookContext::afterStep(state: AgentState::empty()));

        $signals = $result->state()->executionContinuation()->stopSignals();
        expect($signals->hasAny())->toBeTrue();
        expect($signals->first()->reason)->toBe(StopReason::FinishReasonReceived);
        expect($signals->first()->context['finishReason'])->toBe('stop');
    });

    it('passes through when finish reason does not match', function () {
        $hook = new FinishReasonHook(
            stopReasons: [InferenceFinishReason::Stop],
            finishReasonResolver: fn() => InferenceFinishReason::ToolCalls,
        );

        $result = $hook->handle(HookContext::afterStep(state: AgentState::empty()));

        expect($result->state()->executionContinuation()->stopSignals()->hasAny())->toBeFalse();
    });

    it('passes through when resolver returns null', function () {
        $hook = new FinishReasonHook(
            stopReasons: [InferenceFinishReason::Stop],
            finishReasonResolver: fn() => null,
        );

        $result = $hook->handle(HookContext::afterStep(state: AgentState::empty()));

        expect($result->state()->executionContinuation()->stopSignals()->hasAny())->toBeFalse();
    });

    it('passes through when stop reasons list is empty', function () {
        $hook = new FinishReasonHook(
            stopReasons: [],
            finishReasonResolver: fn() => InferenceFinishReason::Stop,
        );

        $result = $hook->handle(HookContext::afterStep(state: AgentState::empty()));

        expect($result->state()->executionContinuation()->stopSignals()->hasAny())->toBeFalse();
    });

    it('includes all configured stop reasons in context', function () {
        $hook = new FinishReasonHook(
            stopReasons: [InferenceFinishReason::Stop, InferenceFinishReason::Length],
            finishReasonResolver: fn() => InferenceFinishReason::Length,
        );

        $result = $hook->handle(HookContext::afterStep(state: AgentState::empty()));

        $signal = $result->state()->executionContinuation()->stopSignals()->first();
        expect($signal->context['stopReasons'])->toBe(['stop', 'length']);
        expect($signal->source)->toBe(FinishReasonHook::class);
    });
});

// ── StepsLimitHook ────────────────────────────────────────────────

describe('StepsLimitHook', function () {
    it('includes source class in stop signal', function () {
        $hook = new StepsLimitHook(maxSteps: 1, stepCounter: fn() => 2);

        $result = $hook->handle(HookContext::beforeStep(state: AgentState::empty()));

        expect($result->state()->executionContinuation()->stopSignals()->first()->source)
            ->toBe(StepsLimitHook::class);
    });

    it('uses provided step counter to determine current steps', function () {
        $counterCalled = false;
        $hook = new StepsLimitHook(
            maxSteps: 10,
            stepCounter: function ($state) use (&$counterCalled) {
                $counterCalled = true;
                return 3;
            },
        );

        $hook->handle(HookContext::beforeStep(state: AgentState::empty()));

        expect($counterCalled)->toBeTrue();
    });
});

// ── TokenUsageLimitHook ───────────────────────────────────────────

describe('TokenUsageLimitHook', function () {
    it('includes token counts in stop signal context', function () {
        // AgentState::empty() has 0 usage, so maxTotalTokens: 0 should trigger
        $hook = new TokenUsageLimitHook(maxTotalTokens: 0);

        $result = $hook->handle(HookContext::beforeStep(state: AgentState::empty()));

        $signal = $result->state()->executionContinuation()->stopSignals()->first();
        expect($signal->reason)->toBe(StopReason::TokenLimitReached);
        expect($signal->context)->toHaveKeys(['totalTokens', 'maxTotalTokens']);
        expect($signal->source)->toBe(TokenUsageLimitHook::class);
    });
});

// ── ExecutionTimeLimitHook ────────────────────────────────────────

describe('ExecutionTimeLimitHook', function () {
    it('captures start time from BeforeExecution trigger', function () {
        $hook = new ExecutionTimeLimitHook(maxSeconds: 0.001);

        $hook->handle(HookContext::beforeExecution(state: AgentState::empty()));

        // Sleep just past the limit
        usleep(2000); // 2ms

        $result = $hook->handle(HookContext::beforeStep(state: AgentState::empty()));

        $signals = $result->state()->executionContinuation()->stopSignals();
        expect($signals->hasAny())->toBeTrue();
        expect($signals->first()->reason)->toBe(StopReason::TimeLimitReached);
        expect($signals->first()->source)->toBe(ExecutionTimeLimitHook::class);
    });

    it('includes elapsed and max seconds in stop context', function () {
        $hook = new ExecutionTimeLimitHook(maxSeconds: 0.001);

        $hook->handle(HookContext::beforeExecution(state: AgentState::empty()));
        usleep(2000);

        $result = $hook->handle(HookContext::beforeStep(state: AgentState::empty()));

        $signal = $result->state()->executionContinuation()->stopSignals()->first();
        expect($signal->context)->toHaveKeys(['elapsedSeconds', 'maxSeconds']);
        expect($signal->context['maxSeconds'])->toBe(0.001);
    });
});

// ── PassThroughInterceptor ────────────────────────────────────────

describe('PassThroughInterceptor', function () {
    it('returns context unchanged', function () {
        $interceptor = PassThroughInterceptor::default();

        $context = HookContext::beforeStep(state: AgentState::empty());
        $result = $interceptor->intercept($context);

        expect($result)->toBe($context);
    });
});

describe('HookTriggers', function () {
    it('includes onStop in the all trigger set', function () {
        $triggers = HookTriggers::all();

        expect($triggers->triggersOn(HookTrigger::OnStop))->toBeTrue();
    });
});

// ── HookStack composition ─────────────────────────────────────────

describe('HookStack composition', function () {
    it('chains state modifications across multiple hooks', function () {
        $stack = (new HookStack(new RegisteredHooks()))
            ->with(
                new CallableHook(fn(HookContext $ctx) => $ctx->withState(
                    $ctx->state()->withMetadata('first', true),
                )),
                HookTriggers::beforeStep(),
                priority: 20,
            )
            ->with(
                new CallableHook(fn(HookContext $ctx) => $ctx->withState(
                    $ctx->state()->withMetadata('second', true),
                )),
                HookTriggers::beforeStep(),
                priority: 10,
            );

        $result = $stack->intercept(HookContext::beforeStep(state: AgentState::empty()));

        expect($result->state()->metadata()->get('first'))->toBeTrue();
        expect($result->state()->metadata()->get('second'))->toBeTrue();
    });

    it('higher priority hooks run first and can affect later hooks', function () {
        $stack = (new HookStack(new RegisteredHooks()))
            ->with(
                new CallableHook(fn(HookContext $ctx) => $ctx->withState(
                    $ctx->state()->withMetadata('order', 'high'),
                )),
                HookTriggers::beforeStep(),
                priority: 100,
            )
            ->with(
                new CallableHook(function (HookContext $ctx) {
                    // Low-priority hook reads what high-priority wrote
                    $prev = $ctx->state()->metadata()->get('order');
                    return $ctx->withState(
                        $ctx->state()->withMetadata('order', $prev . '+low'),
                    );
                }),
                HookTriggers::beforeStep(),
                priority: 1,
            );

        $result = $stack->intercept(HookContext::beforeStep(state: AgentState::empty()));

        expect($result->state()->metadata()->get('order'))->toBe('high+low');
    });

    it('combines guards and custom hooks in a single stack', function () {
        $stack = (new HookStack(new RegisteredHooks()))
            ->with(
                new CallableHook(fn(HookContext $ctx) => $ctx->withState(
                    $ctx->state()->withMetadata('custom_ran', true),
                )),
                HookTriggers::beforeStep(),
                priority: 200,
            )
            ->with(
                new StepsLimitHook(maxSteps: 1, stepCounter: fn() => 5),
                HookTriggers::beforeStep(),
                priority: 100,
            );

        $result = $stack->intercept(HookContext::beforeStep(state: AgentState::empty()));

        // Both hooks ran: custom metadata set AND stop signal emitted
        expect($result->state()->metadata()->get('custom_ran'))->toBeTrue();
        expect($result->state()->executionContinuation()->stopSignals()->hasAny())->toBeTrue();
    });
});
