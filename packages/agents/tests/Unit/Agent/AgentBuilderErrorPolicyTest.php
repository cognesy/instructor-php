<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Agent;

use Cognesy\Agents\AgentBuilder\AgentBuilder;
use Cognesy\Agents\Core\Collections\ToolExecutions;
use Cognesy\Agents\Core\Continuation\Data\ContinuationOutcome;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Data\AgentStep;
use Cognesy\Agents\Core\Data\StepExecution;
use Cognesy\Agents\Core\Data\ToolExecution;
use Cognesy\Agents\Core\ErrorHandling\ErrorPolicy;
use Cognesy\Agents\AgentHooks\HookStackObserver;
use Cognesy\Agents\AgentHooks\Enums\HookType;
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

    return AgentState::empty()->withStepExecutionRecorded($stepExecution);
}

function evaluateOutcome(AgentBuilder $builder, AgentState $state): ContinuationOutcome {
    $agent = $builder->build();
    $observer = $agent->observer();
    if (!$observer instanceof HookStackObserver) {
        throw new \RuntimeException('HookStackObserver is missing.');
    }

    $state = $state->withNewStepExecution();
    $processed = $observer->hookStack()->process($state, HookType::AfterStep);
    $evaluations = $processed->evaluations();

    return ContinuationOutcome::fromEvaluations($evaluations);
}

it('defaults to stop on any error', function () {
    $outcome = evaluateOutcome(AgentBuilder::base(), makeErrorState());

    expect($outcome->shouldContinue())->toBeFalse();
});

it('applies custom error policy in hook evaluations', function () {
    $outcome = evaluateOutcome(
        AgentBuilder::base()->withErrorPolicy(ErrorPolicy::retryToolErrors(3)),
        makeErrorState()
    );

    expect($outcome->shouldContinue())->toBeTrue();
});
