---
title: Testing Doubles
description: 'Deterministic testing seams for inference and embeddings.'
---

## Overview

Polyglot supports deterministic tests at two main seams.

- use fake drivers when you want to bypass HTTP and drive the runtime directly
- use `MockHttpDriver` when you want to keep transport and provider adapter behavior in play

Pick the shallowest seam that still exercises the behavior you care about.

## `FakeInferenceDriver`

`FakeInferenceDriver` lives in `packages/polyglot/tests/Support`.

Use it when you want to test:

- `Inference` request execution without real HTTP
- retry and event behavior around raw inference responses
- streaming assembly from queued `PartialInferenceDelta` batches

It supports:

- queued `InferenceResponse` objects
- queued streaming delta batches
- callback-driven sync or streaming behavior when the test needs custom logic

## `FakeEmbeddingsDriver`

`FakeEmbeddingsDriver` also lives in `packages/polyglot/tests/Support`.

Use it when you want to test:

- `Embeddings` and `PendingEmbeddings` behavior without real HTTP
- memoization and runtime delegation
- event dispatch around completed embeddings responses

It supports:

- queued `EmbeddingsResponse` objects
- callback-driven response generation from `EmbeddingsRequest`
- request recording through `handleCalls` and `requests`

Minimal example:

```php
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsResponse;
use Cognesy\Polyglot\Embeddings\Data\Vector;
use Cognesy\Polyglot\Tests\Support\FakeEmbeddingsDriver;

$driver = new FakeEmbeddingsDriver([
    new EmbeddingsResponse([new Vector(values: [0.1, 0.2], id: 0)]),
]);
```

## `MockHttpDriver`

Use `MockHttpDriver` when the HTTP layer still matters.

This is the right seam for:

- provider adapter tests
- request body and header assertions
- golden tests around provider-specific payload shapes
- error-path coverage that depends on real HTTP response objects

If the test is really about transport or adapter behavior, keep the mock HTTP path.
If it is about runtime behavior above transport, prefer the fake drivers.

## Which One To Use

Use this rule of thumb:

- `FakeInferenceDriver` for most deterministic inference tests
- `FakeEmbeddingsDriver` for most deterministic embeddings tests
- `MockHttpDriver` for transport and provider adapter coverage
