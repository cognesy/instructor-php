<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Agent;

use Cognesy\Agents\Core\Context\AgentContext;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Data\AgentStep;
use Cognesy\Agents\Core\Enums\ExecutionStatus;
use Cognesy\Agents\Exceptions\AgentException;
use Cognesy\Messages\Messages;
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
    $state = AgentState::empty()
        ->withMessages(Messages::fromString('First'))
        ->withCurrentStep($step)
        ->withExecutionCompleted();
    $state = $state->with(context: $state->context()->withSystemPrompt('You are a test agent'));

    $continued = $state->forNextExecution();

    // After forContinuation(), the state is "between executions" (no active execution)
    expect($continued->status())->toBeNull()
        ->and($continued->hasCurrentExecution())->toBeFalse()
        ->and($continued->stepCount())->toBe(0)
        ->and($continued->currentStep())->toBeNull()
        ->and($continued->usage()->total())->toBe(0)
        ->and($continued->context()->systemPrompt())->toBe('You are a test agent')
        ->and($continued->messages()->count())->toBe(1);
});

it('records a step result and sets currentStep', function () {
    $state = AgentState::empty();
    $stepId = 'step-1';
    $step = new AgentStep(id: $stepId);
    $next = $state->withCurrentStep($step)->withExecutionCompleted();

    expect($next->currentStep())->toBeNull()
        ->and($next->stepCount())->toBe(1)
        ->and($next->steps()->lastStep())->toBe($step);
});

it('fails with an error and records a failure step', function () {
    $state = AgentState::empty()
        ->withMessages(Messages::fromString('hi'))
        ->withCurrentStep(new AgentStep(id: 'step-1'));
    $error = AgentException::fromError(new \RuntimeException('boom'));

    $failed = $state->withFailure($error);
    $lastStep = $failed->steps()->lastStep();

    expect($failed->status())->toBe(ExecutionStatus::Failed)
        ->and($lastStep)->not->toBeNull()
        ->and($lastStep?->hasErrors())->toBeTrue();
});
