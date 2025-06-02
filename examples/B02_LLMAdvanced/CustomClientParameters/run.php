---
title: 'Customize parameters of LLM driver'
docname: 'custom_llm'
---

## Overview

You can provide your own LLM configuration instance to `Inference` object. This is useful
when you want to initialize LLM client with custom values.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Http\Data\HttpClientConfig;
use Cognesy\Http\Drivers\SymfonyDriver;
use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\LLM\Data\LLMConfig;
use Cognesy\Polyglot\LLM\Inference;
use Cognesy\Utils\Env;
use Cognesy\Utils\Events\Event;
use Cognesy\Utils\Events\EventDispatcher;
use Cognesy\Utils\Str;
use Symfony\Component\HttpClient\HttpClient as SymfonyHttpClient;

$events = new EventDispatcher();

// Build fully customized HTTP client

$httpConfig = new HttpClientConfig(
    connectTimeout: 5,
    requestTimeout: 60,
    idleTimeout: -1,
    maxConcurrent: 5,
    poolTimeout: 60,
    failOnError: true,
);

$yourClientInstance = SymfonyHttpClient::create(['http_version' => '2.0']);

$customClient = (new HttpClient)
    ->withEventDispatcher($events)
    ->withEventListener($events) // optional - if you want to register custom event listeners
    ->withDriver(new SymfonyDriver(
        config: $httpConfig,
        clientInstance: $yourClientInstance,
        events: $events,
    ));

// Create instance of LLM client initialized with custom parameters

$config = new LLMConfig(
    apiUrl: 'https://api.deepseek.com',
    apiKey: Env::get('DEEPSEEK_API_KEY'),
    endpoint: '/chat/completions',
    model: 'deepseek-chat',
    maxTokens: 128,
    providerType: 'deepseek',
);

// Call inference API with custom client and configuration

$answer = (new Inference)
    ->withEventDispatcher($events)
    ->withEventListener($events) // optional - if you want to register custom event listeners
    ->withConfig($config)
    ->withHttpClient($customClient)
    ->wiretap(fn(Event $e) => $e->print())
    ->with(
        messages: [['role' => 'user', 'content' => 'What is the capital of France']],
        options: ['max_tokens' => 64]
    )
    ->withStreaming()
    ->get();

echo "USER: What is capital of France\n";
echo "ASSISTANT: $answer\n";

assert(Str::contains($answer, 'Paris'));
?>
```
