# HTTP Client v2 Core Scope

This file defines the intended minimal core for v2, based on current in-repo usage and maintenance cost.

## Core (First-Class)

- `HttpClient` + `HttpClientBuilder`
- Core drivers: `curl`, `guzzle`, `symfony`, `laravel`
- Streaming via `EventSourceMiddleware` and request `withStreaming(true)`
- Pooling via built-in pool handlers
- Mock/testing path (`MockHttpDriver`, mock response factory)
- Record/replay middleware path (kept in core for now)

## Compatibility (Deprecated but Kept)

- `HttpClient::withSSEStream()`
- `Middleware\ServerSideEvents\*`
- `HttpClientBuilder::using()`
- `HttpClientBuilder::withDebugPreset()`
- `StreamedRequestRecord::createAppropriateRecord()`

## Optionalization Candidates

- `Drivers\ExtHttp\ExtHttpDriver`
- `Drivers\ExtHttp\ExtHttpPool`

These are niche and extension-dependent (`pecl_http`), so they are planned to move to optional modules.

## Migration Matrix

| Current API | v2 Recommendation |
|---|---|
| `withSSEStream()` | `withMiddleware((new EventSourceMiddleware())->withParser(...))` |
| `StreamSSEsMiddleware` | `EventSourceMiddleware::withParser()` |
| `ServerSideEventStream` | `EventSourceStream` with parser callback |
| `HttpClientBuilder::using()` | `HttpClientBuilder::withPreset()` |
| `HttpClientBuilder::withDebugPreset()` | `HttpClientBuilder::withHttpDebugPreset()` |
| `StreamedRequestRecord::createAppropriateRecord()` | `RequestRecord::createAppropriate()` |
| `exthttp` driver/pool | Prefer core drivers, prepare for optional package |
