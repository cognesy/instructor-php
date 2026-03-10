---
title: Overview of Inference
description: How to use the Inference facade for LLM API access, text generation, streaming, and provider switching.
---

The `Inference` class is the main facade for interacting with LLM APIs. It provides
a clean, immutable interface for chat completions, tool calling, JSON output generation,
and streaming -- all through a consistent API regardless of the underlying provider.

## Quick Start

The simplest way to generate text is with a single chained call:

```php
<?php
use Cognesy\Polyglot\Inference\Inference;

$answer = Inference::using('openai')
    ->withMessages('What is the capital of France?')
    ->get();
```

The `using()` static method resolves a named preset from your configuration, while
`withMessages()` accepts plain text, a single message, a message array, or a `Messages` object.
The `get()` method executes the request and returns the response content as a string.


## Creating an Inference Instance

For more control over the lifecycle, create an instance directly. Without arguments,
Inference uses a sensible default configuration:

```php
<?php
use Cognesy\Polyglot\Inference\Inference;

$inference = new Inference();

$answer = $inference
    ->withMessages([['role' => 'user', 'content' => 'Explain event sourcing briefly.']])
    ->get();
```

You may also use the `with()` method, which accepts all request parameters at once:

```php
<?php
use Cognesy\Polyglot\Inference\Inference;

$answer = (new Inference)->with(
    messages: 'What is the capital of France?',
)->get();
```


## Core Request Fields

The `with()` method and its individual `with...()` counterparts allow you to set every
aspect of the inference request:

| Field | Method | Description |
|-------|--------|-------------|
| `messages` | `withMessages()` | The conversation messages |
| `model` | `withModel()` | Override the model defined in the preset |
| `tools` | `withTools()` | Tool/function definitions for the model to call |
| `toolChoice` | `withToolChoice()` | Control which tool the model should use |
| `responseFormat` | `withResponseFormat()` | Request structured output (e.g. JSON schema) |
| `options` | `withOptions()` | Provider-specific parameters (`temperature`, `max_tokens`, etc.) |
| `maxTokens` | `withMaxTokens()` | Shorthand for setting the maximum output token count |


## Execution Paths

Once you have configured a request, choose how to execute it:

| Method | Returns | Use case |
|--------|---------|----------|
| `get()` | `string` | Quick text extraction |
| `response()` | `InferenceResponse` | Full response with metadata, usage stats, and tool calls |
| `asJson()` | `string` | Extract JSON from the response content |
| `asJsonData()` | `array` | Decode JSON from the response into a PHP array |
| `asToolCallJson()` | `string` | Extract tool call arguments as a JSON string |
| `asToolCallJsonData()` | `array` | Decode tool call arguments into a PHP array |
| `stream()` | `InferenceStream` | Stream partial deltas as they arrive |


## Multi-Turn Conversations

For multi-turn conversations, pass an array of messages with role annotations:

```php
<?php
use Cognesy\Polyglot\Inference\Inference;

$messages = [
    ['role' => 'user', 'content' => 'Can you help me with a math problem?'],
    ['role' => 'assistant', 'content' => 'Of course! What would you like to solve?'],
    ['role' => 'user', 'content' => 'What is the square root of 144?'],
];

$answer = Inference::using('openai')
    ->withMessages($messages)
    ->get();
```


## Customizing Request Options

Provider-specific parameters such as `temperature`, `max_tokens`, or `top_p` are
passed through the `options` array. Most providers follow the OpenAI-compatible
parameter conventions:

```php
<?php
use Cognesy\Polyglot\Inference\Inference;

$answer = Inference::using('openai')
    ->withMessages('Write a short poem about coding.')
    ->withModel('gpt-4o')
    ->withOptions(['temperature' => 0.7, 'max_tokens' => 200])
    ->get();
```

You can also set all parameters at once via the `with()` convenience method:

```php
<?php
use Cognesy\Polyglot\Inference\Inference;

$answer = Inference::using('openai')->with(
    messages: 'Write a haiku about PHP.',
    model: 'gpt-4o',
    options: ['temperature' => 0.9, 'max_tokens' => 100],
)->get();
```


## Streaming Responses

