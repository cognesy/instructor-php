<?php declare(strict_types=1);

namespace Tests\Addons\Unit\Agent;

use Cognesy\Agents\Agent\Collections\ToolExecutions;
use Cognesy\Agents\Agent\Continuation\ContinuationCriteria;
use Cognesy\Agents\Agent\Continuation\ContinuationDecision;
use Cognesy\Agents\Agent\Continuation\ContinuationOutcome;
use Cognesy\Agents\Agent\Data\AgentState;
use Cognesy\Agents\Agent\Data\AgentStep;
use Cognesy\Agents\Agent\Data\StepExecution;
use Cognesy\Agents\Agent\Data\ToolExecution;
use Cognesy\Agents\Agent\ErrorHandling\ErrorPolicy;
use Cognesy\Agents\AgentBuilder\AgentBuilder;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Utils\Result\Result;

function makeErrorState(): AgentState {
    $execution = new ToolExecution(
        toolCall: new ToolCall('tool', [], 'call_1'),
        result: Result::failure(new \RuntimeException('tool failed')),
        startedAt: new \DateTimeImmutable(),
        completedAt: new \DateTimeImmutable(),
    );
    $stepId = 'step-1';
    $step = new AgentStep(
        toolExecutions: new ToolExecutions($execution),
        id: $stepId,
    );
    $stepExecution = new StepExecution(
        step: $step,
        outcome: ContinuationOutcome::empty(),
        startedAt: new \DateTimeImmutable(),
        completedAt: new \DateTimeImmutable(),
        stepNumber: 1,
        id: $stepId,
    );

    return AgentState::empty()->recordStepExecution($stepExecution);
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
    $outcome = $criteria->evaluateAll(makeErrorState());

    expect($outcome->shouldContinue())->toBeFalse();
});

it('applies custom error policy in continuation criteria', function () {
    $agent = AgentBuilder::base()
        ->withErrorPolicy(ErrorPolicy::retryToolErrors(3))
        ->addContinuationCriteria(alwaysRequestContinuation())
        ->build();

    $criteria = getCriteria($agent);
    $outcome = $criteria->evaluateAll(makeErrorState());

    expect($outcome->shouldContinue())->toBeTrue();
});
