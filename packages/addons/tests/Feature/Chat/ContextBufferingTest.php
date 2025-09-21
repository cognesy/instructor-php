<?php declare(strict_types=1);

use Cognesy\Addons\Chat\ChatFactory;
use Cognesy\Addons\Chat\Collections\Participants;
use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Addons\Chat\Participants\ExternalParticipant;
use Cognesy\Addons\Core\Continuation\ContinuationCriteria;
use Cognesy\Addons\Core\Continuation\Criteria\StepsLimit;
use Cognesy\Messages\Message;

it('processes messages with context processors', function () {
    $human = new ExternalParticipant(
        name: 'user', 
        provider: fn() => new Message(role: 'user', content: 'Long message: ' . str_repeat('x', 100))
    );

    $participants = new Participants($human);
    $continuationCriteria = new ContinuationCriteria(new StepsLimit(1, fn(ChatState $state): int => $state->stepCount()));
    
    $chat = ChatFactory::default(
        participants: $participants,
        continuationCriteria: $continuationCriteria
    );
    
    $state = new ChatState();
    $state = $chat->nextStep($state);

    $messages = $state->messages()->toArray();
    
    expect(count($messages))->toBe(1);
    expect($messages[0]['content'])->toContain('Long message:');
    expect($messages[0]['content'])->toContain(str_repeat('x', 100));
});
