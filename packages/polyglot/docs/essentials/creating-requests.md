---
title: Creating Requests
description: Build inference requests with messages, models, and options using the fluent API.
meta:
  - name: 'has_code'
    content: true
---

Polyglot provides a clean, fluent API for building inference requests. You can configure
messages, models, tools, response formats, and provider-specific options -- all through
a consistent interface that works across every supported LLM provider.

## Basic Request

The simplest way to get a response is to pass a string message directly:

```php
<?php
use Cognesy\Polyglot\Inference\Inference;

$response = Inference::using('openai')
    ->withMessages('What is the capital of France?')
    ->get();

echo $response; // "Paris."
```

A plain string is automatically wrapped as a user message before it reaches the provider.

## The `with()` Method

When you need to configure multiple request fields at once, use the combined `with()` method.
It accepts all core request parameters in a single call:

```php
<?php
use Cognesy\Polyglot\Inference\Inference;

$text = Inference::using('openai')
    ->with(
        messages: [
            ['role' => 'system', 'content' => 'Answer briefly.'],
            ['role' => 'user', 'content' => 'What is CQRS?'],
        ],
        model: 'gpt-4.1-nano',
        options: ['temperature' => 0.2],
    )
    ->get();
```

All parameters on `with()` are optional -- pass only what you need:

| Parameter        | Type                    | Description                                |
|------------------|-------------------------|--------------------------------------------|
| `messages`       | `string\|array\|null`   | The messages to send to the LLM            |
| `model`          | `?string`               | Model identifier (overrides preset default)|
| `tools`          | `ToolDefinitions\|array\|null` | Tool/function definitions for the model    |
| `toolChoice`     | `ToolChoice\|string\|array\|null` | Tool selection preference                  |
| `responseFormat` | `ResponseFormat\|array\|null` | Response format specification              |
| `options`        | `?array`                | Provider-specific request options          |

## Focused Helper Methods

For better readability, use the dedicated fluent helpers instead of packing everything
into a single `with()` call:

```php
<?php
use Cognesy\Polyglot\Inference\Inference;

$response = Inference::using('anthropic')
    ->withModel('claude-sonnet-4-20250514')
    ->withMessages([
        ['role' => 'system', 'content' => 'You are a helpful assistant who provides concise answers.'],
        ['role' => 'user', 'content' => 'What is the capital of France?'],
        ['role' => 'assistant', 'content' => 'Paris.'],
        ['role' => 'user', 'content' => 'And what about Germany?'],
    ])
    ->withOptions(['temperature' => 0.5])
    ->get();
```

Each helper returns a new immutable instance, so you can safely branch from a shared base:

```php
$base = Inference::using('openai')->withModel('gpt-4.1-nano');

$creative = $base->withOptions(['temperature' => 0.9]);
$precise  = $base->withOptions(['temperature' => 0.0]);
```

The full list of fluent helpers:

- `withMessages(...)` -- set the conversation messages
- `withModel(...)` -- override the model
- `withTools(...)` -- attach tool/function definitions
- `withToolChoice(...)` -- control tool selection
- `withResponseFormat(...)` -- specify the response format
- `withOptions(...)` -- set provider-specific options
- `withStreaming(...)` -- enable or disable streaming
- `withMaxTokens(...)` -- set the maximum token count
- `withCachedContext(...)` -- attach reusable cached context
- `withRetryPolicy(...)` -- configure retry behavior
- `withResponseCachePolicy(...)` -- configure response caching

## Using the `Messages` Class

For complex conversations, use the `Messages` class to build message sequences with
a more expressive API:

```php
<?php
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Inference;

$messages = (new Messages)
    ->asSystem('You are a senior PHP8 backend developer.')
    ->asDeveloper('Be concise and use modern PHP8.2+ features.')
    ->asUser([
        'What is the best way to handle errors in PHP8?',
        'Provide a code example.',
    ]);

$response = Inference::using('openai')
    ->withModel('gpt-4.1-nano')
    ->withMessages($messages)
    ->get();
```

> The `asDeveloper()` method maps to OpenAI's developer role and is automatically
> normalized for providers that do not support it.

## Message Formats

Polyglot accepts messages in several formats, giving you flexibility based on your needs:

- **String** -- automatically converted to a single user message
- **Array of messages** -- each element should have `role` and `content` keys
- **`Messages` object** -- built using the fluent Messages API
- **`Message` object** -- a single message instance

### Multimodal Content

For providers that support vision, you can include images in your messages:

```php
<?php
use Cognesy\Polyglot\Inference\Inference;

$imageData = base64_encode(file_get_contents('image.jpg'));
$messages = [
    [
        'role' => 'user',
        'content' => [
            [
                'type' => 'text',
                'text' => 'What\'s in this image?',
            ],
            [
                'type' => 'image_url',
                'image_url' => [
                    'url' => "data:image/jpeg;base64,$imageData",
                ],
            ],
        ],
    ],
];

$response = Inference::using('openai')
    ->withModel('gpt-4o')
    ->withMessages($messages)
    ->get();
```

## Using `InferenceRequest` Directly

If your application already constructs request objects -- for example, when deserializing
stored requests or building them in a pipeline -- you can pass them in directly:

```php
<?php
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Inference;

$request = new InferenceRequest(
    messages: 'Return one deployment tip.',
    model: 'gpt-4.1-nano',
    options: ['temperature' => 0],
);

$text = Inference::using('openai')
    ->withRequest($request)
    ->get();
```

`InferenceRequest` objects are immutable value objects. Use `with()` or the dedicated
mutators (`withMessages()`, `withModel()`, etc.) to derive modified copies:

```php
$updated = $request->with(
    model: 'gpt-4.1',
    options: ['temperature' => 0.7],
);
```
