---
title: Lifecycle
description: What happens between `withMessages(...)` and the final response.
---

The inference lifecycle is straightforward:

1. `Inference` builds an `InferenceRequest`.
2. `create()` returns `PendingInference`.
3. The first read operation starts execution.
4. `InferenceRuntime` delegates to the selected driver.
5. The driver sends the HTTP request and normalizes the result.
6. Polyglot returns `InferenceResponse` or `PartialInferenceDelta` values.

For streaming requests:

1. `stream()` creates `InferenceStream`.
2. `deltas()` yields visible `PartialInferenceDelta` objects.
3. `final()` assembles the terminal `InferenceResponse`.

For embeddings:

1. `Embeddings` builds an `EmbeddingsRequest`.
2. `create()` returns `PendingEmbeddings`.
3. `get()` executes the driver and returns `EmbeddingsResponse`.
