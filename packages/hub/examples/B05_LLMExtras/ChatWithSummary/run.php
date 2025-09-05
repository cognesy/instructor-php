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
$sys = [
    'You are helpful assistant explaining Challenger Sale method, you answer questions. Provide very brief answers, not more than one sentence. Simplify things, don\'t go into details, but be very pragmatic and focused on practical bizdev problems.',
    'You are curious novice growth expert working to promote Instructor library, you keep asking questions. Use your knowledge of Instructor library and marketing of tech products for developers. Ask short, simple questions. Always ask a single question.'
];
$startMessage = new Message('assistant', 'Help me get better sales results. Be brief and concise.');

$context = "# CONTEXT\n\n" . file_get_contents(__DIR__ . '/summary.md');

$summarizer = new SummarizeMessages(
    //prompt: 'Summarize the messages.',
    llm: LLMProvider::using('openai'),
    //model: 'gpt-4o-mini',
    tokenLimit: 1024,
);

$chat = BuildChatWithSummary::create(
    maxChatTokens: 256,
    maxBufferTokens: 256,
    maxSummaryTokens: 1024,
    summarizer: $summarizer,
);
$chat = $chat->appendMessage($startMessage);

for($i = 0; $i < $maxSteps; $i++) {
    $script = $chat->script()
        ->withSection('system')
        ->withSection('context')
        ->withSectionMessages('system', Messages::fromString($sys[$i % 2], 'system'))
        ->withSectionMessages('context', Messages::fromString($context, 'system'));
    $chat = $chat->withScript($script);

        $messages = $chat->state()->script()
        ->select(['system', 'context', 'summary', 'buffer', 'main'])
        ->toMessages();

    $chat->withMessages(Messages::fromString($sys[$i % 2], 'user'));
    $step = $chat->nextTurn();
    $response = $step->messages()->toString();

    echo "\n";
    dump('>>> '.$response);
    echo "\n";
    // response is already appended by Chat orchestrator
}

dump($chat->script());
