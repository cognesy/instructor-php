# HTTP Client v2 Core Scope

This file defines the intended minimal core for v2, based on current in-repo usage and maintenance cost.

## Core (First-Class)

- `HttpClient` + `HttpClientRuntime` + `HttpClientBuilder`
- `CanSendHttpRequests`
- `CanHandleHttpRequest`
- `HttpDriverRegistry` + `BundledHttpDrivers`
- Core drivers: `curl`, `guzzle`, `symfony`, `exthttp`
- Streaming via `HttpResponse` and request `withStreaming(true)`
- Mock/testing path (`MockHttpDriver`, mock response factory)

Pooling moved to `packages/http-pool`.

## Compatibility (Deprecated but Kept)

- `HttpClient::withSSEStream()`
- `Middleware\ServerSideEvents\*`
- `StreamedRequestRecord::createAppropriateRecord()`

## Extras

- middleware and support helpers under `Extras`

These are available, but not part of the smallest core surface.

## Migration Matrix

| Current API | v2 Recommendation |
|---|---|
| `withSSEStream()` | explicit middleware under `Extras` |
| `StreamSSEsMiddleware` | explicit middleware under `Extras` |
| `ServerSideEventStream` | explicit support class under `Extras` |
| `StreamedRequestRecord::createAppropriateRecord()` | `RequestRecord::createAppropriate()` |
| `exthttp` driver | Keep when available; use another core driver if the extension is not installed |
