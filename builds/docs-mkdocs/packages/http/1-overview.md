---
title: Overview
description: 'A small HTTP transport for requests, streaming, and middleware.'
---

The HTTP client package gives the rest of the stack one small transport API.

It is built around a few focused types:

- `CanSendHttpRequests`: top-level transport contract
- `HttpClient`: default implementation of `CanSendHttpRequests`
- `HttpRequest`: immutable request value
- `PendingHttpResponse`: deferred execution wrapper
- `HttpResponse`: buffered or streamed response value
- `HttpClientBuilder`: explicit composition entry point
- `HttpClientConfig`: typed driver configuration

## Core Capabilities

- send synchronous requests
- stream response bodies
- switch drivers without changing request code
- add middleware around the transport layer

Pooling lives in `packages/http-pool`.

## Typical Flow

```text
HttpClient
  -> send(HttpRequest)
  -> PendingHttpResponse
  -> get() or stream()
  -> HttpResponse
// @doctest id="5da9"
```

## Immutability

Reassign after every `with*()` call.
