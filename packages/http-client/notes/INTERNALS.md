# HTTP Client Package - Internal Design

Note: concurrent request pooling has been extracted to `packages/http-pool`.
This document now describes the single-request transport layer only.

## Core Architecture

The package has four main pieces:

- `HttpClient`
  - public facade
  - immutable
  - delegates one request to `PendingHttpResponse`
- `PendingHttpResponse`
  - deferred execution
  - exposes `get()`, `content()`, `stream()`, `statusCode()`, `headers()`
- `MiddlewareStack`
  - composes middleware once around a low-level handler
- `CanHandleHttpRequest`
  - low-level driver contract

`HttpClientBuilder` is still the composition entry point today:

- resolves config
- resolves driver
- builds middleware stack
- returns `HttpClient`

## Exception Hierarchy

Comprehensive exception system providing rich error context and intelligent retry indicators:

```
HttpRequestException (Enhanced base - backward compatible)
├── NetworkException (Connection/transport issues - retriable)
│   ├── ConnectionException (DNS, refused connections)
│   └── TimeoutException (Request/connection timeouts)
├── HttpClientErrorException (4xx errors - only 429 is retriable)
└── ServerErrorException (5xx errors - all retriable)
```

**Exception Features**:
- **Rich Context**: `getRequest()`, `getResponse()`, `getDuration()`, `getStatusCode()`
- **Retry Intelligence**: Built-in `isRetriable()` method with smart defaults
- **Backward Compatibility**: All new exceptions extend `HttpRequestException`
- **Cross-Driver Consistency**: Same exception types across all HTTP drivers
- **HttpExceptionFactory**: Intelligent pattern-based exception creation

**Error Handling Flow**:
1. Driver catches native exceptions (Guzzle, Symfony, cURL, etc.)
2. `HttpExceptionFactory` maps to appropriate exception type
3. Enhanced exception includes request/response context + timing
4. Event dispatch (`HttpRequestFailed`) for monitoring
5. Consistent exception thrown regardless of underlying driver

## Mental Model

```
HttpClient
├── PendingHttpResponse
├── MiddlewareStack
│   └── MiddlewareHandler
│       └── Driver
└── Events
```

Request flow:

`HttpRequest` -> `PendingHttpResponse` -> `MiddlewareStack.decorate(driver)` -> `Driver.handle()` -> `HttpResponse`

For concurrent execution internals, see `packages/http-pool`.
