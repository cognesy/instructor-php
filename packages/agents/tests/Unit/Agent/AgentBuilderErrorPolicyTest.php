<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Agent;

use Cognesy\Agents\AgentBuilder\AgentBuilder;
use Cognesy\Agents\AgentHooks\Enums\HookType;
use Cognesy\Agents\AgentHooks\HookStackObserver;
use Cognesy\Agents\Core\Collections\ToolExecutions;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Data\AgentStep;
use Cognesy\Agents\Core\Data\StepExecution;
use Cognesy\Agents\Core\Data\ToolExecution;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Utils\Result\Result;
use tmp\ErrorHandling\ErrorPolicy;

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
        stopSignal: null,
        startedAt: new \DateTimeImmutable(),
        completedAt: new \DateTimeImmutable(),
        stepNumber: 1,
        id: $stepId,
    );

    return AgentState::empty()->withStepExecutionRecorded($stepExecution);
}

function evaluateContinuation(AgentBuilder $builder, AgentState $state): bool {
    $agent = $builder->build();
    $observer = $agent->observer();
    if (!$observer instanceof HookStackObserver) {
        throw new \RuntimeException('HookStackObserver is missing.');
    }

    $state = $state->withNewStepExecution();
    $processed = $observer->hookStack()->process($state, HookType::AfterStep);

    return $processed->pendingStopSignal() === null && $processed->continuationRequested();
}

it('defaults to stop on any error', function () {
    $shouldContinue = evaluateContinuation(AgentBuilder::base(), makeErrorState());

    expect($shouldContinue)->toBeFalse();
})->skip('hooks not integrated yet');

it('applies custom error policy in hook processing', function () {
    $shouldContinue = evaluateContinuation(
        AgentBuilder::base()->withErrorPolicy(ErrorPolicy::retryToolErrors(3)),
        makeErrorState()
    );

    expect($shouldContinue)->toBeTrue();
})->skip('hooks not integrated yet');
