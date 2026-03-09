# HTTP Client v2 API Surface Inventory

This document tracks public surface pruning decisions for `packages/http-client`.

## Keep (Core)

- `HttpClient` request flow: `default()`, `fromConfig()`, `send()`, `withMiddleware()`, `withoutMiddleware()`
- `HttpClientRuntime`
- `HttpClientBuilder` setup: `withConfig()`, `withDsn()`, `withDriver()`, `withDrivers()`, `withClientInstance()`, `withMock()`, `withMiddleware()`, `withEventBus()`, `create()`, `createRuntime()`
- Driver registry: `CanProvideHttpDrivers`, `HttpDriverRegistry`, `BundledHttpDrivers`
- Core data objects: `HttpRequest`, `HttpResponse`
- Core collections: `HttpRequestList`, `HttpResponseList`
- Core middleware runtime: `MiddlewareStack`

## Deprecated (Compatibility Layer)

- `HttpClient::withSSEStream()`
  Migration: explicit streaming middleware under `Extras`

- `StreamedRequestRecord::createAppropriateRecord()`
  Migration: `RequestRecord::createAppropriate()`

- `Middleware\ServerSideEvents\*`
  Migration: `Extras\Middleware\EventSource\*`

## Remove (Next Breaking Window)

Candidates to remove after deprecation window:

- `Middleware\ServerSideEvents\ServerSideEventStream`
- `Middleware\ServerSideEvents\ServerSideEventResponseDecorator`
- `Middleware\ServerSideEvents\StreamSSEsMiddleware`

## Scope Notes

- Core v2 keeps first-class support for: default driver path, streaming, mock/testing.
- Pooling now lives in `packages/http-pool`.
- Optional middleware and support helpers live under `Extras`.
