<?php declare(strict_types=1);

use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Addons\Chat\Data\Chat;
use Cognesy\Addons\StepByStep\StateProcessing\Processors\AppendStepMessages;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;

function applyStep(ChatState $state, Chat $step, AppendStepMessages $processor): ChatState {
    $stateWithStep = $state
        ->withAddedStep($step)
        ->withCurrentStep($step);

    return $processor->process($stateWithStep);
}

it('creates empty ChatState correctly', function () {
    $state = new ChatState();

    expect($state->stepCount())->toBe(0);
    expect($state->currentStep())->toBeNull();
    expect($state->messages()->count())->toBe(0);
    expect($state->steps()->count())->toBe(0);
});

it('appends steps correctly and maintains step history', function () {
    $state = new ChatState();
    $processor = new AppendStepMessages();

    $step1 = new Chat(
        participantName: 'user',
        outputMessages: new Messages(new Message('user', 'Hello')),
        metadata: []
    );

    $step2 = new Chat(
        participantName: 'assistant',
        outputMessages: new Messages(new Message('assistant', 'Hi there!')),
        metadata: []
    );

    // Add first step
    $state1 = applyStep($state, $step1, $processor);
    expect($state1->stepCount())->toBe(1);
    expect($state1->currentStep())->toBe($step1);
    expect($state1->steps()->count())->toBe(1);
    expect($state1->messages()->count())->toBe(1);

    // Add second step
    $state2 = applyStep($state1, $step2, $processor);
    expect($state2->stepCount())->toBe(2);
    expect($state2->currentStep())->toBe($step2);
    expect($state2->steps()->count())->toBe(2);
    expect($state2->messages()->count())->toBe(2);

    // Verify step history is preserved
    $allSteps = $state2->steps()->all();
    expect($allSteps[0])->toBe($step1);
    expect($allSteps[1])->toBe($step2);

    // Verify message accumulation
    $allMessages = $state2->messages()->toArray();
    expect($allMessages[0]['content'])->toBe('Hello');
    expect($allMessages[1]['content'])->toBe('Hi there!');
});

it('maintains immutability when appending steps', function () {
    $state = new ChatState();
    $processor = new AppendStepMessages();
    $step = new Chat(
        participantName: 'user',
        outputMessages: new Messages(new Message('user', 'Test')),
        metadata: []
    );

    $newState = applyStep($state, $step, $processor);

    // Original state should remain unchanged
    expect($state->stepCount())->toBe(0);
    expect($state->currentStep())->toBeNull();

    // New state should have the step
    expect($newState->stepCount())->toBe(1);
    expect($newState->currentStep())->toBe($step);
    expect($newState)->not()->toBe($state);
});

it('correctly builds conversation messages from multiple steps', function () {
    $state = new ChatState();
    $processor = new AppendStepMessages();

    $userStep = new Chat(
        participantName: 'user',
        outputMessages: new Messages(
            new Message('user', 'What is AI?'),
        ),
        metadata: []
    );

    $assistantStep = new Chat(
        participantName: 'assistant',
        outputMessages: new Messages(
            new Message('assistant', 'AI stands for Artificial Intelligence.'),
        ),
        metadata: []
    );

    $followUpStep = new Chat(
        participantName: 'user',
        outputMessages: new Messages(
            new Message('user', 'Can you explain more?'),
        ),
        metadata: []
    );

    $stateAfterUser = applyStep($state, $userStep, $processor);
    $stateAfterAssistant = applyStep($stateAfterUser, $assistantStep, $processor);
    $finalState = applyStep($stateAfterAssistant, $followUpStep, $processor);

    expect($finalState->stepCount())->toBe(3);

    $messages = $finalState->messages()->toArray();
    expect($messages)->toHaveCount(3);
    expect($messages[0]['role'])->toBe('user');
    expect($messages[0]['content'])->toBe('What is AI?');
    expect($messages[1]['role'])->toBe('assistant');
    expect($messages[1]['content'])->toBe('AI stands for Artificial Intelligence.');
    expect($messages[2]['role'])->toBe('user');
    expect($messages[2]['content'])->toBe('Can you explain more?');
});

it('handles steps with multiple messages correctly', function () {
    $state = new ChatState();
    $processor = new AppendStepMessages();

    $multiMessageStep = new Chat(
        participantName: 'assistant',
        outputMessages: new Messages(
            new Message('assistant', 'First response.'),
            new Message('assistant', 'Second response.'),
        ),
        metadata: []
    );

    $finalState = applyStep($state, $multiMessageStep, $processor);

    expect($finalState->stepCount())->toBe(1);
    expect($finalState->messages()->count())->toBe(2);

    $messages = $finalState->messages()->toArray();
    expect($messages[0]['content'])->toBe('First response.');
    expect($messages[1]['content'])->toBe('Second response.');
});

it('preserves step metadata throughout state transitions', function () {
    $state = new ChatState();

    $metadata = ['timestamp' => '2024-01-01', 'source' => 'test'];
    $step = new Chat(
        participantName: 'user',
        outputMessages: new Messages(new Message('user', 'Test')),
        metadata: $metadata
    );

    $newState = $state->withAddedStep($step)->withCurrentStep($step);
    $retrievedStep = $newState->currentStep();

    expect($retrievedStep?->metadata()->toArray())->toBe($metadata);
    expect($newState->steps()->all()[0]->metadata()->toArray())->toBe($metadata);
});

it('captures participant errors inside failure steps', function () {
    $messages = Messages::fromString('hello there');
    $error = new RuntimeException('participant blew up');

    $failureStep = Chat::failure(
        error: $error,
        participantName: 'assistant',
        inputMessages: $messages,
    );

    expect($failureStep->hasErrors())->toBeTrue();
    expect($failureStep->errors())->toHaveCount(1);
    expect($failureStep->errors()[0]->getMessage())->toBe('participant blew up');
    expect($failureStep->errorsAsString())->toContain('participant blew up');

    $serialized = $failureStep->toArray();
    expect($serialized['errors'])->toHaveCount(1);
    expect($serialized['errors'][0]['message'])->toBe('participant blew up');
});
