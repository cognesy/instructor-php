---
title: Quickstart
description: 'Get up and running with Polyglot in under 5 minutes.'
meta:
  - name: 'has_code'
    content: true
---

This guide walks you through installing Polyglot and making your first LLM
inference request. By the end you will have a working PHP script that sends a
prompt to an LLM provider and prints the response.

> Polyglot is already included in the Instructor for PHP package. If you have
> Instructor installed, you do not need to install Polyglot separately.


## Installation

Polyglot requires **PHP 8.3** or later. Install it with Composer:

```bash
composer require cognesy/instructor-polyglot
```


## Configure Your API Key

Polyglot ships with ready-made presets for over 25 providers including OpenAI,
Anthropic, Gemini, Mistral, Groq, Deepseek, and many more. Each preset reads
its API key from an environment variable so credentials never appear in code.

For this quickstart we will use the `openai` preset. Export your key before
running any PHP code:

```bash
export OPENAI_API_KEY=sk-...
```

<Warning>
    Never hard-code API keys in source files. Use environment variables or a
    <code>.env</code> file to keep credentials out of version control.
</Warning>


## Your First Request

Create a file called `test-polyglot.php` in your project directory:

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Messages\Messages;

$answer = Inference::using('openai')
    ->withMessages(Messages::fromString('What is the capital of France?'))
    ->get();

echo "ASSISTANT: $answer\n";
```

Run it from the terminal:

```bash
php test-polyglot.php

# Output:
# ASSISTANT: The capital of France is Paris.
```

That is all it takes. The `Inference` class is the main entry point for every
LLM request in Polyglot.


## Understanding the Flow

Every Polyglot request follows a three-step pattern:

1. **Select a provider** -- call `Inference::using('preset')` to choose a
   bundled or custom preset, or create an `Inference` instance directly.
2. **Build the request** -- chain fluent methods such as `withMessages()`,
   `withModel()`, `withMaxTokens()`, or `withOptions()` to describe what you
   want.
3. **Execute** -- call a terminal method to send the request and retrieve the
   result.

The available terminal methods are:

| Method | Returns | Description |
|---|---|---|
| `get()` | `string` | The plain-text content of the response. |
| `response()` | `InferenceResponse` | The full response object with content, usage, tool calls, and metadata. |
| `asJson()` | `string` | The response content parsed as a JSON string. |
| `asJsonData()` | `array` | The response content parsed into a PHP array. |
| `stream()` | `InferenceStream` | A streamed response you can iterate over in real time. |


## Switching Providers

Because every provider is just a preset name, switching from OpenAI to another
provider is a one-line change:

```php
use Cognesy\Messages\Messages;

// Anthropic
$text = Inference::using('anthropic')
    ->withMessages(Messages::fromString('Explain dependency injection in one paragraph.'))
    ->get();

// Google Gemini
$text = Inference::using('gemini')
    ->withMessages(Messages::fromString('Explain dependency injection in one paragraph.'))
    ->get();

// Groq
$text = Inference::using('groq')
    ->withMessages(Messages::fromString('Explain dependency injection in one paragraph.'))
    ->get();
```

Set the corresponding environment variable for each provider you want to use
(`ANTHROPIC_API_KEY`, `GEMINI_API_KEY`, `GROQ_API_KEY`, and so on).


## Overriding the Model

The preset defines a default model, but you can override it per-request:

```php
use Cognesy\Messages\Messages;

$text = Inference::using('openai')
    ->withModel('gpt-4.1')
    ->withMessages(Messages::fromString('Summarize the theory of relativity in two sentences.'))
    ->get();
```


## Streaming Responses

For long responses or interactive UIs, you can stream the output token by token:

```php
use Cognesy\Messages\Messages;

$stream = Inference::using('openai')
    ->withMessages(Messages::fromString('Write a short poem about PHP.'))
    ->stream();

foreach ($stream->deltas() as $delta) {
    echo $delta->contentDelta;
}
```


## Next Steps

Now that you have a working setup, explore the rest of the documentation to
unlock the full power of Polyglot:

- **[Setup](setup)** -- define your own presets and customize provider
  configuration.
- **[Essentials](essentials/overview)** -- learn the complete request and
  response API.
- **[Streaming](streaming/overview)** -- handle streamed responses, events,
  and partial updates.
- **[Embeddings](embeddings/overview)** -- generate vector embeddings for
  semantic search and RAG.
