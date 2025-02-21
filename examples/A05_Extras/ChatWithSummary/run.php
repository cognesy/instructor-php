---
title: 'Chat with summary'
docname: 'chat_with_summary'
---

## Overview


## Example


```php
<?php

use Cognesy\Addons\Chat\Pipelines\ChatWithSummary;
use Cognesy\Addons\Chat\Utils\SummarizeMessages;
use Cognesy\Polyglot\LLM\Inference;
use Cognesy\Polyglot\LLM\LLM;
use Cognesy\Utils\Messages\Message;
use Cognesy\Utils\Messages\Messages;

$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

die(); // TO BE FIXED

$maxSteps = 5;
$sys = [
    'You are helpful assistant explaining Challenger Sale method, you answer questions. Provide very brief answers, not more than one sentence. Simplify things, don\'t go into details, but be very pragmatic and focused on practical bizdev problems.',
    'You are curious novice growth expert working to promote Instructor library, you keep asking questions. Use your knowledge of Instructor library and marketing of tech products for developers. Ask short, simple questions. Always ask a single question.',
];
$startMessage = new Message('assistant', 'Help me get better sales results. Be brief and concise.');

$context = "# CONTEXT\n\n" . file_get_contents(__DIR__ . '/summary.md');

$summarizer = new SummarizeMessages(
    //prompt: 'Summarize the messages.',
    llm: LLM::connection('deepseek'),
    //model: 'gpt-4o-mini',
    tokenLimit: 1024,
);

//$chat = new ChatWithSummary(
//    null,
//    256,
//    256,
//    1024,
//    true,
//    true,
//    $summarizer,
//);

$chat = ChatWithSummary::create(
    256,
    256,
    1024,
    $summarizer,
);
$chat->script()->section('main')->appendMessage($startMessage);

//Debug::enable();

for($i = 0; $i < $maxSteps; $i++) {
    $chat->script()
        ->section('system')
        ->withMessages(Messages::fromString($sys[$i % 2], 'system'));
    $chat->script()
        ->section('context')
        ->withMessages(Messages::fromString($context, 'system'));

    $messages = $chat->script()
        ->select(['system', 'context', 'summary', 'buffer', 'main'])
        ->toMessages()
        ->remapRoles(['assistant' => 'user', 'user' => 'assistant', 'system' => 'system']);

    dump($messages->toRoleString());

    $response = Inference::text(
        messages: $messages->toArray(),
        connection: 'deepseek',
        options: ['max_tokens' => 256],
    );

    echo "\n";
    dump('>>> '.$response);
    echo "\n";
    $chat->appendMessage(new Message(role: 'assistant', content: $response), 'main');
}
//dump($chat->script());
