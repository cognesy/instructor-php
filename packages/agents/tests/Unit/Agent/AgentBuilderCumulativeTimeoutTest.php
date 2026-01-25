<?php declare(strict_types=1);

namespace Tests\Addons\Unit\Agent;

use Cognesy\Agents\Agent\Continuation\ContinuationCriteria;
use Cognesy\Agents\Agent\Continuation\ContinuationDecision;
use Cognesy\Agents\Agent\Data\AgentState;
use Cognesy\Agents\AgentBuilder\AgentBuilder;
use DateTimeImmutable;

function getAgentCriteria(object $agent): ContinuationCriteria {
    /** @psalm-suppress InvalidScope */
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

    $criteria = getAgentCriteria($agent);
    $criteria->executionStarted(new DateTimeImmutable('-5 seconds'));

    $state = AgentState::empty();
    $outcome = $criteria->evaluateAll($state);

    expect($outcome->shouldContinue())->toBeFalse();
});
