<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Agent;

use Cognesy\Agents\Core\Continuation\Data\ContinuationOutcome;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Data\AgentStep;
use Cognesy\Agents\Core\Data\StepExecution;
use Cognesy\Agents\Core\Enums\AgentStatus;
use Cognesy\Agents\Core\Exceptions\AgentException;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\CachedContext;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\Usage;

it('appends a user message to the default section', function () {
    $state = AgentState::empty();

    $next = $state->withUserMessage('Hello');

    $messages = $next->messages()->toArray();
    expect($messages)->toHaveCount(1)
        ->and($messages[0]['role'])->toBe('user')
        ->and($messages[0]['content'])->toBe('Hello');
});

it('resets execution state for continuation', function () {
    $stepId = 'step-1';
    $step = new AgentStep(
        inferenceResponse: new InferenceResponse(usage: new Usage(1, 2, 0, 0, 0)),
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
    $state = AgentState::empty()
        ->withMessages(Messages::fromString('First'))
        ->recordStepExecution($stepExecution)
        ->withCachedContext(new CachedContext(messages: [['role' => 'user', 'content' => 'cached']]))
        ->withStatus(AgentStatus::Completed);

    $continued = $state->forContinuation();

    expect($continued->status())->toBe(AgentStatus::InProgress)
        ->and($continued->stepCount())->toBe(0)
        ->and($continued->currentStep())->toBeNull()
        ->and($continued->usage()->total())->toBe(0)
        ->and($continued->cache()->isEmpty())->toBeTrue()
        ->and($continued->messages()->count())->toBe(1);
});

it('records a step result and sets currentStep', function () {
    $state = AgentState::empty();
    $stepId = 'step-1';
    $step = new AgentStep(id: $stepId);
    $stepExecution = new StepExecution(
        step: $step,
        outcome: ContinuationOutcome::empty(),
        startedAt: new \DateTimeImmutable(),
        completedAt: new \DateTimeImmutable(),
        stepNumber: 1,
        id: $stepId,
    );

    $next = $state->recordStepExecution($stepExecution);

    expect($next->currentStep())->toBe($step)
        ->and($next->stepCount())->toBe(1);
});

it('fails with an error and records a failure step', function () {
    $state = AgentState::empty()->withMessages(Messages::fromString('hi'));
    $error = AgentException::fromThrowable(new \RuntimeException('boom'));

    $failed = $state->failWith($error);
    $currentStep = $failed->currentStep();

    expect($failed->status())->toBe(AgentStatus::Failed)
        ->and($currentStep)->not->toBeNull()
        ->and($currentStep?->hasErrors())->toBeTrue();
});