Streaming lets you display partial output as it arrives from the model, creating
a more responsive user experience. Call `stream()` to get an `InferenceStream`,
then iterate over deltas:

```php
<?php
use Cognesy\Polyglot\Inference\Inference;

$stream = Inference::using('openai')
    ->withMessages('Describe the capital of Brasil.')
    ->withMaxTokens(512)
    ->stream();

foreach ($stream->deltas() as $delta) {
    echo $delta->contentDelta;
}
```

Each `PartialInferenceDelta` exposes the `contentDelta` string for the incremental
text fragment. The stream also provides functional-style helpers -- `map()`, `filter()`,
and `reduce()` -- for processing deltas inline.

You can also register a callback to handle each delta as it arrives:

```php
<?php
use Cognesy\Polyglot\Inference\Inference;

$stream = Inference::using('openai')
    ->withMessages('Tell me a story.')
    ->stream();

$stream->onDelta(fn($delta) => print($delta->contentDelta));

// Drain the stream to trigger callbacks
$stream->all();
```

After the stream completes, call `final()` to retrieve the assembled
`InferenceResponse` with full content and usage statistics.


## Working with the Full Response

When you need more than just text, use `response()` to access the complete
`InferenceResponse` object:

```php
<?php
use Cognesy\Polyglot\Inference\Inference;

$response = Inference::using('openai')
    ->withMessages('What is quantum computing?')
    ->response();

$text = $response->content();
$usage = $response->usage();
$finishReason = $response->finishReason();
```

The response object provides access to content, reasoning content (for models that
support chain-of-thought), tool calls, token usage statistics, and the raw HTTP
response data.


## Switching Between Providers

Polyglot ships with YAML-based presets for many providers. Switching between them
is a single method call:

```php
<?php
use Cognesy\Polyglot\Inference\Inference;

$question = 'What is the capital of France?';

$openai = Inference::using('openai')->withMessages($question)->get();
$anthropic = Inference::using('anthropic')->withMessages($question)->get();
$gemini = Inference::using('gemini')->withMessages($question)->get();
```

Available presets include `openai`, `anthropic`, `gemini`, `mistral`, `groq`,
`ollama`, `fireworks`, `together`, `openrouter`, `cohere`, `deepseek`, `xai`,
`azure`, `perplexity`, `sambanova`, and others. Each preset is defined in a
YAML file under `resources/config/llm/presets/`.


## Configuring Presets

Each preset is a YAML file that defines the connection parameters for a provider.
For example, the OpenAI preset:

```yaml
driver: openai
apiUrl: 'https://api.openai.com/v1'
apiKey: '${OPENAI_API_KEY}'
endpoint: /chat/completions
model: gpt-4.1-nano
maxTokens: 1024
contextLength: 1000000
maxOutputLength: 16384
```

Polyglot resolves presets from several locations, searched in order:

1. `config/llm/presets/` (your project root)
2. `packages/polyglot/resources/config/llm/presets/` (monorepo)
3. `vendor/cognesy/instructor-php/packages/polyglot/resources/config/llm/presets/`
4. `vendor/cognesy/instructor-polyglot/resources/config/llm/presets/`

To customize a provider, copy the relevant YAML file into `config/llm/presets/`
at your project root and modify it as needed. Environment variables are referenced
with the `${VAR_NAME}` syntax.


## Selecting a Model

Each preset defines a default model, but you can override it per-request:

```php
<?php
use Cognesy\Polyglot\Inference\Inference;

$answer = Inference::using('openai')
    ->withMessages('Explain machine learning in one sentence.')
    ->withModel('gpt-4o')
    ->get();
```


## Immutability

`Inference` is immutable from the caller's perspective. Every `with...()` method
returns a new instance, leaving the original unchanged. This makes it safe to
build a base configuration and derive specialized variants from it:

```php
<?php
use Cognesy\Polyglot\Inference\Inference;

$base = Inference::using('openai')->withOptions(['temperature' => 0.3]);

$precise = $base->withModel('gpt-4o');
$fast = $base->withModel('gpt-4.1-mini');
```

Both `$precise` and `$fast` inherit the temperature setting without affecting
each other or the `$base` instance.
