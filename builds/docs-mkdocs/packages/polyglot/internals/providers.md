---
title: Providers
description: Provider objects are small config resolvers with optional explicit drivers.
---

Provider objects sit between config and runtime assembly.

## `LLMProvider`

Use it when you want a reusable inference provider object.

Factories:

- `LLMProvider::new()`
- `LLMProvider::using(...)`
- `LLMProvider::fromLLMConfig(...)`
- `LLMProvider::fromArray(...)`

Mutators:

- `withLLMConfig(...)`
- `withConfigOverrides(...)`
- `withModel(...)`
- `withDriver(...)`

## `EmbeddingsProvider`

Use it for the same role on the embeddings side.

Factories:

- `EmbeddingsProvider::new()`
- `EmbeddingsProvider::fromEmbeddingsConfig(...)`
- `EmbeddingsProvider::fromArray(...)`

Mutators:

- `withConfig(...)`
- `withConfigOverrides(...)`
- `withDriver(...)`

Unlike `LLMProvider`, `EmbeddingsProvider` does not have a `using(...)` shortcut.
