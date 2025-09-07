<?php declare(strict_types=1);

use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Addons\Chat\Participants\LLMParticipant;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;

it('llm participant prepares messages with role mapping', function () {
    $participant = new LLMParticipant(name: 'assistant-a', systemPrompt: 'You are assistant A');
    
    // Create messages with mixed roles and participant names
    $m1 = new Message('assistant', 'Hi from A', name: 'assistant-a');
    $m2 = new Message('assistant', 'Hi from B', name: 'assistant-b');
    $userMessage = new Message('user', 'Hello');
    $messages = new Messages($userMessage, $m1, $m2);
    
    $state = new ChatState(messages: $messages);
    
    // Test the participant's prepareMessages method (protected, so we test via act)
    $step = $participant->act($state);
    $inputMessages = $step->inputMessages();
    $arr = $inputMessages->toArray();

    // Should have system prompt prepended
    expect($arr[0]['role'])->toBe('system');
    expect($arr[0]['content'])->toBe('You are assistant A');

    // User message should remain user
    expect($arr[1]['role'])->toBe('user');
    expect($arr[1]['content'])->toBe('Hello');
    
    // Own message should be assistant
    expect($arr[2]['role'])->toBe('assistant');
    expect($arr[2]['content'])->toBe('Hi from A');
    
    // Other assistant's message should become user
    expect($arr[3]['role'])->toBe('user');
    expect($arr[3]['content'])->toBe('Hi from B');
});
