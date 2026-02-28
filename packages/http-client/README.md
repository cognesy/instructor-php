# HTTP Client Package

Framework-agnostic HTTP layer for Instructor, focused on reliable sync, streaming, and pooled execution.

## Core API

- `HttpClient::default()` / `HttpClient::using($preset)`
- `withRequest(HttpRequest)->get()` for sync responses
- `withRequest(HttpRequest)->stream()` for streaming responses
- `pool(HttpRequestList, ?int)` / `withPool(HttpRequestList)` for concurrency
- `withMiddleware()` / `withoutMiddleware()` for request/response interception

## Design Rules

- `with*()` operations are immutable and return new instances
- Streaming and sync pending execution paths are isolated
- Pooling uses the active client driver; unsupported external drivers fail explicitly

## Documentation

See `packages/http-client/docs/` for focused guides:

1. Overview
2. Getting Started
3. Making Requests
4. Handling Responses
5. Streaming Responses
6. Request Pooling
7. Middleware

v2 scope/pruning references:

- `packages/http-client/V2_API_SURFACE.md`
- `packages/http-client/V2_CORE_SCOPE.md`
