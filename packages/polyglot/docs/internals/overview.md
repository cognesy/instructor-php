---
title: Architecture Overview
description: How the public facades, runtimes, requests, and drivers fit together.
---

Polyglot has a small layered structure.

## Public Layer

This is what application code usually touches:

- `Inference`
- `Embeddings`

These facades build request objects and delegate execution to runtimes.

## Runtime Layer

Runtimes assemble the moving parts:

- config
- driver
- HTTP client
- event dispatcher

The key classes are:

- `InferenceRuntime`
- `EmbeddingsRuntime`

## Request and Response Layer

Requests and responses are normalized into package data objects:

- `InferenceRequest`
- `InferenceResponse`
- `PartialInferenceDelta`
- `EmbeddingsRequest`
- `EmbeddingsResponse`

## Driver Layer

Drivers translate Polyglot requests into provider-native HTTP payloads and normalize the results back into Polyglot data objects.
