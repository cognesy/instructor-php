---
title: Inference Class
description: 'How to use the Inference class in Polyglot for LLM requests'
---

The `Inference` class is the primary facade for making requests to LLM providers in Polyglot.
It is a thin request facade: runtime/provider/http/event assembly lives in `InferenceRuntime`.


## Architecture Overview

The `Inference` facade focuses on:
- **Request builder methods** (`withMessages`, `withModel`, `withOptions`, etc.)
- **Execution shortcuts** (`get`, `response`, `stream`, `asJson`, `asJsonData`)
- **Runtime handoff** via `runtime()` / `withRuntime()`


## Basic Usage

```php
<?php
use Cognesy\Polyglot\Inference\Inference;

// Simple text completion
$response = (new Inference())
    ->withMessages('What is the capital of France?')
    ->get();

// Using a specific provider
$response = Inference::using('openai')
    ->withMessages('Explain quantum physics')
    ->get();
```


## Runtime Selection

Create a facade with constructor sugar or inject a custom runtime:

```php
use Cognesy\Polyglot\Inference\InferenceRuntime;

$inference = Inference::using('openai');               // Preset-based runtime
$inference = Inference::fromDsn('preset=openai,model=gpt-4o-mini');
$inference = Inference::fromRuntime(
    InferenceRuntime::using('openai')
);
```


## Request Building Methods

Configure the inference request:

```php
// Message configuration
$inference->withMessages('Hello, world!');           // String message
$inference->withMessages(['user' => 'Hello']);       // Array format
$inference->withMessages($messageObject);            // Message object

// Model and generation parameters
$inference->withModel('gpt-4');                      // Specific model
$inference->withMaxTokens(100);                      // Token limit
$inference->withOutputMode($outputMode);             // Response format mode

// Tool usage
$inference->withTools($toolDefinitions);             // Available tools
$inference->withToolChoice('auto');                  // Tool selection strategy

// Response formatting
$inference->withResponseFormat(['type' => 'json']); // Response format
$inference->withOptions(['temperature' => 0.5]);    // Additional options

// Advanced features
$inference->withStreaming(true);                     // Enable streaming
$inference->withCachedContext($messages, $tools);   // Context caching
```


## Invocation Methods

Execute inference requests:

```php
// Flexible configuration method
$inference->with(
    messages: 'Hello',
    model: 'gpt-4',
    tools: [],
    toolChoice: 'auto',
    responseFormat: ['type' => 'text'],
    options: ['temperature' => 0.7],
    mode: OutputMode::Text
);

// Create pending inference for advanced handling
$pending = $inference->create();

// Direct request execution
$inference->withRequest($existingRequest);
```

## Runtime-First Usage (`CanCreateInference`)

For constructor-injected creators, call `runtime()` and execute explicit requests:

```php
<?php
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Inference;

$creator = Inference::using('openai')->runtime();

$request = new InferenceRequest(
    messages: 'What is the capital of France?',
    model: 'gpt-4o-mini',
);

$response = $creator->create($request)->get();
```


## Response Shortcuts

Get responses in different formats:

```php
// Text responses
$text = $inference->get();                           // Plain text
$response = $inference->response();                  // Full InferenceResponse object

// JSON responses  
$json = $inference->asJson();                        // JSON string
$data = $inference->asJsonData();                    // Parsed array

// Streaming
$stream = $inference->stream();                      // InferenceStream object
```


## Driver Registration

Register custom drivers for new providers:

```php
// Register with class name
Inference::registerDriver('custom-provider', CustomDriver::class);

// Register with factory callable
Inference::registerDriver('custom-provider', function($config, $httpClient) {
    return new CustomDriver($config, $httpClient);
});
```
