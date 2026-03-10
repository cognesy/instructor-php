---
title: Overview
description: 'Polyglot is the low-level LLM transport layer for InstructorPHP, providing a unified API for interacting with multiple LLM and embeddings providers.'
meta:
  - name: 'has_code'
    content: false
---

Polyglot is a PHP library that provides a unified API for interacting with various Large Language Model (LLM) providers. It serves as the low-level transport and normalization layer for InstructorPHP, but can also be used as a standalone library for direct LLM interactions.

The core philosophy behind Polyglot is to create a consistent, provider-agnostic interface that abstracts away the differences between LLM APIs while staying close to provider-native request shapes. This enables developers to:

- Write code once and use it with any supported LLM provider
- Easily switch between providers without changing application code
- Use different providers in different environments (development, testing, production)
- Fall back to alternative providers if one becomes unavailable
- Use local models (via Ollama) for development and cloud providers for production

Polyglot has two main entrypoints:

- `Cognesy\Polyglot\Inference\Inference` for model responses (chat completions)
- `Cognesy\Polyglot\Embeddings\Embeddings` for vector embeddings

In 2.0, Polyglot stays close to provider-native request shapes, supporting:

- Plain text output by default
- Native JSON output through `responseFormat`
- Native JSON schema output through `responseFormat`
- Tool calling through `tools` and `toolChoice`
- Streaming through `withStreaming()` and `stream()`
- Embeddings through the `Embeddings` facade

Polyglot is a transport and normalization layer. If you need higher-level structured output workflows, fallback prompting, or schema-to-object extraction, use Instructor on top.


## Key Features

### Unified LLM API

Polyglot's primary feature is its unified API that works across multiple LLM providers:

- Consistent interface for making inference and embedding requests
- Common message format across all providers
- Standardized response handling with `InferenceResponse` and `EmbeddingsResponse`
- Unified error handling and retry policies

### Framework-Agnostic

Polyglot is designed to work with any PHP framework or even in plain PHP applications. It does not depend on any specific framework, making it easy to integrate into existing projects.

- Compatible with Laravel, Symfony, CodeIgniter, and others
- Can be used in CLI scripts or web applications
- Lightweight with minimal dependencies

### Configuration Flexibility

Polyglot offers a flexible configuration system built around YAML preset files:

- Configure multiple providers simultaneously using named presets
- Environment-based configuration with `${ENV_VAR}` interpolation in preset files
- Runtime provider switching via `Inference::using('preset-name')`
- Per-request customization through the fluent builder API
- DSN-based configuration via `LLMConfig::fromDsn()`


## Main Concepts

### Inference

The `Inference` class is the main facade for sending requests to LLM providers and receiving responses. It provides a fluent builder API for constructing requests and several convenience methods for consuming responses.

Use `Inference` when you want a model response as:

- Plain text via `get()` -- returns the raw content string
- A full `InferenceResponse` via `response()` -- gives access to content, tool calls, usage, finish reason, and reasoning content
- Decoded JSON via `asJsonData()` -- extracts and parses JSON from the response content
- Tool call arguments via `asToolCallJsonData()` -- extracts arguments from tool/function calls
- Streamed deltas via `stream()` -- returns an `InferenceStream` for real-time processing

The `InferenceResponse` object provides rich access to the full response, including:

- `content()` -- the text content of the response
- `reasoningContent()` -- reasoning/thinking content (for models that support it)
- `toolCalls()` -- any tool calls made by the model
- `usage()` -- token usage statistics
- `finishReason()` -- why the model stopped generating
- `hasContent()`, `hasToolCalls()`, `hasReasoningContent()` -- presence checks

### Streaming

Polyglot provides first-class streaming support through the `InferenceStream` class. When you call `stream()`, you receive a stream object that yields `PartialInferenceDelta` objects as they arrive from the provider.

The stream supports several consumption patterns:

- `deltas()` -- a generator that yields each visible delta as it arrives
- `all()` -- collects all deltas into an array
- `map(callable)` -- transforms each delta through a mapper function
- `filter(callable)` -- yields only deltas matching a predicate
- `reduce(callable, initial)` -- reduces the stream to a single value
- `final()` -- drains the stream and returns the finalized `InferenceResponse`
- `onDelta(callable)` -- registers a callback for each visible delta

Streaming also dispatches events for monitoring, including `StreamFirstChunkReceived` for time-to-first-chunk measurement.

### Embeddings

The `Embeddings` class is the facade for generating vector embeddings from text inputs. It follows the same fluent builder pattern as `Inference`.

Use `Embeddings` when you want vectors from one or more text inputs. The `EmbeddingsResponse` gives you:

- `first()` -- the first embedding vector (useful for single-input requests)
- `vectors()` -- all embedding vectors as an array of `Vector` objects
- `all()` -- alias for `vectors()`
- `last()` -- the last embedding vector
- `split(index)` -- splits vectors into two groups at a given index
- `usage()` -- provider-reported token usage
- `toValuesArray()` -- raw float arrays for all vectors

