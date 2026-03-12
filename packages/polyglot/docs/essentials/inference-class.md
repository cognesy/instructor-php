---
title: Inference Class
description: The primary facade for making LLM requests -- creation, configuration, and execution.
---

The `Inference` class is a thin, immutable facade over `InferenceRuntime`. It provides the
unified entry point for configuring providers, building requests, and retrieving responses
from any supported LLM.

## Creating an Instance

Choose the factory method that matches your level of control:

```php
<?php
use Cognesy\Polyglot\Inference\Inference;

// Use a named preset from your configuration
$inference = Inference::using('openai');

// Use the default provider
$inference = new Inference();

// Explicit configuration object
$inference = Inference::fromConfig($config);

// From a provider instance
$inference = Inference::fromProvider($provider);

// From a fully assembled runtime
$inference = Inference::fromRuntime($runtime);
```

### Presets

The most common pattern is `Inference::using()`, which loads a named preset from your
configuration files. Each preset defines the provider type, API key, base URL, default
model, and other connection details:

```php
// Use different providers by switching the preset name
$openai    = Inference::using('openai');
$anthropic = Inference::using('anthropic');
$ollama    = Inference::using('ollama');
```

## Configuring a Request

The fluent API lets you build requests step by step. Every method returns a new immutable
instance, so you can safely branch from a shared configuration:

### Messages and Model

```php
$inference = Inference::using('openai')
    ->withMessages(Messages::fromString('Explain dependency injection in one paragraph.'))
    ->withModel('gpt-4.1-nano');
```

### Tools and Response Format

```php
use Cognesy\Polyglot\Inference\Data\ToolChoice;
use Cognesy\Polyglot\Inference\Data\ResponseFormat;

$inference = Inference::using('openai')
    ->withTools($toolDefinitions)
    ->withToolChoice(ToolChoice::auto())
    ->withResponseFormat(ResponseFormat::jsonObject());
```

### Streaming and Token Limits

```php
$inference = Inference::using('openai')
    ->withStreaming(true)
    ->withMaxTokens(256);
```

### Provider-Specific Options

```php
$inference = Inference::using('openai')
    ->withOptions(['temperature' => 0.5, 'top_p' => 0.9]);
```

### The Combined `with()` Method

When you prefer a single call, use `with()` to set multiple fields at once:

```php
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\ToolChoice;
use Cognesy\Polyglot\Inference\Data\ResponseFormat;

$inference = Inference::using('openai')->with(
    messages: Messages::fromString('Hello'),
    model: 'gpt-4.1-nano',
    toolChoice: ToolChoice::auto(),
    responseFormat: ResponseFormat::text(),
    options: ['temperature' => 0.7],
);
```

### Full Method Reference

| Method                        | Purpose                                       |
|-------------------------------|-----------------------------------------------|
| `withMessages(...)`           | Set conversation messages                     |
| `withModel(...)`              | Override the model                            |
| `withTools(...)`              | Attach tool/function definitions              |
| `withToolChoice(...)`         | Control tool selection strategy               |
| `withResponseFormat(...)`     | Specify the response format                   |
| `withOptions(...)`            | Set provider-specific options                 |
| `withStreaming(...)`          | Enable or disable streaming                   |
| `withMaxTokens(...)`         | Set maximum token count                       |
| `withCachedContext(...)`      | Attach reusable cached context                |
| `withRetryPolicy(...)`       | Configure retry behavior                      |
| `withResponseCachePolicy(...)` | Configure response caching                  |
| `withRequest(...)`           | Load all fields from an `InferenceRequest`    |
| `withRuntime(...)`           | Replace the underlying runtime                |

## Executing Requests

### Response Shortcuts

These methods build the request, execute it, and return the result in a single step:

```php
<?php
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Messages\Messages;

$inference = Inference::using('openai')
    ->withMessages(Messages::fromString('What is PHP?'))
    ->withModel('gpt-4.1-nano');

// Plain text content
$text = $inference->get();

// Full InferenceResponse object (with usage, finish reason, etc.)
$response = $inference->response();

// JSON string extracted from the response
$json = $inference->asJson();

// Parsed JSON as an associative array
$data = $inference->asJsonData();

// JSON from a tool call response
$toolJson = $inference->asToolCallJson();

// Parsed tool call JSON as an array
$toolData = $inference->asToolCallJsonData();
```

### Streaming

To receive partial results as they arrive from the provider:

```php
$stream = Inference::using('openai')
    ->withMessages(Messages::fromString('Write a short story about a robot.'))
    ->stream();

foreach ($stream->deltas() as $partial) {
    echo $partial->contentDelta;
}
```

### The Lazy Handle: `PendingInference`

If you need to defer execution or pass the handle to another part of your system,
call `create()` to get a `PendingInference` instance. Execution happens only when
you call a response method on it:

```php
$pending = Inference::using('openai')
    ->withMessages(Messages::fromString('Hello'))
    ->create();

// Nothing has been sent to the provider yet.
// Execution happens here:
$text = $pending->get();
```

`PendingInference` exposes the same response methods as `Inference`: `get()`,
`response()`, `asJson()`, `asJsonData()`, `asToolCallJson()`, `asToolCallJsonData()`,
and `stream()`.

## Custom Drivers

To use a custom driver, implement the `CanProvideInferenceDrivers` contract and pass
it to `Inference::using()` or `Inference::fromConfig()` via the `drivers` parameter:

```php
use Cognesy\Polyglot\Inference\Inference;

$response = Inference::using('custom-provider', drivers: $myDriverRegistry)
    ->withMessages(Messages::fromString('Hello from a custom driver.'))
    ->get();
```
