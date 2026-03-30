---
title: Introduction
description: 'Structured data extraction in PHP, powered by LLMs. Designed for simplicity, transparency, and control.'
---

Instructor is a PHP library for extracting structured, validated data from LLM responses.
You define the shape of the data you need using plain PHP classes, and Instructor handles
the rest: schema generation, prompt construction, response parsing, validation, and
automatic retries.

The library is inspired by the [Instructor](https://jxnl.github.io/instructor/) library
for Python created by [Jason Liu](https://twitter.com/jxnlco).

```php
use Cognesy\Instructor\StructuredOutput;

final class City {
    public string $name;
    public string $country;
    public int $population;
}

$city = StructuredOutput::using('openai')
    ->with(
        messages: 'What is the capital of France?',
        responseModel: City::class,
    )
    ->get();

echo $city->name;       // Paris
echo $city->country;    // France
echo $city->population; // 2148000
// @doctest id="4137"
```

The package is distributed as `cognesy/instructor-struct` and requires PHP 8.3+.


## Core Architecture

Instructor's API is built around four types, each with a distinct responsibility:

| Type | Role |
|------|------|
| `StructuredOutput` | Builds and executes a single request. Provides the primary developer-facing API. |
| `StructuredOutputRuntime` | Holds provider configuration, retry policy, output mode, event listeners, and pipeline extensions. Reusable across requests. |
| `PendingStructuredOutput` | A lazy handle returned by `create()`. Execution happens only when you call `get()`, `response()`, or `stream()`. |
| `StructuredOutputStream` | Exposes streaming partial updates, sequence items, and the final response. |


## How It Works

1. **Define a response model.** Use a PHP class with typed public properties. Instructor
   generates a JSON Schema from the class and sends it to the LLM.

2. **Build a request.** Provide input messages, select a provider, and optionally
   customize the system prompt, examples, or model options.

3. **Read the result.** Call `get()` for the deserialized object, `stream()` for partial
   updates, or `response()` for the full response wrapper including raw LLM output.

Under the hood, Instructor translates your response model into a schema the LLM
understands, wraps it in the appropriate output mode (tool calls, JSON mode, or JSON
Schema mode), and deserializes the response back into your PHP object. If the response
fails validation, Instructor feeds the errors back to the LLM and retries automatically.


## Feature Highlights

### Structured Responses & Validation

- Extract typed objects, arrays, or scalar values from LLM responses
- Automatic validation of returned data using Symfony Validator constraints
- Configurable retry policy when the LLM returns invalid data

### Flexible Inputs

- Process text, chat message arrays, or images
- Provide examples to improve extraction quality
- Structure-to-structure processing: pass an object or array as input and receive a
  typed result

### Multiple Response Model Formats

- **PHP classes** with typed properties and optional validation attributes
- **JSON Schema arrays** for dynamic or runtime-defined shapes
- **Scalar types** via built-in helpers (`getString()`, `getInt()`, `getBoolean()`, etc.)

### Sync & Streaming

- Synchronous extraction with `get()`
- Streaming partial updates with `stream()->partials()`
- Streaming completed sequence items with `stream()->sequence()`

### Provider Support

- Works with OpenAI, Anthropic, Google Gemini, Cohere, Azure OpenAI, Groq, Mistral,
  Fireworks AI, Ollama, OpenRouter, Together AI, and more
- Switch providers by changing a single preset name or `LLMConfig`

### Observability

- Fine-grained event system for monitoring every stage of the extraction pipeline
- Wiretap support for logging and debugging


## Start Here

- [Quickstart](quickstart) -- extract your first typed object in minutes
- [Setup](setup) -- installation and provider configuration
- [Usage](essentials/usage) -- the full request-building API
- [Data Model](essentials/data_model) -- defining response models
- [Validation](essentials/validation) -- validation rules and retry behavior
- [Partials](advanced/partials) -- streaming partial updates


## Instructor in Other Languages

Instructor has been implemented across multiple technology stacks:

- [Python](https://www.github.com/jxnl/instructor) (original)
- [JavaScript / TypeScript](https://github.com/instructor-ai/instructor-js)
- [Elixir](https://github.com/thmsmlr/instructor_ex/)
- [Ruby](https://ruby.useinstructor.com/)
- [Go](https://go.useinstructor.com/)
