<?php declare(strict_types=1);

namespace Tests\Addons\Unit\Core;

use Cognesy\Addons\StepByStep\Continuation\ContinuationDecision;
use Cognesy\Addons\StepByStep\Continuation\Criteria\CumulativeExecutionTimeLimit;

final class CumulativeTimeState
{
    public function __construct(public float $seconds) {}
}

it('allows continuation when cumulative time is under limit', function () {
    $criterion = new CumulativeExecutionTimeLimit(
        10,
        static fn(CumulativeTimeState $state): float => $state->seconds,
    );

    $evaluation = $criterion->evaluate(new CumulativeTimeState(9.5));

    expect($evaluation->decision)->toBe(ContinuationDecision::AllowContinuation);
    expect($evaluation->context['cumulativeSeconds'])->toBe(9.5);
    expect($evaluation->context['maxSeconds'])->toBe(10);
});

it('forbids continuation when cumulative time exceeds limit', function () {
    $criterion = new CumulativeExecutionTimeLimit(
        10,
        static fn(CumulativeTimeState $state): float => $state->seconds,
    );

    $evaluation = $criterion->evaluate(new CumulativeTimeState(10.0));

    expect($evaluation->decision)->toBe(ContinuationDecision::ForbidContinuation);
    expect($evaluation->context['cumulativeSeconds'])->toBe(10.0);
    expect($evaluation->context['maxSeconds'])->toBe(10);
});
