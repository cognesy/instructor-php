---
title: Overview
description: 'Pragmatic overview of the Instructor HTTP client layer and its core workflow.'
---

## Why This Package Exists

`packages/http-client` gives Instructor a single HTTP API that works consistently across environments and drivers.

Primary goals:

- Stable request/response API across drivers (`curl`, `guzzle`, `symfony`, `laravel`)
- First-class streaming support for LLM responses
- Predictable middleware pipeline for cross-cutting behavior
- Concurrent request pooling with typed result handling

## Core Building Blocks

- `HttpClient`: entry point for request execution, middleware, and pooling
- `HttpRequest`: immutable request object (`with*()` returns a new request)
- `PendingHttpResponse`: deferred response execution (`get()` / `stream()`)
- `HttpRequestList` / `HttpResponseList`: typed collections for pooling
- `MiddlewareStack`: immutable middleware composition

## Mental Model

```text
HttpClient
  -> middleware stack
  -> driver
  -> HttpResponse (sync) or stream (chunked)
```

For pooling:

```text
HttpRequestList
  -> HttpClient::pool() or withPool()->all()
  -> HttpResponseList (Result<Success|Failure>)
```

## Immutability Rules

`with*()` methods are immutable across core objects:

- Reassign clients: `$client = $client->withMiddleware(...)`
- Reassign requests: `$request = $request->withHeader(...)`
- Middleware stack operations return new stack instances

This avoids hidden side effects between requests.

## Recommended Scope

Use this package for:

- LLM HTTP calls (sync or streaming)
- Request/response middleware customization
- Fan-out/fan-in workloads with pooled requests

Avoid treating it as a full generic REST SDK abstraction. Keep it focused on reliable transport behavior.
