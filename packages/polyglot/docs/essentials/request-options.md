---
title: Request Options
description: Customize provider-specific request fields, retry policies, caching, and advanced options.
---

The `options` array is the escape hatch for provider-specific request fields that fall
outside Polyglot's unified API. Anything you place in `options` is passed through to
the underlying provider driver.

> **Note:** Except for `max_tokens` and `stream`, all option keys are provider-specific
> and may not be available or behave identically across providers. Always consult the
> provider's API documentation for details.

## Setting Options

Pass options through `withOptions()` or the `options` parameter on `with()`:

```php
<?php
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Messages\Messages;

$text = Inference::using('openai')
    ->withMessages(Messages::fromString('Write one short sentence about PHP.'))
    ->withOptions([
        'temperature' => 0.2,
        'top_p' => 0.9,
    ])
    ->get();
```

Options are merged additively -- calling `withOptions()` multiple times will merge the
new keys into the existing set rather than replacing it.

## Common Options

These options are widely supported across most providers:

```php
$options = [
    'temperature' => 0.7,         // Controls randomness (0.0 to 1.0)
    'max_tokens' => 1000,         // Maximum tokens to generate
    'top_p' => 0.95,              // Nucleus sampling parameter
    'frequency_penalty' => 0.0,   // Penalize repeated tokens
    'presence_penalty' => 0.0,    // Penalize repeated topics
    'stop' => ["\n\n", "User:"],  // Stop sequences
];
```

## Dedicated Helpers Over Raw Options

For common behaviors, prefer the dedicated fluent helpers instead of manually placing
values in the `options` array. The helpers ensure correct handling across all providers:

| Instead of this...                         | Use this...                   |
|--------------------------------------------|-------------------------------|
| `withOptions(['stream' => true])`          | `withStreaming(true)`         |
| `withOptions(['max_tokens' => 256])`       | `withMaxTokens(256)`         |
| `withOptions(['retryPolicy' => [...]])`    | `withRetryPolicy($policy)`   |

These helpers set values that the request builder manages separately from the raw
options array, ensuring they are applied correctly regardless of the provider.

## Provider-Specific Options

Different providers accept different option keys. Here are a few examples:

### OpenAI

```php
$response = Inference::using('openai')
    ->withMessages(Messages::fromString('Write a poem about programming.'))
    ->withOptions([
        'temperature' => 0.7,
        'top_p' => 0.9,
        'frequency_penalty' => 0.5,
        'presence_penalty' => 0.3,
    ])
    ->get();
```

### Anthropic

```php
$response = Inference::using('anthropic')
    ->withMessages(Messages::fromString('Write a poem about programming.'))
    ->withOptions([
        'temperature' => 0.7,
        'top_p' => 0.9,
        'top_k' => 40,
    ])
    ->get();
```

## Retry Policy

Retry behavior is configured explicitly through `withRetryPolicy()` -- never place it
inside the `options` array. Polyglot will throw an `InvalidArgumentException` if you do.

```php
<?php
use Cognesy\Polyglot\Inference\Config\InferenceRetryPolicy;
use Cognesy\Polyglot\Inference\Inference;

$retryPolicy = new InferenceRetryPolicy(
    maxAttempts: 3,
    baseDelayMs: 500,
    maxDelayMs: 8000,
    jitter: 'full',               // none, full, or equal
    retryOnStatus: [429, 500, 502, 503, 504],
);

$response = Inference::using('openai')
    ->withMessages(Messages::fromString('Summarize this article.'))
    ->withRetryPolicy($retryPolicy)
    ->get();
```

The retry policy supports exponential backoff with configurable jitter and can also
recover from truncated responses:

| Parameter              | Default             | Description                                     |
|------------------------|---------------------|-------------------------------------------------|
| `maxAttempts`          | `1`                 | Total attempts (1 = no retry)                   |
| `baseDelayMs`         | `250`               | Base delay in milliseconds                      |
| `maxDelayMs`          | `8000`              | Maximum delay cap                               |
| `jitter`              | `'full'`            | Jitter strategy: `none`, `full`, or `equal`     |
| `retryOnStatus`       | `[408,429,500,...]` | HTTP status codes that trigger a retry          |
| `retryOnExceptions`   | Timeout, Network    | Exception classes that trigger a retry          |
| `lengthRecovery`      | `'none'`            | Recovery mode: `none`, `continue`, `increase_max_tokens` |
| `lengthMaxAttempts`   | `1`                 | Max attempts for length recovery                |
| `lengthContinuePrompt` | `'Continue.'`     | Prompt used for `continue` recovery mode        |
| `maxTokensIncrement`  | `512`               | Token increment for `increase_max_tokens` mode  |

## Response Cache Policy

Control whether responses are cached in memory for reuse:

```php
<?php
use Cognesy\Polyglot\Inference\Enums\ResponseCachePolicy;
use Cognesy\Polyglot\Inference\Inference;

$response = Inference::using('openai')
    ->withMessages(Messages::fromString('What is 2 + 2?'))
    ->withResponseCachePolicy(ResponseCachePolicy::Memory)
    ->get();
```

Available policies:

- `ResponseCachePolicy::None` -- no caching (default)
- `ResponseCachePolicy::Memory` -- cache responses in memory for the current process

## Cached Context

Use `withCachedContext()` to attach stable, reusable context that should be prepended
to every request. This is useful when you have shared system instructions, tool
definitions, or response formats that remain constant across multiple calls:

```php
<?php
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\ToolChoice;
use Cognesy\Polyglot\Inference\Data\ResponseFormat;

$base = Inference::using('openai')->withCachedContext(
    messages: Messages::fromArray([
        ['role' => 'system', 'content' => 'You are an expert PHP developer.'],
    ]),
    tools: $sharedToolDefinitions,
    toolChoice: ToolChoice::auto(),
    responseFormat: ResponseFormat::jsonObject(),
);

// Each call inherits the cached context automatically
$response1 = $base->withMessages(Messages::fromString('Explain SOLID principles.'))->get();
$response2 = $base->withMessages(Messages::fromString('What is the Repository pattern?'))->get();
```

When the request is executed, cached context is merged with the request-level fields:
cached messages are prepended to the request messages, and cached tools, tool choice,
and response format are used as defaults when the request does not specify its own.

Drivers can map cached context to provider-native caching features (such as Anthropic's
prompt caching) when available.
