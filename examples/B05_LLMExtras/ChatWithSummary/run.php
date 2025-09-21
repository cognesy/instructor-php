---
title: 'Chat with summary'
docname: 'chat_with_summary'
---

## Overview


## Example


```php
<?php

require 'examples/boot.php';

use Cognesy\Addons\Chat\ChatFactory;
use Cognesy\Addons\Chat\Collections\Participants;
use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Addons\Chat\Participants\LLMParticipant;
use Cognesy\Addons\Chat\Participants\ScriptedParticipant;
use Cognesy\Addons\Chat\Utils\SummarizeMessages;
use Cognesy\Addons\Core\Continuation\ContinuationCriteria;
use Cognesy\Addons\Core\Continuation\Criteria\ResponseContentCheck;
use Cognesy\Addons\Core\Continuation\Criteria\StepsLimit;
use Cognesy\Addons\Core\Processors\AccumulateTokenUsage;
use Cognesy\Addons\Core\Processors\AppendStepMessages;
use Cognesy\Addons\Core\Processors\MoveMessagesToBuffer;
use Cognesy\Addons\Core\Processors\SummarizeBuffer;
use Cognesy\Addons\Core\StateProcessors;
use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\LLMProvider;

$events = new EventDispatcher();

$student = new ScriptedParticipant(
    name: 'student',
    messages: [
        'Help me get better sales results.',
        'What should I do next?',
        'Give me one more actionable tip.',
        'How could I apply this in practice?',
        "What are some common pitfalls to avoid?",
        'Any final advice?',
        '' // Empty string to signal end of conversation
    ],
);

$expert = new LLMParticipant(
    name: 'expert',
    llmProvider: LLMProvider::using('openai'),
    systemPrompt: 'You are a helpful assistant explaining Challenger Sale. Be very brief (one sentence), pragmatic and focused on practical bizdev problems.'
);

// Build a Chat with summary + buffer processors and an assistant participant
$chat = ChatFactory::default(
    participants: new Participants($student, $expert),
    continuationCriteria: new ContinuationCriteria(
        new StepsLimit(12, fn(ChatState $state): int => $state->stepCount()),
        new ResponseContentCheck(
            fn(ChatState $state): ?Messages => $state->currentStep()?->outputMessages(),
            static fn(Messages $lastResponse): bool => $lastResponse->toString() !== '',
        ),
    ),
    stepProcessors: new StateProcessors(
        new AccumulateTokenUsage(),
        new AppendStepMessages(),
        new MoveMessagesToBuffer(
            maxTokens: 1024,
            bufferSection: 'buffer',
            events: $events
        ),
        new SummarizeBuffer(
            maxBufferTokens: 128,
            maxSummaryTokens: 512,
            bufferSection: 'buffer',
            summarySection: 'summary',
            summarizer: new SummarizeMessages(llm: LLMProvider::using('openai')),
            events: $events,
        ),
    ),
    events: $events,
); //->wiretap(fn(Event $e) => $e->print());

$context = "# CONTEXT\n\n" . file_get_contents(__DIR__ . '/summary.md');

$state = (new ChatState)->withMessages(
    Messages::fromString(content: $context, role: 'system')
);

while ($chat->hasNextStep($state)) {
    $state = $chat->nextStep($state);
    $step = $state->currentStep();

    $name = $step?->participantName() ?? 'unknown';
    $content = trim($step?->outputMessages()->toString() ?? '');
    echo "\n--- Step " . ($state->stepCount()) . " ($name) ---\n";
    echo $content . "\n";
}

?>
```