### Presets

The usual entrypoint is `Inference::using('openai')` or `Embeddings::using('openai')`, which loads a named preset configuration.

Preset files are YAML files that define the connection details for a provider. They are loaded from the following locations (searched in order):

- `config/llm/presets` (or `config/embed/presets`) in your application root
- `packages/polyglot/resources/config/llm/presets` within the monorepo
- `vendor/cognesy/instructor-php/packages/polyglot/resources/config/llm/presets` when installed via Composer
- `vendor/cognesy/instructor-polyglot/resources/config/llm/presets` for standalone installs

A typical preset file looks like this:

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

You can override any preset value at runtime using the fluent API -- for example, `withModel()` to change the model or `withMaxTokens()` to adjust the token limit.

### Providers and Drivers

Each LLM provider is backed by a driver that knows how to format requests and parse responses for that provider's API. Polyglot ships with drivers for all supported providers, and you can register custom drivers when needed.

The `LLMProvider` and `EmbeddingsProvider` classes act as configuration holders that pair a config with an optional explicit driver. They are typically created behind the scenes when you use `Inference::using()` or `Inference::fromConfig()`.


## What Polyglot Covers

- **Provider selection** -- choose any supported provider through presets or programmatic configuration
- **Request building** -- fluent API for constructing messages, setting models, tools, response formats, and options
- **Request execution** -- handles HTTP communication with provider APIs
- **Response normalization** -- unified `InferenceResponse` and `EmbeddingsResponse` regardless of provider
- **Streaming deltas** -- real-time streaming with event-driven processing
- **Retry policy** -- configurable retry behavior for transient failures via `InferenceRetryPolicy` and `EmbeddingsRetryPolicy`
- **Custom drivers and runtimes** -- extensible architecture for adding new providers or custom execution logic
- **Response caching** -- configurable cache policy for inference responses

## What It Does Not Try To Hide

Polyglot does not invent a synthetic output mode system. You shape requests with explicit fields that match current provider APIs -- `responseFormat` for JSON output, `tools` and `toolChoice` for function calling, and `withStreaming()` for streamed responses. This keeps the abstraction thin and predictable, making it easy to understand what will be sent to the provider.


## Supported Providers

### Inference Providers

Polyglot ships with drivers for the following LLM providers:

- **A21** -- API access to Jamba models
- **Anthropic** -- Claude family of models
- **AWS Bedrock** -- Amazon Bedrock hosted models
- **Microsoft Azure** -- Azure-hosted OpenAI models
- **Cerebras** -- Cerebras high-performance inference
- **Cohere** -- Command models (v2 API)
- **Deepseek** -- Deepseek models including reasoning capabilities
- **Fireworks** -- Fireworks AI hosted models
- **GLM** -- GLM (ChatGLM) models
- **Google Gemini** -- Google's Gemini models (native API)
- **Google Gemini (OpenAI compatible)** -- Gemini via OpenAI-compatible endpoint
- **Groq** -- High-performance inference platform
- **Hugging Face** -- Hugging Face hosted models
- **Inception** -- Inception AI models
- **Meta** -- Meta AI models
- **MiniMaxi** -- MiniMax models (native and OpenAI compatible)
- **Mistral** -- Mistral AI models
- **Moonshot** -- Kimi models
- **Ollama** -- Self-hosted open source models
- **OpenAI** -- GPT models family (Chat Completions API)
- **OpenAI Responses** -- OpenAI Responses API
- **OpenAI Compatible** -- Generic driver for any OpenAI-compatible API
- **OpenRouter** -- Multi-provider routing service
- **Perplexity** -- Perplexity models
- **Qwen** -- Qwen (Tongyi Qianwen) models
- **SambaNova** -- SambaNova hosted models
- **Together** -- Together AI hosted models
- **xAI** -- xAI's Grok models

### Embeddings Providers

For vector embeddings generation, Polyglot supports:

- **Microsoft Azure** -- Azure-hosted OpenAI embeddings
- **Cohere** -- Cohere embedding models
- **Google Gemini** -- Google's embedding models
- **Jina** -- Jina AI embeddings
- **Ollama** -- Self-hosted embedding models
- **OpenAI** -- OpenAI text embedding models


## Use Cases

Polyglot is a good choice for a variety of scenarios:

- **Applications requiring LLM provider flexibility** -- switch between providers based on cost, performance, or feature needs without rewriting application code
- **Multi-environment deployments** -- use different LLM providers in development, staging, and production through preset configuration
- **Redundancy and fallback** -- implement fallback strategies when a provider is unavailable
- **Hybrid approaches** -- combine different providers for different tasks based on their strengths
- **Local + cloud development** -- use local models via Ollama for development and cloud providers for production
- **Direct LLM access** -- when you need raw LLM responses without the higher-level extraction that Instructor provides
