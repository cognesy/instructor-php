<?php declare(strict_types=1);

namespace Tests\Addons\Unit\Agent;

use Cognesy\Agents\Agent\Data\AgentState;
use Cognesy\Agents\Agent\Data\AgentStep;
use Cognesy\Agents\Agent\Enums\AgentStatus;
use Cognesy\Agents\Agent\Exceptions\AgentException;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\CachedContext;
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
    $state = AgentState::empty()
        ->withMessages(Messages::fromString('First'))
        ->withAddedStep(new AgentStep())
        ->withUsage(new Usage(1, 2, 3, 4, 5))
        ->withCachedContext(new CachedContext(messages: [['role' => 'user', 'content' => 'cached']]))
        ->markStepStarted()
        ->markExecutionStarted()
        ->withStatus(AgentStatus::Completed);

    $continued = $state->forContinuation();

    expect($continued->status())->toBe(AgentStatus::InProgress)
        ->and($continued->stepCount())->toBe(0)
        ->and($continued->currentStep())->toBeNull()
        ->and($continued->usage()->total())->toBe(0)
        ->and($continued->cache()->isEmpty())->toBeTrue()
        ->and($continued->currentStepStartedAt)->toBeNull()
        ->and($continued->executionStartedAt)->toBeNull()
        ->and($continued->messages()->count())->toBe(1);
});

it('records a step and stamps the start time', function () {
    $state = AgentState::empty();
    $step = new AgentStep();

    $next = $state->recordStep($step);

    expect($next->currentStep())->toBe($step)
        ->and($next->stepCount())->toBe(1)
        ->and($next->currentStepStartedAt)->not->toBeNull();
});

it('fails with an error and records a failure step', function () {
    $state = AgentState::empty()->withMessages(Messages::fromString('hi'));
    $error = AgentException::fromThrowable(new \RuntimeException('boom'));

    $failed = $state->failWith($error);
    $currentStep = $failed->currentStep();

    expect($failed->status())->toBe(AgentStatus::Failed)
        ->and($failed->stepCount())->toBe(1)
        ->and($currentStep)->not->toBeNull()
        ->and($currentStep?->hasErrors())->toBeTrue();
});
