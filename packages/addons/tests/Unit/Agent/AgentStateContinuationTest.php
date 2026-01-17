<?php declare(strict_types=1);

namespace Tests\Addons\Unit\Agent;

use Cognesy\Addons\Agent\Core\Data\AgentState;
use Cognesy\Addons\Agent\Core\Data\AgentStep;
use Cognesy\Addons\Agent\Core\Enums\AgentStatus;
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
