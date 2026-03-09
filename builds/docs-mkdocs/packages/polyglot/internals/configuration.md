---
title: Configuration
description: The config objects used to build runtimes and presets.
---

Polyglot resolves two config types.

## `LLMConfig`

Used by inference runtimes and providers.

Main fields:

- `driver`
- `apiUrl`
- `apiKey`
- `endpoint`
- `model`
- `maxTokens`
- `options`
- `pricing`

Load it with:

- `LLMConfig::fromPreset(...)`
- `LLMConfig::fromArray(...)`
- `LLMConfig::fromDsn(...)`

## `EmbeddingsConfig`

Used by embeddings runtimes and providers.

Main fields:

- `driver`
- `apiUrl`
- `apiKey`
- `endpoint`
- `model`
- `dimensions`
- `maxInputs`

Load it with:

- `EmbeddingsConfig::fromPreset(...)`
- `EmbeddingsConfig::fromArray(...)`
- `EmbeddingsConfig::fromDsn(...)`
