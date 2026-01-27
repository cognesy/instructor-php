<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Agent;

use Cognesy\Agents\AgentBuilder\AgentBuilder;
use Cognesy\Agents\Core\Collections\ToolExecutions;
use Cognesy\Agents\Core\Continuation\ContinuationCriteria;
use Cognesy\Agents\Core\Continuation\Data\ContinuationOutcome;
use Cognesy\Agents\Core\Continuation\Enums\ContinuationDecision;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Data\AgentStep;
use Cognesy\Agents\Core\Data\StepExecution;
use Cognesy\Agents\Core\Data\ToolExecution;
use Cognesy\Agents\Core\ErrorHandling\ErrorPolicy;
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
