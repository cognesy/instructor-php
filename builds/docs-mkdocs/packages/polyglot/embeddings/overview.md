---
title: Overview of Embeddings
description: Generate vectors through the `Embeddings` facade.
---

`Embeddings` is the raw embeddings entrypoint in Polyglot.

Use it when you want vectors for one input or many inputs without dealing with provider-specific payloads directly.

## Main Entry Points

- `Embeddings::using('openai')`
- `Embeddings::fromConfig($config)`
- `Embeddings::fromProvider($provider)`
- `Embeddings::fromRuntime($runtime)`

## Main Request Methods

- `withInputs(...)`
- `withModel(...)`
- `withOptions(...)`
- `withRetryPolicy(...)`
- `withRequest(...)`

## Main Execution Methods

- `get()`
- `vectors()`
- `first()`

## Bundled Presets

Bundled embeddings presets include:

- `openai`
- `azure`
- `cohere`
- `gemini`
- `jina`
- `mistral`
- `ollama`
