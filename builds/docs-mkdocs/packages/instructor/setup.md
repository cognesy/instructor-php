---
title: Setup
description: 'Install the package and configure your LLM provider.'
---

Getting started with Instructor requires two things:

1. Install the `cognesy/instructor-struct` package
2. Provide LLM provider credentials


## Installation

```bash
composer require cognesy/instructor-struct
# @doctest id="8bdd"
```

> Instructor requires **PHP 8.3** or later.


## Providing API Keys

Instructor reads provider credentials from environment variables. The simplest approach
is to set them in your shell or a `.env` file at the root of your project:

```ini
# .env
OPENAI_API_KEY=sk-your-key-here
# @doctest id="b98e"
```

For other providers, set the corresponding variable:

```ini
ANTHROPIC_API_KEY=your-key
GEMINI_API_KEY=your-key
GROQ_API_KEY=your-key
MISTRAL_API_KEY=your-key
# @doctest id="cd82"
```

> Never commit API keys to version control. Add `.env` to your `.gitignore` file.


## Preset-Based Setup

Presets are the fastest way to get started. A preset name maps to a provider
configuration that reads credentials from the environment:

```php
use Cognesy\Instructor\StructuredOutput;

$result = StructuredOutput::using('openai')
    ->with(
        messages: 'What is the capital of France?',
        responseModel: City::class,
    )
    ->get();
// @doctest id="4013"
```

You can switch providers by changing the preset name:

```php
// Use Anthropic instead of OpenAI
$result = StructuredOutput::using('anthropic')
    ->with(
        messages: 'What is the capital of France?',
        responseModel: City::class,
    )
    ->get();
// @doctest id="d552"
```


## Explicit Provider Configuration

When you need full control over the driver, model, API base URL, or other connection
parameters, use `LLMConfig` directly:

```php
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Polyglot\Inference\Config\LLMConfig;

$result = StructuredOutput::fromConfig(
    LLMConfig::fromDsn('driver=openai,model=gpt-4o-mini')
)->with(
    messages: 'What is the capital of France?',
    responseModel: City::class,
)->get();
// @doctest id="7543"
```

You can also construct `LLMConfig` from an array for more detailed configuration:

```php
use Cognesy\Polyglot\Inference\Config\LLMConfig;

$config = LLMConfig::fromArray([
    'driver' => 'openai',
    'model' => 'gpt-4o-mini',
    'apiKey' => $_ENV['OPENAI_API_KEY'],
    'apiUrl' => 'https://api.openai.com/v1',
    'maxTokens' => 4096,
]);

$result = StructuredOutput::fromConfig($config)
    ->with(
        messages: 'What is the capital of France?',
        responseModel: City::class,
    )
    ->get();
// @doctest id="2a77"
```


## Runtime Configuration

`StructuredOutput` handles single requests. When you need to configure behavior that
applies across multiple requests -- retries, output mode, event listeners, or custom
pipeline extensions -- use `StructuredOutputRuntime`:

```php
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\StructuredOutputRuntime;
use Cognesy\Polyglot\Inference\Config\LLMConfig;

$runtime = StructuredOutputRuntime::fromConfig(
    LLMConfig::fromPreset('openai')
)->withMaxRetries(3);

$structured = (new StructuredOutput)->withRuntime($runtime);

$city = $structured
    ->with(
        messages: 'What is the capital of France?',
        responseModel: City::class,
    )
    ->get();
// @doctest id="ea9b"
```

### What Belongs Where

Understanding the separation of concerns helps you structure your application:

| Layer | Responsibility | Examples |
|-------|---------------|----------|
| `LLMConfig` | Provider connection details | Driver, model, API key, base URL, max tokens |
| `StructuredOutputConfig` | Extraction behavior | Output mode, retry prompt template, schema naming |
| `StructuredOutputRuntime` | Runtime behavior | Max retries, event listeners, custom validators/transformers |
| `StructuredOutput` | Single request | Messages, response model, system prompt, examples |


### Output Modes

Instructor supports multiple strategies for getting structured output from the LLM.
The default mode (`Tools`) uses the provider's function/tool calling API. You can switch
modes via the runtime:

```php
use Cognesy\Instructor\Enums\OutputMode;

$runtime = StructuredOutputRuntime::fromConfig(
    LLMConfig::fromPreset('openai')
)->withOutputMode(OutputMode::JsonSchema);
// @doctest id="f93c"
```

Available modes:

| Mode | Description |
|------|-------------|
| `OutputMode::Tools` | Uses the provider's tool/function calling API (default) |
| `OutputMode::Json` | Requests JSON output via the provider's JSON mode |
| `OutputMode::JsonSchema` | Sends a JSON Schema and requests strict conformance |
| `OutputMode::MdJson` | Asks the LLM to return JSON inside a Markdown code block |
| `OutputMode::Text` | Extracts JSON from unstructured text responses |
| `OutputMode::Unrestricted` | No output constraints; extraction is best-effort |


### Event Listeners

The runtime exposes a full event system for monitoring and debugging:

```php
use Cognesy\Instructor\Events\StructuredOutput\StructuredOutputRequestReceived;

$runtime = StructuredOutputRuntime::fromConfig(
    LLMConfig::fromPreset('openai')
);

// Listen for a specific event
$runtime->onEvent(
    StructuredOutputRequestReceived::class,
    fn($event) => logger()->info('Request received', $event->toArray()),
);

// Or wiretap all events
$runtime->wiretap(
    fn($event) => logger()->debug(get_class($event)),
);
// @doctest id="887b"
```


## Using a Local Model with Ollama

Instructor works with local models through Ollama. Install Ollama, pull a model, and
point Instructor at the local endpoint:

```php
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Polyglot\Inference\Config\LLMConfig;

$result = StructuredOutput::fromConfig(
    LLMConfig::fromDsn('driver=ollama,model=llama3.1')
)->with(
    messages: 'What is the capital of France?',
    responseModel: City::class,
)->get();
// @doctest id="8742"
```


## Framework Integration

Instructor is a standalone library that works in any PHP application. It does not
require published config files, service providers, or framework-specific bindings.

For Laravel-specific installation, configuration, facades, events, and testing,
use the dedicated Laravel package docs:

- [Instructor for Laravel installation guide](../../laravel/docs/installation.md)


## Next Steps

- [Quickstart](quickstart) -- run your first extraction
- [Usage](essentials/usage) -- the full request-building API
- [Configuration](essentials/configuration) -- advanced configuration options
- [Modes](essentials/modes) -- output mode details and trade-offs
- [LLM Providers](misc/llm_providers) -- supported providers and driver options
