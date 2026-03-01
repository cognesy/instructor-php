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
- Deterministic test workflows via mock and record/replay middleware

## Core Building Blocks

- `HttpClient`: entry point for request execution, middleware, and pooling
- `HttpRequest`: immutable request object (`with*()` returns a new request)
- `PendingHttpResponse`: deferred response execution (`get()` / `stream()`)
- `HttpRequestList` / `HttpResponseList`: typed collections for pooling
- `MiddlewareStack`: immutable middleware composition

## Docs Structure

### Essentials

- [Overview](1-overview.md)
- [Getting Started](2-getting-started.md)
- [Making Requests](3-making-requests.md)
- [Handling Responses](4-handling-responses.md)
- [Streaming Responses](5-streaming-responses.md)
- [Request Pooling](6-pooling.md)
- [Middleware](10-middleware.md)

### Extras

- [Changing Client](7-changing-client.md)
- [Changing Client Config](8-changing-client-config.md)
- [Custom Clients](9-1-custom-clients.md)
- [Processing with Middleware](11-processing-with-middleware.md)
- [Reliability Middleware](12-reliability-middleware.md)
- [Record and Replay](13-record-replay.md)
- [Upgrade Guide (2.0)](14-upgrade-guide.md)

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
