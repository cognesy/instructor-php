---
title: Architecture Overview
description: How the public facades, runtimes, requests, and drivers fit together.
---

Polyglot is built on a modular, layered architecture that separates concerns and promotes extensibility. Each layer has a clear responsibility, and dependencies flow in one direction -- from the public API down to the HTTP transport.

Understanding these layers will help you extend the library, contribute to its development, or build your own integrations with new LLM providers.


## The Four Layers

### Public Layer

This is what application code usually touches. Two facade classes provide a unified interface for all provider interactions:

- **`Inference`** -- for chat completions and text generation
- **`Embeddings`** -- for generating vector embeddings

These facades build request objects, delegate execution to runtimes, and return normalized responses regardless of the underlying provider. Both facades follow an immutable, fluent interface pattern -- every method that modifies state returns a new instance, so you can safely branch configurations from a shared base.

### Runtime Layer

Runtimes assemble the moving parts needed for a provider call. They wire together the configuration, driver, HTTP client, and event dispatcher, and they own the execution lifecycle including retry logic and response caching.

The key classes are:

- **`InferenceRuntime`** -- coordinates inference execution and creates `PendingInference` handles
- **`EmbeddingsRuntime`** -- coordinates embeddings execution and creates `PendingEmbeddings` handles

Each runtime can be constructed from a config object, a provider, or injected directly. When no HTTP client is provided, the runtime builds a default one via `HttpClientBuilder`. Runtimes also expose `onEvent()` and `wiretap()` methods for hooking into the event system.

### Request and Response Layer

Requests and responses are normalized into package data objects that are provider-agnostic:

- **`InferenceRequest`** -- messages, model, tools, tool choice, response format, options, cached context, retry policy, response cache policy
- **`InferenceResponse`** -- content, reasoning content, tool calls, usage (with pricing), finish reason, raw HTTP response data
- **`PartialInferenceDelta`** -- a single streaming event delta with content, reasoning content, tool call fragments, finish reason, and usage
- **`EmbeddingsRequest`** -- input texts, model, options, retry policy
- **`EmbeddingsResponse`** -- vectors and usage

These objects isolate your application from provider-specific response shapes. Both request types support immutable `with*()` mutators for building modified copies.

### Driver Layer

Drivers translate Polyglot requests into provider-native HTTP payloads and normalize the results back. Each driver implements `CanProcessInferenceRequest` (for inference) or `CanHandleVectorization` (for embeddings) and is composed of smaller adapter responsibilities:

- **Request adapters** (`CanTranslateInferenceRequest`) -- convert `InferenceRequest` into an `HttpRequest`
- **Response adapters** (`CanTranslateInferenceResponse`) -- convert raw `HttpResponse` data into `InferenceResponse` or stream of `PartialInferenceDelta`
- **Message formatters** (`CanMapMessages`) -- map the unified message format to provider-specific structures
- **Body formatters** (`CanMapRequestBody`) -- assemble the full request body with mode-specific adjustments
- **Usage formatters** (`CanMapUsage`) -- extract token usage from provider responses

Most inference drivers extend `BaseInferenceRequestDriver`, which provides the standard HTTP execution flow and stream handling. Provider-specific classes like `OpenAIDriver`, `AnthropicDriver`, and `GeminiDriver` compose the appropriate adapters and formatters for their API.


## How the Layers Connect

```text
+---------------------+    +---------------------+
|      Inference      |    |     Embeddings      |     Public Layer
+---------------------+    +---------------------+
          |                          |
+---------------------+    +---------------------+
|  InferenceRuntime   |    | EmbeddingsRuntime   |     Runtime Layer
+---------------------+    +---------------------+
          |                          |
+---------------------+    +---------------------+
|  InferenceRequest   |    | EmbeddingsRequest   |
|  PendingInference   |    | PendingEmbeddings   |     Request/Response
|  InferenceResponse  |    | EmbeddingsResponse  |     Layer
+---------------------+    +---------------------+
          |                          |
+---------------------+    +---------------------+
|  Inference Drivers  |    |  Embeddings Drivers |     Driver Layer
| (OpenAI, Anthropic, |    | (OpenAI, Cohere,    |
|  Gemini, etc.)      |    |  Gemini, etc.)      |
+---------------------+    +---------------------+
          |                          |
+------------------------------------------------+
|             HTTP Client (shared)               |     Transport
+------------------------------------------------+
```

The public facade creates a request and hands it to the runtime. The runtime delegates to a driver, which translates the request into an HTTP call and normalizes the response. Events are dispatched at each stage for observability. The result flows back up as a normalized data object.


## Key Design Decisions

**Immutability.** Both the public facades and the request/response objects are immutable. Calling `withMessages()` or `withModel()` always returns a new instance rather than modifying the original. This makes it safe to reuse a configured `Inference` or `Embeddings` instance across multiple concurrent calls.

**Lazy execution.** Calling `create()` on a facade returns a `PendingInference` or `PendingEmbeddings` handle without triggering the HTTP call. Execution is deferred until the application reads from the handle via `get()`, `response()`, or `stream()`.

**Driver registry.** Inference drivers are resolved through `InferenceDriverRegistry`, which maps string names (like `'openai'` or `'anthropic'`) to driver factory functions. Embeddings drivers use `EmbeddingsDriverFactory` with a similar pattern. Both support registering custom drivers at runtime.

**Provider-agnostic data.** The `InferenceResponse` and `EmbeddingsResponse` objects present a uniform shape regardless of which provider produced them. Provider-specific details are accessible through `responseData()` when needed, but the primary accessors (`content()`, `toolCalls()`, `usage()`, etc.) work identically across all providers.
