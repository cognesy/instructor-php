<?php declare(strict_types=1);

namespace Tests\Addons\Unit\Agent;

use Cognesy\Addons\Agent\AgentBuilder;
use Cognesy\Addons\Agent\Core\Collections\ToolExecutions;
use Cognesy\Addons\Agent\Core\Data\AgentExecution;
use Cognesy\Addons\Agent\Core\Data\AgentState;
use Cognesy\Addons\Agent\Core\Data\AgentStep;
use Cognesy\Addons\StepByStep\Continuation\ContinuationCriteria;
use Cognesy\Addons\StepByStep\Continuation\ContinuationDecision;
use Cognesy\Addons\StepByStep\Continuation\ErrorPolicy;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Utils\Result\Result;

function makeErrorState(): AgentState {
    $execution = new AgentExecution(
        toolCall: new ToolCall('tool', [], 'call_1'),
        result: Result::failure(new \RuntimeException('tool failed')),
        startedAt: new \DateTimeImmutable(),
        endedAt: new \DateTimeImmutable(),
    );
    $step = new AgentStep(
        toolExecutions: new ToolExecutions($execution),
    );

    return AgentState::empty()
        ->withAddedStep($step)
        ->withCurrentStep($step);
}

function alwaysRequestContinuation(): ContinuationCriteria {
    return ContinuationCriteria::from(
        ContinuationCriteria::when(
            static fn(AgentState $state): ContinuationDecision => ContinuationDecision::RequestContinuation
        ),
    );
}

function getCriteria(object $agent): ContinuationCriteria {
    $getter = \Closure::bind(
        function (): ContinuationCriteria {
            return $this->continuationCriteria;
        },
        $agent,
        $agent,
    );
    return $getter();
}

it('defaults to stop on any error', function () {
    $agent = AgentBuilder::base()
        ->addContinuationCriteria(alwaysRequestContinuation())
        ->build();

    $criteria = getCriteria($agent);
    $outcome = $criteria->evaluate(makeErrorState());

    expect($outcome->shouldContinue())->toBeFalse();
});

it('applies custom error policy in continuation criteria', function () {
    $agent = AgentBuilder::base()
        ->withErrorPolicy(ErrorPolicy::retryToolErrors(3))
        ->addContinuationCriteria(alwaysRequestContinuation())
        ->build();

    $criteria = getCriteria($agent);
    $outcome = $criteria->evaluate(makeErrorState());

    expect($outcome->shouldContinue())->toBeTrue();
});
