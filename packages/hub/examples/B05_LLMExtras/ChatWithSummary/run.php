---
title: 'Chat with summary'
docname: 'chat_with_summary'
---

## Overview


## Example


```php
<?php

require 'examples/boot.php';

use Cognesy\Addons\Chat\Pipelines\BuildChatWithSummary;
use Cognesy\Addons\Chat\Utils\SummarizeMessages;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\LLMProvider;

$maxSteps = 5;

$system = 'You are a helpful assistant explaining Challenger Sale. Be very brief (one sentence), pragmatic and focused on practical bizdev problems.';
$context = "# CONTEXT\n\n" . file_get_contents(__DIR__ . '/summary.md');

$summarizer = new SummarizeMessages(
    llm: LLMProvider::using('openai'),
    tokenLimit: 1024,
);

// Build a Chat with summary + buffer processors and an assistant participant
$chat = BuildChatWithSummary::create(
    maxChatTokens: 256,
    maxBufferTokens: 256,
    maxSummaryTokens: 1024,
    summarizer: $summarizer,
    model: 'gpt-4o-mini',
);

// Add system + persistent context once
$store = $chat->state()->script()
    ->withSectionMessages('system', Messages::fromString($system, 'system'))
    ->withSectionMessages('context', Messages::fromString($context, 'system'));
$state = $chat->state();
$state->withMessageStore($store);
$chat->withState($state);

$userPrompts = [
    'Help me get better sales results.',
    'What should I do next?',
    'Give me one more actionable tip.',
];

for ($i = 0; $i < $maxSteps; $i++) {
    $prompt = $userPrompts[$i % count($userPrompts)];
    // Append user message, then let assistant produce a reply
    $chat->withMessages(Messages::fromString($prompt, 'user'));
    $step = $chat->nextTurn();

    echo "\nUser:  {$prompt}\n";
    echo   "AI:    ".$step->messages()->toString()."\n";
}

// Show that older content has been summarized into the summary section
echo "\n--- Summary (compressed history) ---\n";
echo $chat->state()->script()->section('summary')->toMessages()->toString()."\n";
?>
```
