---
title: 'Customize parameters of LLM driver'
docname: 'custom_llm'
---

## Overview

You can provide your own LLM configuration instance to Instructor. This is useful
when you want to initialize OpenAI client with custom values - e.g. to call
other LLMs which support OpenAI API.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Http\Data\HttpClientConfig;
use Cognesy\Http\Drivers\SymfonyDriver;
use Cognesy\Http\HttpClient;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Polyglot\LLM\Data\LLMConfig;
use Cognesy\Polyglot\LLM\Enums\OutputMode;
use Cognesy\Utils\Env;
use Cognesy\Utils\Events\EventDispatcher;
use Symfony\Component\HttpClient\HttpClient as SymfonyHttpClient;

class User {
    public int $age;
    public string $name;
}

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

// Create instance of LLM connection preset initialized with custom parameters

$llmConfig = new LLMConfig(
    apiUrl: 'https://api.deepseek.com',
    apiKey: Env::get('DEEPSEEK_API_KEY'),
    endpoint: '/chat/completions',
    model: 'deepseek-chat',
    maxTokens: 128,
    providerType: 'openai-compatible',
);

// Get Instructor with the default client component overridden with your own

$structuredOutput = (new StructuredOutput)
    ->withEventDispatcher($events)
    ->withEventListener($events) // optional - if you want to register custom event listeners
    ->withLLMConfig($llmConfig)
    ->withHttpClient($customClient);

// Call with custom model and execution mode

$user = $structuredOutput
    ->wiretap(fn($e) => $e->print())
    ->with(
        messages: "Our user Jason is 25 years old.",
        responseModel: User::class,
        mode: OutputMode::Tools,
    )
    ->withStreaming()
    ->get();

dump($user);

assert(isset($user->name));
assert(isset($user->age));
?>
```
