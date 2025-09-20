<?php declare(strict_types=1);

use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Addons\Chat\Events\ChatResponseRequested;
use Cognesy\Addons\Chat\Participants\ScriptedParticipant;
use Cognesy\Events\EventBusResolver;
use Cognesy\Messages\Enums\MessageRole;
use Cognesy\Messages\Messages;
use Cognesy\Messages\MessageStore\MessageStore;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Polyglot\Inference\Enums\InferenceFinishReason;

it('creates participant with correct name and cycles through messages', function () {
    $participant = new ScriptedParticipant(
        name: 'test-user',
        messages: ['Hello', 'How are you?', 'Goodbye']
    );
    
    expect($participant->name())->toBe('test-user');
    
    $state = new ChatState();
    
    // First call
    $step1 = $participant->act($state);
    expect($step1->outputMessages()->last()->toString())->toBe('Hello');

    // Second call
    $step2 = $participant->act($state);
    expect($step2->outputMessages()->last()->toString())->toBe('How are you?');
    
    // Third call
    $step3 = $participant->act($state);
    expect($step3->outputMessages()->last()->toString())->toBe('Goodbye');
    
    // Cycles back to first
    $step4 = $participant->act($state);
    expect($step4->outputMessages()->last()->toString())->toBe('Hello');
});

it('uses custom default role', function () {
    $participant = new ScriptedParticipant(
        name: 'assistant-script',
        messages: ['I am an assistant'],
        defaultRole: MessageRole::Assistant
    );
    
    $state = new ChatState();
    $step = $participant->act($state);
    
    expect($step->outputMessages()->last()->role()->value)->toBe('assistant');
    expect($step->outputMessages()->last()->name())->toBe('assistant-script');
});

it('returns proper usage and metadata', function () {
    $participant = new ScriptedParticipant(
        name: 'test-script',
        messages: ['Test message']
    );
    
    $state = new ChatState();
    $step = $participant->act($state);
    
    expect($step->usage())->toBeInstanceOf(Usage::class);
    expect($step->usage()->total())->toBe(0); // Usage::none()
    expect($step->finishReason())->toBe(InferenceFinishReason::Other);
    expect($step->inferenceResponse())->toBeNull();
});

it('dispatches events during execution', function () {
    $events = EventBusResolver::using(null);
    $requestedEvents = [];
    $receivedEvents = [];

    $events->addListener(ChatResponseRequested::class, function($event) use (&$requestedEvents, &$receivedEvents) {
        if (isset($event->data['state'])) {
            $requestedEvents[] = $event;
        } else {
            $receivedEvents[] = $event;
        }
    });

    $participant = new ScriptedParticipant(
        name: 'event-script',
        messages: ['Event test'],
        events: $events
    );

    $messages = Messages::fromArray([
        ['role' => 'user', 'content' => 'Previous message']
    ]);
    $state = new ChatState(store: MessageStore::fromMessages($messages));

    $participant->act($state);

    expect($requestedEvents)->toHaveCount(1);
    expect($receivedEvents)->toHaveCount(1);
    
    $requestedEvent = $requestedEvents[0];
    expect($requestedEvent->data['participant'])->toBe('event-script');
    expect($requestedEvent->data['state'])->toBeArray();
    expect($requestedEvent->data['scriptIndex'])->toBe(0);
    expect($requestedEvent->data['totalMessages'])->toBe(1);
    
    $receivedEvent = $receivedEvents[0];
    expect($receivedEvent->data['participant'])->toBe('event-script');
    expect($receivedEvent->data['response'])->toBeArray();
    expect($receivedEvent->data['scriptIndex'])->toBe(0);
});

it('handles empty message gracefully', function () {
    $participant = new ScriptedParticipant(
        name: 'empty-script',
        messages: []
    );
    
    $state = new ChatState();
    $step = $participant->act($state);
    
    expect($step->outputMessages()->last()->toString())->toBe('');
    expect($step->participantName())->toBe('empty-script');
});

it('compiles input messages using compiler', function () {
    $participant = new ScriptedParticipant(
        name: 'compiler-test',
        messages: ['Response']
    );
    
    $messages = Messages::fromArray([
        ['role' => 'user', 'content' => 'Input message']
    ]);
    $state = new ChatState(store: MessageStore::fromMessages($messages));
    
    $step = $participant->act($state);
    
    expect($step->inputMessages())
        ->toBeInstanceOf(Messages::class)
        ->and($step->inputMessages()->count())->toBe(1)
        ->and($step->inputMessages()->first()->content()->toString())->toBe('Input message');
});
