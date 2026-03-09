---
title: Overview
description: 'Small transport layer for sync and streaming HTTP requests.'
---

`packages/http-client` gives the rest of the stack one HTTP API.

## Core Types

- `CanSendHttpRequests`: top-level transport contract
- `HttpClient`: default implementation of `CanSendHttpRequests`
- `HttpRequest`: immutable request object (`with*()` returns a new request)
- `PendingHttpResponse`: deferred response execution (`get()` / `stream()`)
- `HttpResponse`: buffered or streamed response value
- `MiddlewareStack`: immutable middleware chain

## Scope

- sync requests
- streaming requests
- middleware composition

Pooling lives in `packages/http-pool`.

## Shape

```text
CanSendHttpRequests
  -> HttpClient
  -> PendingHttpResponse
  -> middleware stack
  -> driver
  -> HttpResponse
```

## Rule

Reassign after every `with*()` call.
