<?php declare(strict_types=1);

use Cognesy\Addons\Chat\ChatFactory;
use Cognesy\Addons\Chat\Collections\Participants;
use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Addons\Chat\Participants\LLMParticipant;
use Cognesy\Addons\Core\Continuation\ContinuationCriteria;
use Cognesy\Addons\Core\Continuation\Criteria\StepsLimit;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Messages\MessageStore\MessageStore;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\LLMProvider;
use Tests\Addons\Support\FakeInferenceDriver;

it('handles multi-participant chat with role mapping', function () {
    // Create two different assistants
    $driverA = new FakeInferenceDriver([
        new InferenceResponse(content: 'Response from A'),
    ]);
    $inferenceA = (new Inference())->withLLMProvider(LLMProvider::new()->withDriver($driverA));
    $assistantA = new LLMParticipant(name: 'assistantA', inference: $inferenceA, systemPrompt: 'You are assistant A');

    $driverB = new FakeInferenceDriver([
        new InferenceResponse(content: 'Response from B'),
    ]);
    $inferenceB = (new Inference())->withLLMProvider(LLMProvider::new()->withDriver($driverB));
    $assistantB = new LLMParticipant(name: 'assistantB', inference: $inferenceB, systemPrompt: 'You are assistant B');

    // Set up initial state with mixed messages
    $store = MessageStore::fromMessages(new Messages(
        new Message('user', 'Hello everyone'),
        new Message('assistant', 'Hi from A', name: 'assistantA'),
        new Message('assistant', 'Hi from B', name: 'assistantB'),
    ));

    $participants = new Participants($assistantA, $assistantB);
    $continuationCriteria = new ContinuationCriteria(new StepsLimit(2, fn(ChatState $state): int => $state->stepCount()));
    
    $chat = ChatFactory::default(
        participants: $participants,
        continuationCriteria: $continuationCriteria
    );
    
    $state = new ChatState(store: $store);

    // Run two turns
    $state = $chat->nextStep($state);
    $state = $chat->nextStep($state);

    $finalMessages = $state->messages()->toArray();
    
    // We should have original 3 + 2 new messages
    expect(count($finalMessages))->toBe(5);
    
    // Last two messages should be from the assistants
    expect($finalMessages[3]['content'])->toBe('Response from A');
    expect($finalMessages[4]['content'])->toBe('Response from B');
});
