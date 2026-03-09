---
title: Public API
description: The main classes applications are expected to use.
---

Most apps only need a small part of the package.

## Inference

Main class: `Cognesy\Polyglot\Inference\Inference`

Key entrypoints:

- `using(...)`
- `fromConfig(...)`
- `fromProvider(...)`
- `fromRuntime(...)`

Key request methods:

- `with(...)`
- `withMessages(...)`
- `withModel(...)`
- `withTools(...)`
- `withToolChoice(...)`
- `withResponseFormat(...)`
- `withOptions(...)`
- `withStreaming(...)`

Key execution methods:

- `get()`
- `response()`
- `asJsonData()`
- `asToolCallJsonData()`
- `stream()`
- `create()`

## Embeddings

Main class: `Cognesy\Polyglot\Embeddings\Embeddings`

Key entrypoints:

- `using(...)`
- `fromConfig(...)`
- `fromProvider(...)`
- `fromRuntime(...)`

Key request methods:

- `withInputs(...)`
- `withModel(...)`
- `withOptions(...)`
- `withRetryPolicy(...)`

Key execution methods:

- `get()`
- `vectors()`
- `first()`
