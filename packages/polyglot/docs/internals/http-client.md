---
title: HTTP Client
description: Polyglot depends on the shared Instructor HTTP transport.
---

Polyglot does not implement a provider transport from scratch.
It builds on the shared HTTP package and depends on the transport contract:

- `Cognesy\Http\Contracts\CanSendHttpRequests`

When you call `InferenceRuntime::fromConfig(...)` or `EmbeddingsRuntime::fromConfig(...)`, Polyglot creates a default client with `HttpClientBuilder` unless you inject one.

That keeps transport concerns separate from request and response normalization.
