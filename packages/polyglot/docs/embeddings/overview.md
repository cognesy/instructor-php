---
title: Overview of Embeddings
description: Generate vector embeddings through the Embeddings facade across multiple providers.
---

Embeddings are numerical representations of text that capture semantic meaning in a high-dimensional vector space. They are a foundational building block for many LLM-powered applications, enabling machines to understand relationships between words, phrases, and documents.

Polyglot's `Embeddings` class provides a unified interface for generating vector embeddings across multiple providers. You write your code once, and switch between OpenAI, Cohere, Gemini, Jina, Mistral, or any other supported provider by changing a single preset name.

## Understanding Embeddings

Before diving into the API, it helps to understand the core concepts:

- **Vectors** -- Embeddings represent text as arrays of floating-point numbers in a high-dimensional space (typically 256 to 3072 dimensions).
- **Semantic similarity** -- Texts with similar meaning produce vectors that are closer together, measurable through cosine similarity, Euclidean distance, or dot product.
- **Provider models** -- Different providers offer models with varying dimension counts, language support, and performance characteristics.

Common use cases for embeddings include:

- **Semantic search** -- Find documents similar to a query based on meaning, not just keywords.
- **Clustering** -- Group related documents together automatically.
- **Classification** -- Assign categories to text based on content.
- **Recommendations** -- Suggest related items based on vector proximity.
- **RAG (Retrieval-Augmented Generation)** -- Retrieve relevant context for LLM prompts.

## The Embeddings Class

The `Embeddings` class is a facade that combines provider configuration, request building, and result handling into a fluent, immutable API. Every method that modifies state returns a new instance, making the class safe to reuse and compose.

### Architecture Overview

The class is built from several focused components:

| Component | Responsibility |
|---|---|
| `Embeddings` | Facade with fluent API and static factory methods |
| `EmbeddingsRuntime` | Orchestrates driver creation, HTTP clients, and event dispatching |
| `EmbeddingsProvider` | Resolves configuration and optional explicit drivers |
| `PendingEmbeddings` | Executes the request with retry logic and returns the response |
| `EmbeddingsDriverFactory` | Maps driver names to concrete driver implementations |

## Entry Points

You can create an `Embeddings` instance in several ways, depending on how much control you need:

```php
<?php

use Cognesy\Polyglot\Embeddings\Embeddings;
use Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig;

// From a named preset (most common)
$embeddings = Embeddings::using('openai');

// From a configuration object
$config = new EmbeddingsConfig(
    apiUrl: 'https://api.openai.com/v1',
    apiKey: getenv('OPENAI_API_KEY'),
    endpoint: '/embeddings',
    model: 'text-embedding-3-small',
    dimensions: 1536,
    maxInputs: 2048,
    driver: 'openai',
);
$embeddings = Embeddings::fromConfig($config);

// From a provider instance (for advanced composition)
$embeddings = Embeddings::fromProvider($provider);

// From a custom runtime (full control over driver and events)
$embeddings = Embeddings::fromRuntime($runtime);
```

## Request Methods

Configure what to embed before executing the request:

| Method | Description |
|---|---|
| `withInputs(string\|array $input)` | Set one or more texts to embed |
| `withModel(string $model)` | Override the model from the preset |
| `withOptions(array $options)` | Pass provider-specific options |
| `withRetryPolicy(EmbeddingsRetryPolicy $policy)` | Configure retry behavior |
| `withRequest(EmbeddingsRequest $request)` | Replace the entire request object |
| `with($input, $options, $model)` | Shorthand combining inputs, options, and model |

## Execution Methods

Three convenience methods execute the request and return results at different levels of detail:

| Method | Returns | Description |
|---|---|---|
| `get()` | `EmbeddingsResponse` | Full response with vectors, usage, and metadata |
| `vectors()` | `Vector[]` | Array of all embedding vectors |
| `first()` | `?Vector` | The first vector, or `null` if empty |

For advanced use cases, `create()` returns a `PendingEmbeddings` instance that you can inspect or execute manually.

## Supported Providers

Polyglot ships with presets for the following providers:

| Preset | Driver | Default Model | Dimensions |
|---|---|---|---|
| `openai` | OpenAI | `text-embedding-3-small` | 1536 |
| `azure` | Azure OpenAI | (configured per deployment) | (varies) |
| `cohere` | Cohere | `embed-multilingual-v3.0` | 1024 |
| `gemini` | Gemini | (configured per preset) | (varies) |
| `jina` | Jina | (configured per preset) | (varies) |
| `mistral` | OpenAI-compatible | (configured per preset) | (varies) |
| `ollama` | OpenAI-compatible | (configured per preset) | (varies) |

> **Note:** Mistral and Ollama use the OpenAI-compatible driver, since their APIs follow the same format.

## Custom Driver Registration

You can register your own driver for providers not bundled with Polyglot:

```php
<?php

use Cognesy\Polyglot\Embeddings\Embeddings;

// Register with a class name
Embeddings::registerDriver('custom-provider', CustomEmbeddingsDriver::class);

// Register with a factory callable
Embeddings::registerDriver('custom-provider', function ($config, $httpClient, $events) {
    return new CustomEmbeddingsDriver($config, $httpClient, $events);
});

// Then use it like any other preset
$response = Embeddings::using('custom-provider')
    ->withInputs(['Hello world'])
    ->get();
```

Your custom driver must implement the `CanHandleVectorization` contract.

## Events

The embeddings system dispatches events at key points during execution, which you can listen to through the runtime:

| Event | When |
|---|---|
| `EmbeddingsDriverBuilt` | After the driver is created from configuration |
| `EmbeddingsRequested` | When an embeddings request is initiated |
| `EmbeddingsResponseReceived` | After a successful response is received |
| `EmbeddingsFailed` | When the request fails after all retry attempts |
