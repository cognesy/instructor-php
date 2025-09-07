---
title: 'Chat with summary'
docname: 'chat_with_summary'
---

## Overview


## Example


```php
<?php

require 'examples/boot.php';

use Cognesy\Addons\Chat\Chat;
use Cognesy\Addons\Chat\ContinuationCriteria\StepsLimit;
use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Addons\Chat\Data\Collections\ContinuationCriteria;
use Cognesy\Addons\Chat\Data\Collections\Participants;
use Cognesy\Addons\Chat\Data\Collections\StepProcessors;
use Cognesy\Addons\Chat\Participants\LLMParticipant;
use Cognesy\Addons\Chat\Participants\ScriptedParticipant;
use Cognesy\Addons\Chat\Processors\AccumulateTokenUsage;
use Cognesy\Addons\Chat\Processors\AddCurrentStep;
use Cognesy\Addons\Chat\Processors\AppendStepMessages;
use Cognesy\Addons\Chat\Processors\MoveMessagesToBuffer;
use Cognesy\Addons\Chat\Processors\SummarizeBuffer;
use Cognesy\Addons\Chat\Utils\SummarizeMessages;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\LLMProvider;

$maxSteps = 5;

$student = new ScriptedParticipant(
    name: 'student',
    messages: [
        'Help me get better sales results.',
        'What should I do next?',
        'Give me one more actionable tip.',
    ],
);

$expert = new LLMParticipant(
    name: 'expert',
    llmProvider: LLMProvider::using('openai'),
    systemPrompt: 'You are a helpful assistant explaining Challenger Sale. Be very brief (one sentence), pragmatic and focused on practical bizdev problems.'
);

$context = "# CONTEXT\n\n" . file_get_contents(__DIR__ . '/summary.md');

$summarizer = new SummarizeMessages(
    llm: LLMProvider::using('openai'),
    tokenLimit: 1024,
);

// Build a Chat with summary + buffer processors and an assistant participant
$chat = Chat::default(
    participants: new Participants($student, $expert),
    continuationCriteria: new ContinuationCriteria(
        new StepsLimit(6),
    ),
    stepProcessors: new StepProcessors(
        new AccumulateTokenUsage(),
        new AddCurrentStep(),
        new AppendStepMessages(),
        new MoveMessagesToBuffer(maxTokens: 512, bufferVariable: 'buffer'),
        new SummarizeBuffer(maxBufferTokens: 1024, maxSummaryTokens: 512, bufferVariable: 'buffer', summarizer: $summarizer),
    ),
); //->wiretap(fn(Event $e) => $e->print());

$state = (new ChatState)->withMessages(Messages::fromString(content: $context, role: 'system'));

while ($chat->hasNextTurn($state)) {
    $state = $chat->nextTurn($state);
    $step = $state->currentStep();
    $name = $step?->participantName() ?? 'unknown';
    $content = trim($step?->outputMessage()->toString() ?? '');
    echo "\n--- Step " . ($state->stepCount()) . " ($name) ---\n";
    echo $content . "\n";
}

?>
```
