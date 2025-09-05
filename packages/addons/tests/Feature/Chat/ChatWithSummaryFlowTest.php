<?php declare(strict_types=1);

use Cognesy\Addons\Chat\Chat;
use Cognesy\Addons\Chat\ContinuationCriteria\StepsLimit;
use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Addons\Chat\Participants\LLMParticipant;
use Cognesy\Messages\Messages;
use Cognesy\Messages\Script\Script;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Inference;
use Tests\Addons\Support\FakeInferenceDriver;

it('builds up a conversation with user prompts and assistant replies', function () {
    // Prepare deterministic assistant responses
    $assistantResponses = [
        'Sure, focus on discovery calls.',
        'Next: qualify prospects rigorously.',
        'Tip: set clear next steps.',
    ];

    $driver = new FakeInferenceDriver(array_map(
        fn(string $txt) => new InferenceResponse(content: $txt),
        $assistantResponses,
    ));
    $inference = (new Inference())
        ->withLLMProvider(\Cognesy\Polyglot\Inference\LLMProvider::new()->withDriver($driver));

    // Build chat state with required sections
    $script = (new Script())
        ->withSection('system')
        ->withSection('context')
        ->withSection('summary')
        ->withSection('buffer')
        ->withSection('main');
    $state = new ChatState($script);

    // Use only assistant participant; user messages are appended directly to chat
    $assistant = new LLMParticipant(
        id: 'assistant',
        inference: $inference,
        model: 'fake',
        sectionOrder: ['summary','buffer','main'],
    );

    $chat = new Chat(state: $state, continuationCriteria: [new StepsLimit(3)]);
    $chat->withParticipants([$assistant]);

    $userPrompts = [
        'Help me get better sales results.',
        'What should I do next?',
        'Give me one more actionable tip.',
    ];

    // Loop: append user message, then let assistant reply
    foreach ($userPrompts as $i => $prompt) {
        $chat->withMessages(Messages::fromString($prompt, 'user'));
        $chat->nextTurn();
    }

    $main = $chat->state()->script()->section('main')->toMessages()->toArray();

    // Expect alternating user / assistant messages
    expect(count($main))->toBe(6);
    for ($i = 0; $i < 3; $i++) {
        $u = $main[$i*2];
        $a = $main[$i*2+1];
        expect($u['role'])->toBe('user');
        expect($u['content'])->toBe($userPrompts[$i]);
        expect($a['role'])->toBe('assistant');
        expect($a['content'])->toBe($assistantResponses[$i]);
    }
});

