<?php declare(strict_types=1);

namespace Tests\Addons\Unit\Core;

use Cognesy\Addons\StepByStep\Continuation\ContinuationDecision;
use Cognesy\Addons\StepByStep\Continuation\ContinuationEvaluation;
use Cognesy\Addons\StepByStep\Continuation\ContinuationOutcome;
use Cognesy\Addons\StepByStep\Continuation\StopReason;

it('exposes shouldContinue and forbidding criterion', function () {
    $evaluations = [
        new ContinuationEvaluation('AllowCriterion', ContinuationDecision::AllowContinuation, 'ok'),
        new ContinuationEvaluation('ForbidCriterion', ContinuationDecision::ForbidContinuation, 'no'),
        new ContinuationEvaluation('ForbidCriterion2', ContinuationDecision::ForbidContinuation, 'no'),
    ];

    $outcome = new ContinuationOutcome(
        decision: ContinuationDecision::AllowStop,
        shouldContinue: false,
        resolvedBy: 'ForbidCriterion',
        stopReason: StopReason::GuardForbade,
        evaluations: $evaluations,
    );

    expect($outcome->shouldContinue())->toBeFalse();
    expect($outcome->getForbiddingCriterion())->toBe('ForbidCriterion');
});
