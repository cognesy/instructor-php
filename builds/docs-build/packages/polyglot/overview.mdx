---
title: Overview
description: 'Polyglot is the low-level LLM transport layer for InstructorPHP.'
meta:
  - name: 'has_code'
    content: false
---

Polyglot gives you one PHP API for raw LLM and embeddings requests.

It has two main entrypoints:

- `Cognesy\Polyglot\Inference\Inference` for model responses
- `Cognesy\Polyglot\Embeddings\Embeddings` for vectors

Polyglot stays close to provider-native request shapes. In 2.0, that means:

- plain text by default
- native JSON output through `responseFormat`
- native JSON schema output through `responseFormat`
- tool calling through `tools` and `toolChoice`
- streaming through `withStreaming()` and `stream()`
- embeddings through `Embeddings`

Polyglot is a transport and normalization layer. If you need higher-level structured output workflows, fallback prompting, or schema-to-object extraction, use Instructor on top.

## Main Concepts

### Inference

Use `Inference` when you want a model response as:

- text via `get()`
- a full `InferenceResponse` via `response()`
- decoded JSON via `asJsonData()`
- streamed deltas via `stream()`

### Embeddings

Use `Embeddings` when you want vectors from one or more inputs.

The response gives you:

- `first()` for the first vector
- `vectors()` for all vectors
- `usage()` for provider-reported token usage

### Presets

The usual entrypoint is `Inference::using('openai')` or `Embeddings::using('openai')`.

Preset files are loaded from:

- `config/llm/presets` and `config/embed/presets` in your app
- bundled presets shipped with the package

## What Polyglot Covers

- provider selection
- request building
- request execution
- response normalization
- streaming deltas
- retry policy
- custom drivers and runtimes

## What It Does Not Try To Hide

Polyglot does not invent a synthetic output mode system anymore.
You shape requests with explicit fields that match current provider APIs.
