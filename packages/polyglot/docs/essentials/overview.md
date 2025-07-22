---
title: Overview of Inference
description: How to use LLM inference API
---

`Inference` class offers access to LLM APIs and convenient methods to execute
model inference, incl. chat completions, tool calling or JSON output
generation.

LLM providers access details can be found and modified via `/config/llm.php`.



## Simple Text Generation

The simplest way to use Polyglot is to generate text using static `Inference::text()` method.
Simplified inference API uses the default connection for convenient ad-hoc calls.

```php
<?php
use Cognesy\Polyglot\Inference\Inference;

// Generate text using the default connection
$answer = (new Inference)->with(messages: 'What is the capital of France?')->get();

echo "Answer: $answer";

// Output: Answer: The capital of France is Paris.
```

This static method uses the default connection specified in your configuration.
Default LLM connection can be configured via config/llm.php.


## Creating an Inference Object

For more control, you can create an instance of the `Inference` class:

```php
<?php
use Cognesy\Polyglot\Inference\Inference;

// Create an inference object
$inference = new Inference();

// Generate text using the default connection
$answer = $inference->with(
    messages: [['role' => 'user', 'content' => 'What is the capital of France?']]
)->get();

echo "Answer: $answer";
```


## Specifying a Connection

You can specify which connection preset to use:

```php
<?php
use Cognesy\Polyglot\Inference\Inference;

// Create an inference object with a specific connection
$inference = new Inference();
$answer = $inference->using('anthropic')
    ->with(
        messages: [['role' => 'user', 'content' => 'What is the capital of France?']]
    )->get();

echo "Answer (using Anthropic): $answer";
```


## Creating Chat Conversations

For multi-turn conversations, provide an array of messages:

```php
<?php
use Cognesy\Polyglot\Inference\Inference;

// Create a chat conversation
$messages = [
    ['role' => 'user', 'content' => 'Hello, can you help me with a math problem?'],
    ['role' => 'assistant', 'content' => 'Of course! I\'d be happy to help with a math problem. What would you like to solve?'],
    ['role' => 'user', 'content' => 'What is the square root of 144?'],
];

$inference = new Inference();
$answer = $inference->with(
    messages: $messages
)->get();

echo "Answer: $answer";
```


## Customizing Request Parameters

You can customize various parameters for your requests:

```php
<?php
use Cognesy\Polyglot\Inference\Inference;

// Create an inference with custom options
$inference = new Inference();
$answer = $inference->with(
    messages: [['role' => 'user', 'content' => 'Write a short poem about coding.']],
    model: 'gpt-4', // Override the default model
    options: [
        'temperature' => 0.7,
        'max_tokens' => 100,
    ]
)->get();

echo "Poem: $answer";
```



## Fluent API

Regular inference API allows you to customize inference options, letting you set values specific for a given LLM provider.

Most of the provider options are compatible with OpenAI API.

This example shows how to create an inference object, specify a connection and generate text using the `create()` method.
The `toText()` method returns text completion from the LLM response.

```php
<?php
use Cognesy\Polyglot\Inference\Inference;

$answer = (new Inference)
    ->using('openai') // optional, default is set in /config/llm.php
    ->withMessages([['role' => 'user', 'content' => 'What is capital of France']])
    ->withOptions(['max_tokens' => 64])
    ->get();

echo "USER: What is capital of France\n";
echo "ASSISTANT: $answer\n";
```


## Streaming inference results

Inference API allows streaming responses, which is useful for building more responsive UX
as you can display partial responses from LLM as soon as they arrive, without waiting until
the whole response is ready.

```php
<?php
use Cognesy\Polyglot\Inference\Inference;

$stream = (new Inference)
    ->withMessages([['role' => 'user', 'content' => 'Describe capital of Brasil']])
    ->withOptions(['max_tokens' => 512])
    ->withStreaming()
    ->stream()
    ->responses();

echo "USER: Describe capital of Brasil\n";
echo "ASSISTANT: ";
foreach ($stream as $partial) {
    echo $partial->contentDelta;
}
echo "\n";
```


## Connecting to a specific LLM API provider

