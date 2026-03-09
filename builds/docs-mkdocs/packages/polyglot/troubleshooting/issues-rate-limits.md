---
title: Rate Limits
description: Use explicit retry policies for temporary provider pressure.
---

Polyglot can retry transient failures, but retries are opt-in and explicit.

Use:

- `InferenceRetryPolicy` for inference
- `EmbeddingsRetryPolicy` for embeddings

If rate limits are frequent, reduce request volume or switch models and providers in application code.
