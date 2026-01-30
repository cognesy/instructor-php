<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Core;

use Cognesy\Agents\AgentHooks\Guards\ExecutionTimeLimitHook;
use Cognesy\Agents\AgentHooks\Guards\StepsLimitHook;
use Cognesy\Agents\AgentHooks\Guards\TokenUsageLimitHook;
use Cognesy\Agents\Core\Continuation\Data\ContinuationEvaluation;
use Cognesy\Agents\Core\Continuation\Data\ContinuationOutcome;
use Cognesy\Agents\Core\Continuation\Enums\ContinuationDecision;
use Cognesy\Agents\Core\Continuation\Enums\StopReason;

it('exposes shouldContinue and forbidding criterion', function () {
    $evaluations = [
        new ContinuationEvaluation(StepsLimitHook::class, ContinuationDecision::AllowContinuation, 'ok'),
        new ContinuationEvaluation(TokenUsageLimitHook::class, ContinuationDecision::ForbidContinuation, 'no'),
        new ContinuationEvaluation(ExecutionTimeLimitHook::class, ContinuationDecision::ForbidContinuation, 'no'),
    ];

    // Use fromEvaluations factory - derives shouldContinue from evaluations
    $outcome = ContinuationOutcome::fromEvaluations($evaluations);

    expect($outcome->shouldContinue())->toBeFalse();
    expect($outcome->getForbiddingCriterion())->toBe(TokenUsageLimitHook::class);
    // decision() returns ForbidContinuation to indicate guard actively denied
    expect($outcome->decision())->toBe(ContinuationDecision::ForbidContinuation);
    expect($outcome->stopReason())->toBe(StopReason::GuardForbade);
});

it('derives decision from evaluations', function () {
    $evaluations = [
        new ContinuationEvaluation(StepsLimitHook::class, ContinuationDecision::AllowContinuation, 'ok'),
        new ContinuationEvaluation(TokenUsageLimitHook::class, ContinuationDecision::RequestContinuation, 'work requested'),
    ];

    $outcome = ContinuationOutcome::fromEvaluations($evaluations);

    expect($outcome->shouldContinue())->toBeTrue();
    expect($outcome->decision())->toBe(ContinuationDecision::RequestContinuation);
    expect($outcome->stopReason())->toBe(StopReason::Completed);
});

it('empty outcome continues by default (no stopping evaluations)', function () {
    $outcome = ContinuationOutcome::empty();

    // With no evaluations defined, continue by default
    expect($outcome->shouldContinue())->toBeTrue();
    // RequestContinuation indicates work should proceed
    expect($outcome->decision())->toBe(ContinuationDecision::RequestContinuation);
    expect($outcome->stopReason())->toBe(StopReason::Completed);
    expect($outcome->evaluations)->toBe([]);
});

it('returns resolvedBy as aggregate when no specific resolver', function () {
    $evaluations = [
        new ContinuationEvaluation(StepsLimitHook::class, ContinuationDecision::AllowStop, 'no work'),
        new ContinuationEvaluation(TokenUsageLimitHook::class, ContinuationDecision::AllowStop, 'no work'),
    ];

    $outcome = ContinuationOutcome::fromEvaluations($evaluations);

    expect($outcome->shouldContinue())->toBeFalse();
    expect($outcome->resolvedBy())->toBe('aggregate');
});

it('toArray includes all outcome data', function () {
    $evaluations = [
        new ContinuationEvaluation(StepsLimitHook::class, ContinuationDecision::ForbidContinuation, 'limit reached'),
    ];

    $outcome = ContinuationOutcome::fromEvaluations($evaluations);
    $array = $outcome->toArray();

    // decision() returns ForbidContinuation to indicate guard actively denied
    expect($array['decision'])->toBe('forbid');
    expect($array['shouldContinue'])->toBeFalse();
    expect($array['resolvedBy'])->toBe(StepsLimitHook::class);
    expect($array['stopReason'])->toBe('guard'); // StopReason::GuardForbade->value
    expect(count($array['evaluations']))->toBe(1);
});
