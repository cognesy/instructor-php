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
- Deterministic test workflows via mock and record/replay middleware

## Core Building Blocks

- `HttpClient`: entry point for request execution and middleware
- `HttpRequest`: immutable request object (`with*()` returns a new request)
- `PendingHttpResponse`: deferred response execution (`get()` / `stream()`)
- `MiddlewareStack`: immutable middleware composition

Pooling now lives in `packages/http-pool`.

## Docs Structure

### Essentials

- [Overview](1-overview.md)
- [Getting Started](2-getting-started.md)
- [Making Requests](3-making-requests.md)
- [Handling Responses](4-handling-responses.md)
- [Streaming Responses](5-streaming-responses.md)
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

Use `packages/http-pool` for fan-out/fan-in workloads with concurrent requests.

Avoid treating it as a full generic REST SDK abstraction. Keep it focused on reliable transport behavior.
