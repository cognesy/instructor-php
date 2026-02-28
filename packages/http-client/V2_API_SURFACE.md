# HTTP Client v2 API Surface Inventory

This document tracks public surface pruning decisions for `packages/http-client`.

## Keep (Core)

- `HttpClient` request flow: `default()`, `using()`, `withRequest()`, `pool()`, `withPool()`, `withMiddleware()`, `withoutMiddleware()`
- `HttpClientBuilder` core setup: `withPreset()`, `withConfig()`, `withDriver()`, `withMock()`, `withMiddleware()`, `create()`
- Core data objects: `HttpRequest`, `HttpResponse`
- Core collections: `HttpRequestList`, `HttpResponseList`
- Core middleware runtime: `MiddlewareStack`, retry/circuit-breaker/idempotency middleware
- Streaming core path: `EventSourceMiddleware`, `EventSourceResponseDecorator`, `EventSourceStream`
- Record/replay core path: `RecordReplayMiddleware`, `RecordingMiddleware`, `ReplayMiddleware`, request records

## Deprecated (Compatibility Layer)

- `HttpClient::withSSEStream()`
  Migration: `withMiddleware((new EventSourceMiddleware())->withParser(...))`

- `HttpClientBuilder::using()`
  Migration: `withPreset()`

- `HttpClientBuilder::withDebugPreset()`
  Migration: `withHttpDebugPreset()`

- `StreamedRequestRecord::createAppropriateRecord()`
  Migration: `RequestRecord::createAppropriate()`

- `Middleware\ServerSideEvents\*`
  Migration: `Middleware\EventSource\*`

## Remove (Next Breaking Window)

Candidates to remove after deprecation window:

- `Middleware\ServerSideEvents\ServerSideEventStream`
- `Middleware\ServerSideEvents\ServerSideEventResponseDecorator`
- `Middleware\ServerSideEvents\StreamSSEsMiddleware`
- Legacy alias methods listed above

## Scope Notes

- Core v2 keeps first-class support for: default driver path, streaming, mock/testing, and pooling.
- Optionalization candidate list (separate task): ext-http driver/pool and niche middleware modules.
