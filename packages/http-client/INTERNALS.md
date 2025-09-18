# HTTP Client Package - Internal Design

## Core Architecture

**HttpClient** serves as the unified public API that abstracts underlying HTTP implementations (Guzzle, Laravel, Symfony) behind a consistent interface. The design enables seamless integration into diverse PHP environments without coupling to specific HTTP client libraries.

### Key Components

**HttpClient** - Main entry point providing immutable fluent API:
- `HttpClient::default()` / `HttpClient::using(preset)` - Factory methods
- `withRequest(HttpRequest)` → `PendingHttpResponse` - Lazy execution model
- `PendingHttpResponse` - Represents a pending execution, let's you choose and chain further operations (e.g., regular call vs streaming)
- `withMiddleware()` / `withoutMiddleware()` - Stack manipulation
- `pool()` / `withPool()` - Concurrent request execution
- Internally wraps: `CanHandleHttpRequest` driver + `MiddlewareStack`

**HttpClientBuilder** - Fluent configuration builder:
- Resolves driver from config presets or explicit instances
- Assembles middleware stack (default: BufferResponse + optional debug)
- Manages event dispatching and dependency injection
- Supports custom client instance injection for DI container integration

**Driver Abstraction** - `CanHandleHttpRequest` contract:
- Single method: `handle(HttpRequest) → HttpResponse`
- Implementations: `GuzzleDriver`, `LaravelDriver`, `SymfonyDriver`, `MockDriver`
- Each driver wraps native client (Guzzle\Client, Laravel\Factory, Symfony\Client)
- Standardizes config mapping (timeouts, headers, streaming)
- Enhanced exception handling with timing measurement and rich context
- Pool support: `CanHandleRequestPool` contract for concurrent execution

**Middleware System** - Onion-pattern request/response processing:
- `MiddlewareStack` - manages ordered collection with named middleware
- `MiddlewareHandler` - executes stack recursively around driver
- `HttpMiddleware` contract: `handle(HttpRequest, next) → HttpResponse`
- Built-in: BufferResponse, EventSource, StreamByLine, RecordReplay
- Examples: Example1-3Middleware for reference implementations

**Data Layer**:
- `HttpRequest` - immutable request container (url, method, headers, body, options)
- `HttpResponse` - interface for response data (status, headers, body, streaming)
- `PendingHttpResponse` - lazy executor that triggers middleware stack + driver
- `PendingHttpPool` - manages concurrent request execution

## Exception Hierarchy (New in v1.7+)

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
1. Driver catches native exceptions (Guzzle, Symfony, etc.)
2. `HttpExceptionFactory` maps to appropriate exception type
3. Enhanced exception includes request/response context + timing
4. Event dispatch (`HttpRequestFailed`) for monitoring
5. Consistent exception thrown regardless of underlying driver

## Mental Model

```
HttpClient
├── MiddlewareStack (BufferResponse + custom)
│   └── MiddlewareHandler (recursive execution)
│       └── Driver (Guzzle/Laravel/Symfony)
│           ├── Native HTTP Client
│           └── HttpExceptionFactory (error mapping)
├── Events (debugging/monitoring)
└── PendingHttpPool (concurrent execution)
```

**Request Flow**: `HttpRequest` → `MiddlewareStack.decorate(driver)` → `MiddlewareHandler` → `Driver.handle()` → `HttpResponse`

**Error Flow**: Native Exception → `HttpExceptionFactory.fromDriverException()` → Custom Exception → Event Dispatch

**Pool Flow**: `HttpRequest[]` → `PendingHttpPool` → Driver Pool Implementation → Concurrent Execution

**Configuration**: Preset-based config resolution supports environment-specific defaults while allowing per-client overrides.

**Immutability**: All client operations return new instances, enabling safe concurrent usage and configuration sharing.

## Advanced Features

### Concurrent Request Processing
- **Pool Support**: All drivers implement `CanHandleRequestPool` for concurrent execution
- **Driver-Specific Pools**: `GuzzlePool`, `LaravelPool`, `SymfonyPool` with optimized implementations
- **Configurable Concurrency**: `maxConcurrent` parameter controls parallel request limits
- **Mixed Results**: Pools handle success/failure combinations gracefully

### Streaming & Real-time Processing
- **Server-Sent Events**: `EventSourceMiddleware` with listener system
- **Progressive Streaming**: `StreamByLineMiddleware` for line-by-line processing
- **Buffering Control**: Configurable response buffering via middleware
- **Debug Integration**: Real-time stream monitoring with `DispatchDebugEvents`

### Testing & Development
- **Record/Replay**: `RecordReplayMiddleware` for HTTP interaction capture
- **Mock Driver**: Full-featured mocking with expectations and callbacks
- **Integration Test Server**: Built-in HTTP server for reliable testing
- **Debug Events**: Comprehensive debugging event system

### Error Handling Improvements
- **Status Code Handling**: Configurable error throwing via `failOnError` flag
- **Retry Indicators**: Built-in retry logic guidance via `isRetriable()` method
- **Context Preservation**: Full request/response context in all exceptions
- **Event Integration**: Error events for monitoring and logging integration

## Integration Strategy

The package eliminates HTTP client lock-in:
- **Instance Injection**: Accepts pre-configured client instances from DI containers
- **Preset System**: Environment-specific configurations (Laravel, Symfony, etc.)
- **Middleware Compatibility**: Consistent processing layer regardless of underlying client
- **Exception Consistency**: Same exception hierarchy across all HTTP implementations
- **Pool Abstraction**: Concurrent request handling without driver coupling

This design allows InstructorPHP to integrate transparently into any PHP application stack while maintaining performance, leveraging existing HTTP infrastructure, and providing robust error handling with intelligent retry capabilities.