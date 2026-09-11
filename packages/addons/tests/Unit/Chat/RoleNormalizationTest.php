<?php declare(strict_types=1);

namespace Cognesy\Addons\Tests\Unit\Chat;

use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Addons\Chat\Participants\LLMParticipant;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Messages\MessageStore\MessageStore;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Addons\Tests\Support\FakeInferenceDriver;

it('llm participant prepares messages with role mapping', function () {
    // Create fake driver to avoid live API calls
    $driver = new FakeInferenceDriver([
        new InferenceResponse(message: \Cognesy\Messages\Message::asAssistant('mocked response')),
    ]);
    $inference = Inference::fromRuntime(
        \Cognesy\Polyglot\Inference\InferenceRuntime::fromProvider(
            \Cognesy\Polyglot\Inference\LLMProvider::new()->withDriver($driver),
        ),
    );
    
    $participant = new LLMParticipant(name: 'assistant-a', systemPrompt: 'You are assistant A', inference: $inference);
    
    // Create messages with mixed roles and participant names
    $m1 = new Message('assistant', 'Hi from A', name: 'assistant-a');
    $m2 = new Message('assistant', 'Hi from B', name: 'assistant-b');
    $userMessage = new Message('user', 'Hello');
    $messages = new Messages($userMessage, $m1, $m2);
    
    $state = new ChatState(store: MessageStore::fromMessages($messages));
    
    // Test the participant's prepareMessages method (protected, so we test via act)
    $step = $participant->act($state);
    $inputMessages = $step->inputMessages();
    $arr = $inputMessages->all();

    // Should have system prompt prepended
    expect($arr[0]->role()->value)->toBe('system');
    expect($arr[0]->content()->toString())->toBe('You are assistant A');

    // User message should remain user
    expect($arr[1]->role()->value)->toBe('user');
    expect($arr[1]->content()->toString())->toBe('Hello');
    
    // Own message should be assistant
    expect($arr[2]->role()->value)->toBe('assistant');
    expect($arr[2]->content()->toString())->toBe('Hi from A');
    
    // Other assistant's message should become user
    expect($arr[3]->role()->value)->toBe('user');
    expect($arr[3]->content()->toString())->toBe('Hi from B');
});
