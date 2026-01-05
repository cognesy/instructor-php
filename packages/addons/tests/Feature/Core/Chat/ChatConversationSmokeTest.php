<?php declare(strict_types=1);

use Cognesy\Addons\Chat\ChatFactory;
use Cognesy\Addons\Chat\Collections\Participants;
use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Addons\Chat\Participants\LLMParticipant;
use Cognesy\Addons\Chat\Participants\ScriptedParticipant;
use Cognesy\Addons\StepByStep\Continuation\ContinuationCriteria;
use Cognesy\Addons\StepByStep\Continuation\Criteria\ResponseContentCheck;
use Cognesy\Addons\StepByStep\Continuation\Criteria\StepsLimit;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\LLMProvider;
use Tests\Addons\Support\FakeInferenceDriver;

it('runs multi-participant chat with proper turn-taking and conversation history', function () {
    // Setup participants similar to the ChatWithManyParticipants example
    $moderator = new ScriptedParticipant(
        name: 'moderator',
        messages: [
            "Welcome! Please introduce yourselves.",
            "What's your main expertise?",
            "", // Empty to end conversation
        ],
    );

    $researcher = new LLMParticipant(
        name: 'dr_chen',
        inference: (new Inference())->withLLMProvider(
            LLMProvider::new()->withDriver(
                new FakeInferenceDriver([
                    new InferenceResponse(content: 'I am Dr. Chen, AI researcher. - Dr. Chen'),
                    new InferenceResponse(content: 'My expertise is machine learning safety. - Dr. Chen'),
                ])
            )
        ),
        systemPrompt: 'You are Dr. Chen, AI researcher. Always end with "- Dr. Chen"'
    );

    $engineer = new LLMParticipant(
        name: 'marcus',
        inference: (new Inference())->withLLMProvider(
            LLMProvider::new()->withDriver(
                new FakeInferenceDriver([
                    new InferenceResponse(content: 'I am Marcus, Senior AI Engineer. - Marcus'),
                    new InferenceResponse(content: 'I focus on production AI systems. - Marcus'),
                ])
            )
        ),
        systemPrompt: 'You are Marcus, AI engineer. Always end with "- Marcus"'
    );

    $participants = new Participants($moderator, $engineer, $researcher);

    // Use the same continuation criteria as the fixed example
    $continuationCriteria = new ContinuationCriteria(
        new StepsLimit(8, fn(ChatState $state): int => $state->stepCount()),
        new ResponseContentCheck(
            fn(ChatState $state): ?Messages => $state->currentStep()?->outputMessages(),
            static fn(Messages $lastResponse): bool => $lastResponse->last()->content()->toString() !== '',
        ),
    );

    $chat = ChatFactory::default(
        participants: $participants,
        continuationCriteria: $continuationCriteria
    );

    $state = new ChatState();
    $conversationHistory = [];
    $participantOrder = [];

    // Run the conversation and track the flow
    while ($chat->hasNextStep($state)) {
        $state = $chat->nextStep($state);
        $step = $state->currentStep();

        if ($step) {
            $participantName = $step->participantName();
            $content = trim($step->outputMessages()->toString());

            $participantOrder[] = $participantName;
            $conversationHistory[] = [
                'participant' => $participantName,
                'content' => $content,
                'stepCount' => $state->stepCount(),
            ];
        }
    }

    // Verify conversation flow
    expect(count($conversationHistory))->toBeGreaterThan(3);

    // Verify each participant took turns
    expect($conversationHistory[0]['participant'])->toBe('moderator');
    expect($conversationHistory[0]['content'])->toBe('Welcome! Please introduce yourselves.');

    // Verify responses from AI participants
    $drChenSteps = array_filter($conversationHistory, fn($step) => $step['participant'] === 'dr_chen');
    $marcusSteps = array_filter($conversationHistory, fn($step) => $step['participant'] === 'marcus');

    expect(count($drChenSteps))->toBeGreaterThan(0);
    expect(count($marcusSteps))->toBeGreaterThan(0);

    // Verify AI responses contain expected signatures
    foreach ($drChenSteps as $step) {
        expect($step['content'])->toContain('- Dr. Chen');
    }

    foreach ($marcusSteps as $step) {
        expect($step['content'])->toContain('- Marcus');
    }

    // Verify conversation builds up properly
    $finalMessages = $state->messages()->toArray();
    $nonEmptyHistory = array_filter(
        $conversationHistory,
        static fn(array $step): bool => trim($step['content']) !== ''
    );
    expect(count($finalMessages))->toBe(count($nonEmptyHistory));

    // Verify step count increments properly
    $stepCounts = array_column($conversationHistory, 'stepCount');
    for ($i = 1; $i < count($stepCounts); $i++) {
        expect($stepCounts[$i])->toBe($stepCounts[$i-1] + 1);
    }

    // Verify participants alternate (not strict order, but no immediate repeats in this simple case)
    $uniqueParticipants = array_unique($participantOrder);
    expect(count($uniqueParticipants))->toBeGreaterThan(1);
});

it('stops conversation when ResponseContentCheck detects empty response', function () {
    $moderator = new ScriptedParticipant(
        name: 'moderator',
        messages: ["Hello", "Goodbye"],
    );

    $assistant = new LLMParticipant(
        name: 'assistant',
        inference: (new Inference())->withLLMProvider(
            LLMProvider::new()->withDriver(
                new FakeInferenceDriver([
                    new InferenceResponse(content: 'Hi there!'),
                    new InferenceResponse(content: ''), // Empty response should stop
                ])
            )
        )
    );

    $participants = new Participants($moderator, $assistant);

    $continuationCriteria = new ContinuationCriteria(
        new StepsLimit(10, fn(ChatState $state): int => $state->stepCount()),
        new ResponseContentCheck(
            fn(ChatState $state): ?Messages => $state->currentStep()?->outputMessages(),
            static fn(Messages $lastResponse): bool => $lastResponse->last()->content()->toString() !== '',
        ),
    );

    $chat = ChatFactory::default(
        participants: $participants,
        continuationCriteria: $continuationCriteria
    );

    $state = new ChatState();
    $stepCount = 0;

    while ($chat->hasNextStep($state) && $stepCount < 5) {
        $state = $chat->nextStep($state);
        $stepCount++;
    }

    // Should stop after empty response, not reach step limit
    expect($stepCount)->toBeLessThan(5);
    expect($state->stepCount())->toBeGreaterThan(1);

    $messages = $state->messages()->toArray();
    expect(count($messages))->toBeGreaterThan(1);
});
