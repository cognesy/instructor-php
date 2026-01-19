<?php declare(strict_types=1);

namespace Tests\Addons\Unit\Core;

use Cognesy\Addons\StepByStep\Continuation\ContinuationDecision;
use Cognesy\Addons\StepByStep\Continuation\ContinuationEvaluation;
use Cognesy\Addons\StepByStep\Continuation\ContinuationOutcome;
use Cognesy\Addons\StepByStep\Continuation\Criteria\StepsLimit;
use Cognesy\Addons\StepByStep\Continuation\Criteria\TokenUsageLimit;
use Cognesy\Addons\StepByStep\Continuation\Criteria\ExecutionTimeLimit;
use Cognesy\Addons\StepByStep\Continuation\StopReason;

it('exposes shouldContinue and forbidding criterion', function () {
    $evaluations = [
        new ContinuationEvaluation(StepsLimit::class, ContinuationDecision::AllowContinuation, 'ok'),
        new ContinuationEvaluation(TokenUsageLimit::class, ContinuationDecision::ForbidContinuation, 'no'),
        new ContinuationEvaluation(ExecutionTimeLimit::class, ContinuationDecision::ForbidContinuation, 'no'),
    ];

    // Use fromEvaluations factory - derives shouldContinue from evaluations
    $outcome = ContinuationOutcome::fromEvaluations($evaluations);

    expect($outcome->shouldContinue())->toBeFalse();
    expect($outcome->getForbiddingCriterion())->toBe(TokenUsageLimit::class);
    // decision() returns ForbidContinuation to indicate guard actively denied
    expect($outcome->decision())->toBe(ContinuationDecision::ForbidContinuation);
    expect($outcome->stopReason())->toBe(StopReason::GuardForbade);
});

it('derives decision from evaluations', function () {
    $evaluations = [
        new ContinuationEvaluation(StepsLimit::class, ContinuationDecision::AllowContinuation, 'ok'),
        new ContinuationEvaluation(TokenUsageLimit::class, ContinuationDecision::RequestContinuation, 'work requested'),
    ];

    $outcome = ContinuationOutcome::fromEvaluations($evaluations);

    expect($outcome->shouldContinue())->toBeTrue();
    expect($outcome->decision())->toBe(ContinuationDecision::RequestContinuation);
    expect($outcome->stopReason())->toBe(StopReason::Completed);
});

it('empty outcome stops with Completed reason', function () {
    $outcome = ContinuationOutcome::empty();

    expect($outcome->shouldContinue())->toBeFalse();
    expect($outcome->decision())->toBe(ContinuationDecision::AllowStop);
    expect($outcome->stopReason())->toBe(StopReason::Completed);
    expect($outcome->evaluations)->toBe([]);
});

it('returns resolvedBy as aggregate when no specific resolver', function () {
    $evaluations = [
        new ContinuationEvaluation(StepsLimit::class, ContinuationDecision::AllowStop, 'no work'),
        new ContinuationEvaluation(TokenUsageLimit::class, ContinuationDecision::AllowStop, 'no work'),
    ];

    $outcome = ContinuationOutcome::fromEvaluations($evaluations);

    expect($outcome->shouldContinue())->toBeFalse();
    expect($outcome->resolvedBy())->toBe('aggregate');
});

it('toArray includes all outcome data', function () {
    $evaluations = [
        new ContinuationEvaluation(StepsLimit::class, ContinuationDecision::ForbidContinuation, 'limit reached'),
    ];

    $outcome = ContinuationOutcome::fromEvaluations($evaluations);
    $array = $outcome->toArray();

    // decision() returns ForbidContinuation to indicate guard actively denied
    expect($array['decision'])->toBe('forbid');
    expect($array['shouldContinue'])->toBeFalse();
    expect($array['resolvedBy'])->toBe(StepsLimit::class);
    expect($array['stopReason'])->toBe('guard'); // StopReason::GuardForbade->value
    expect(count($array['evaluations']))->toBe(1);
});
