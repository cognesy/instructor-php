<?php declare(strict_types=1);

namespace Tests\Addons\Unit\Agent;

use Cognesy\Agents\Agent\Continuation\ContinuationCriteria;
use Cognesy\Agents\Agent\Continuation\ContinuationDecision;
use Cognesy\Agents\Agent\Data\AgentState;
use Cognesy\Agents\AgentBuilder\AgentBuilder;

function getAgentCriteria(object $agent): ContinuationCriteria {
    $getter = \Closure::bind(
        function (): ContinuationCriteria {
            return $this->continuationCriteria;
        },
        $agent,
        $agent,
    );
    return $getter();
}

function requestContinuationCriterion(): ContinuationCriteria {
    return ContinuationCriteria::from(
        ContinuationCriteria::when(
            static fn(AgentState $state): ContinuationDecision => ContinuationDecision::RequestContinuation
        ),
    );
}

it('uses wall-clock execution time by default', function () {
    $agent = AgentBuilder::base()
        ->withTimeout(1)
        ->addContinuationCriteria(requestContinuationCriterion())
        ->build();

    $state = AgentState::empty()->with(
        executionStartedAt: new \DateTimeImmutable('-5 seconds'),
    );

    $criteria = getAgentCriteria($agent);
    $outcome = $criteria->evaluateAll($state);

    expect($outcome->shouldContinue())->toBeFalse();
});

it('uses cumulative timeout when configured', function () {
    $agent = AgentBuilder::base()
        ->withTimeout(1)
        ->withCumulativeTimeout(1)
        ->addContinuationCriteria(requestContinuationCriterion())
        ->build();

    $state = AgentState::empty()
        ->with(executionStartedAt: new \DateTimeImmutable('-5 seconds'));
    $state = $state->withStateInfo(
        $state->stateInfo()->addExecutionTime(0.5)
    );

    $criteria = getAgentCriteria($agent);
    $outcome = $criteria->evaluateAll($state);

    expect($outcome->shouldContinue())->toBeTrue();
});
