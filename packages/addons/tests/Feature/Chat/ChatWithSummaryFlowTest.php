<?php declare(strict_types=1);

use Cognesy\Addons\Chat\ChatFactory;
use Cognesy\Addons\Chat\Collections\Participants;
use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Addons\Chat\Participants\ScriptedParticipant;
use Cognesy\Addons\StepByStep\Continuation\ContinuationCriteria;
use Cognesy\Addons\StepByStep\Continuation\Criteria\StepsLimit;

it('builds up a conversation with multiple turns', function () {
    // Use scripted participants for deterministic behavior
    $userPrompts = [
        'Help me get better sales results.',
        'What should I do next?',
        'Give me one more actionable tip.',
    ];
    
    $assistantResponses = [
        'Sure, focus on discovery calls.',
        'Next: qualify prospects rigorously.',
        'Tip: set clear next steps.',
    ];

    $user = new ScriptedParticipant(name: 'user', messages: $userPrompts);
    $assistant = new ScriptedParticipant(name: 'assistant', messages: $assistantResponses);

    $participants = new Participants($user, $assistant);
    $continuationCriteria = new ContinuationCriteria(new StepsLimit(6, fn(ChatState $state): int => $state->stepCount()));
    
    $chat = ChatFactory::default(
        participants: $participants,
        continuationCriteria: $continuationCriteria
    );
    
    $state = new ChatState();

    // Run the conversation
    while ($chat->hasNextStep($state)) {
        $state = $chat->nextStep($state);
    }

    $messages = $state->messages()->toArray();

    // Expect alternating user / assistant messages
    expect(count($messages))->toBe(6);
    for ($i = 0; $i < 3; $i++) {
        $userMsg = $messages[$i*2];
        $assistantMsg = $messages[$i*2+1];
        expect($userMsg['role'])->toBe('user');
        expect($userMsg['content'])->toBe($userPrompts[$i]);
        expect($assistantMsg['role'])->toBe('user'); // ScriptedParticipant always returns 'user' role
        expect($assistantMsg['content'])->toBe($assistantResponses[$i]);
    }
});
