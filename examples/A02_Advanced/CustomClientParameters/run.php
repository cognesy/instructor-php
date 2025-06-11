---
title: 'Customize parameters of LLM driver'
docname: 'custom_config'
---

## Overview

You can provide your own LLM configuration instance to Instructor. This is useful
when you want to initialize OpenAI client with custom values - e.g. to call
other LLMs which support OpenAI API.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Config\Env;
use Cognesy\Events\EventDispatcher;
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Drivers\Symfony\SymfonyDriver;
use Cognesy\Http\HttpClientBuilder;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Symfony\Component\HttpClient\HttpClient as SymfonyHttpClient;

class User {
    public int $age;
    public string $name;
}

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

// Create instance of LLM connection preset initialized with custom parameters

$llmConfig = new LLMConfig(
    apiUrl  : 'https://api.deepseek.com',
    apiKey  : Env::get('DEEPSEEK_API_KEY'),
    endpoint: '/chat/completions', defaultModel: 'deepseek-chat', defaultMaxTokens: 128, driver: 'openai-compatible',
);

// Get Instructor with the default client component overridden with your own

$structuredOutput = (new StructuredOutput)
    ->withEventBus($events)
    ->withLLMConfig($llmConfig)
    ->withHttpClient($customClient);

// Call with custom model and execution mode

$user = $structuredOutput
    ->wiretap(fn($e) => $e->print())
    ->with("Our user Jason is 25 years old.")
    ->withResponseClass(User::class)
    ->withOutputMode(OutputMode::Tools)
    ->withStreaming()
    ->get();

dump($user);

assert(isset($user->name));
assert(isset($user->age));
?>
```
