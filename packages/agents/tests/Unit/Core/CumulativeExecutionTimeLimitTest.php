<?php declare(strict_types=1);

namespace Tests\Addons\Unit\Core;

use Cognesy\Agents\Agent\Continuation\ContinuationDecision;
use Cognesy\Agents\Agent\Continuation\Criteria\CumulativeExecutionTimeLimit;
use Cognesy\Agents\Agent\Data\AgentState;

it('allows continuation when cumulative time is under limit', function () {
    $criterion = new CumulativeExecutionTimeLimit(
        10,
        static fn(AgentState $state): float => $state->stateInfo()->cumulativeExecutionSeconds(),
    );

    $state = AgentState::empty()->withAddedExecutionTime(9.5);
    $evaluation = $criterion->evaluate($state);

    expect($evaluation->decision)->toBe(ContinuationDecision::AllowContinuation);
    expect($evaluation->context['cumulativeSeconds'])->toBe(9.5);
    expect($evaluation->context['maxSeconds'])->toBe(10);
});

it('forbids continuation when cumulative time exceeds limit', function () {
    $criterion = new CumulativeExecutionTimeLimit(
        10,
        static fn(AgentState $state): float => $state->stateInfo()->cumulativeExecutionSeconds(),
    );

    $state = AgentState::empty()->withAddedExecutionTime(10.0);
    $evaluation = $criterion->evaluate($state);

    expect($evaluation->decision)->toBe(ContinuationDecision::ForbidContinuation);
    expect($evaluation->context['cumulativeSeconds'])->toBe(10.0);
    expect($evaluation->context['maxSeconds'])->toBe(10);
});
