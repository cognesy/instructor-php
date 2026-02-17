---
title: 'Customize configuration of LLM driver'
docname: 'llm_custom_config'
id: '59ef'
---
## Overview

You can provide your own LLM configuration instance to `Inference` object. This is useful
when you want to initialize LLM client with custom values.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Config\Env;
use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Events\Event;
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Drivers\Symfony\SymfonyDriver;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Utils\Str;
use Symfony\Component\HttpClient\HttpClient as SymfonyHttpClient;

$events = new EventDispatcher();

// Build fully customized HTTP client

$httpConfig = new HttpClientConfig(
    connectTimeout: 30,
    requestTimeout: 60,
    idleTimeout: -1,
    maxConcurrent: 5,
    poolTimeout: 60,
    failOnError: true,
);

$yourClientInstance = SymfonyHttpClient::create(['http_version' => '2.0']);

$customClient = (new HttpClientBuilder)
    ->withEventBus($events)
    ->withDriver(new SymfonyDriver(
        config: $httpConfig,
        clientInstance: $yourClientInstance,
        events: $events,
    ))
    ->create();

// Create instance of LLM client initialized with custom parameters

$config = new LLMConfig(
    apiUrl  : 'https://api.deepseek.com',
    apiKey  : Env::get('DEEPSEEK_API_KEY'),
    endpoint: '/chat/completions',
    model: 'deepseek-chat',
    maxTokens: 128,
    driver: 'deepseek',
);

// Call inference API with custom client and configuration

$answer = (new Inference)
    ->withEventHandler($events)
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