Instructor allows you to define multiple API connections in `llm.php` file.
This is useful when you want to use different LLMs or API providers in your application.

Default configuration is located in `/config/llm.php` in the root directory
of Instructor codebase. It contains a set of predefined connections to all LLM APIs
supported out-of-the-box by Instructor.

Config file defines connections to LLM APIs and their parameters. It also specifies
the default connection to be used when calling Instructor without specifying
the client connection.

```php
    // This is fragment of /config/llm.php file
    'defaultPreset' => 'openai',
    //...
    'presets' => [
        'anthropic' => [ ... ],
        'azure' => [ ... ],
        'cohere' => [ ... ],
        'fireworks' => [ ... ],
        'gemini' => [ ... ],
        'xai' => [ ... ],
        'groq' => [ ... ],
        'mistral' => [ ... ],
        'ollama' => [
            'driver' => 'ollama',
            'apiUrl' => 'http://localhost:11434/v1',
            'apiKey' => Env::get('OLLAMA_API_KEY', ''),
            'endpoint' => '/chat/completions',
            'model' => 'qwen2.5:0.5b',
            'maxTokens' => 1024,
            'httpClientPreset' => 'guzzle-ollama', // use custom HTTP client configuration
        ],
        'openai' => [ ... ],
        'openrouter' => [ ... ],
        'together' => [ ... ],
    // ...
```
To customize the available connections you can either modify existing entries or
add your own.

Connecting to LLM API via predefined connection is as simple as calling `withPreset`
method with the connection preset name.

```php
<?php
// ...
$answer = (new Inference)
    ->using('ollama') // see /config/llm.php
    ->with(
        messages: [['role' => 'user', 'content' => 'What is the capital of France']],
        options: ['max_tokens' => 64]
    )
    ->get();
// ...
```

You can change the location of the configuration files for Instructor to use via
`INSTRUCTOR_CONFIG_PATHS` environment variable. You can use copies of the default
configuration files as a starting point.



## Switching Between Providers

Polyglot makes it easy to switch between different LLM providers at runtime.

### Using Different Providers for LLM Requests

```php
<?php
use Cognesy\Polyglot\Inference\Inference;

// Create an inference object
$inference = new Inference();

// Use the default provider (set in config)
$defaultResponse = $inference->with(
    messages: 'What is the capital of France?'
)->get();

echo "Default provider response: $defaultResponse\n\n";

// Switch to Anthropic
$anthropicResponse = $inference->using('anthropic')
    ->with(
        messages: 'What is the capital of Germany?'
    )->get();

echo "Anthropic response: $anthropicResponse\n\n";

// Switch to Mistral
$mistralResponse = $inference->using('mistral')
    ->with(
        messages: 'What is the capital of Italy?'
    )->get();

echo "Mistral response: $mistralResponse\n\n";

// You can create a new instance for each provider
$openAI = new Inference('openai');
$anthropic = new Inference('anthropic');
$mistral = new Inference('mistral');

// And use them independently
$responses = [
    'openai' => $openAI->with(messages: 'What is the capital of Spain?')->get(),
    'anthropic' => $anthropic->with(messages: 'What is the capital of Portugal?')->get(),
    'mistral' => $mistral->with(messages: 'What is the capital of Greece?')->get(),
];

foreach ($responses as $provider => $response) {
    echo "$provider response: $response\n";
}
```


## Selecting Different Models

Each provider offers multiple models with different capabilities, context lengths, and pricing. Polyglot lets you override the default model for each request.

### Specifying Models for LLM Requests

```php
<?php
use Cognesy\Polyglot\Inference\Inference;

$inference = new Inference('openai');

// Use the default model (set in config)
$defaultModelResponse = $inference->with(
    messages: 'What is machine learning?'
)->get();

// Use a specific model
$specificModelResponse = $inference->with(
    messages: 'What is machine learning?',
    model: 'gpt-4o'  // Override the default model
)->get();

// You can also set the model and other options
$customResponse = $inference->with(
    messages: 'What is machine learning?',
    model: 'gpt-4-turbo',
    options: [
        'temperature' => 0.7,
        'max_tokens' => 500,
    ]
)->get();
```
