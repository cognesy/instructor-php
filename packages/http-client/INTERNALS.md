# HTTP Client Package - Internal Design

## Core Architecture

**HttpClient** serves as the unified public API that abstracts underlying HTTP implementations (Guzzle, Laravel, Symfony, Curl) behind a consistent interface. The design enables seamless integration into diverse PHP environments without coupling to specific HTTP client libraries.

### Key Components

**HttpClient** - Main entry point providing immutable fluent API:
- `HttpClient::default()` / `HttpClient::using(preset)` - Factory methods
- `withRequest(HttpRequest)` → `PendingHttpResponse` - Lazy execution model
- `PendingHttpResponse` - Represents a pending execution, lets you choose and chain further operations (e.g., regular call vs streaming)
- `withMiddleware()` / `withoutMiddleware()` - Stack manipulation
- `pool(HttpRequestList, ?int)` → `HttpResponseList` - Concurrent request execution
- `withPool(HttpRequestList)` → `PendingHttpPool` - Deferred concurrent execution
- Internally wraps: `CanHandleHttpRequest` driver + `MiddlewareStack`

**HttpClientBuilder** - Fluent configuration builder:
- Resolves driver from config presets or explicit instances
- Assembles middleware stack (default: BufferResponse + optional debug)
- Manages event dispatching and dependency injection
- Supports custom client instance injection for DI container integration

**Driver Abstraction** - `CanHandleHttpRequest` contract:
- Single method: `handle(HttpRequest) → HttpResponse`
- Implementations: `GuzzleDriver`, `LaravelDriver`, `SymfonyDriver`, `CurlDriver`, `CurlNewDriver`, `MockDriver`
- Each driver wraps native client (Guzzle\Client, Laravel\Factory, Symfony\Client, or raw cURL)
- Standardizes config mapping (timeouts, headers, streaming)
- Enhanced exception handling with timing measurement and rich context
- Pool support: `CanHandleRequestPool` contract for concurrent execution

**Pool Abstraction** - `CanHandleRequestPool` contract:
- Method: `pool(HttpRequestList, ?int) → HttpResponseList`
- Implementations: `GuzzlePool`, `LaravelPool`, `SymfonyPool`, `CurlPool`, `CurlNewPool`
- Driver-optimized concurrent execution (native pools for Guzzle/Symfony, batching for Laravel)
- Returns typed collection of Result objects (Success/Failure)
- Note: `MockDriver` does NOT implement pools - sequential execution only

**Collection Classes** - Type-safe request/response containers:
- `HttpRequestList` - Immutable collection of HttpRequest objects
  - Factory methods: `empty()`, `of(...)`, `fromArray(array)`
  - Implements `Countable`, `IteratorAggregate`
  - Query methods: `all()`, `first()`, `last()`, `isEmpty()`, `count()`
  - Mutators: `withAppended()`, `withPrepended()`, `filter()`
- `HttpResponseList` - Immutable collection of Result<HttpResponse> objects
  - Factory methods: `empty()`, `of(...)`, `fromArray(array)`
  - Implements `Countable`, `IteratorAggregate`
  - Query methods: `successful()`, `failed()`, `hasFailures()`, `successCount()`, `failureCount()`
  - Mutators: `withAppended()`, `filter()`, `map()`
- Both use `ArrayList<T>` internally for storage
- Eliminates raw array usage in pool APIs

**Middleware System** - Onion-pattern request/response processing:
- `MiddlewareStack` - manages ordered collection with named middleware
- `MiddlewareHandler` - executes stack recursively around driver
- `HttpMiddleware` contract: `handle(HttpRequest, next) → HttpResponse`
- Built-in: BufferResponse, EventSource, StreamByLine, RecordReplay
- Examples: Example1-3Middleware for reference implementations

**Data Layer**:
- `HttpRequest` - immutable request container (url, method, headers, body, options)
- `HttpResponse` - interface for response data (status, headers, body, streaming)
- `HttpRequestList` - typed collection of HttpRequest objects
- `HttpResponseList` - typed collection of Result<HttpResponse> objects
- `PendingHttpResponse` - lazy executor that triggers middleware stack + driver
- `PendingHttpPool` - manages concurrent request execution with deferred execution model

## Exception Hierarchy (v1.7+)

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
├── MiddlewareStack (BufferResponse + custom)
│   └── MiddlewareHandler (recursive execution)
│       └── Driver (Guzzle/Laravel/Symfony/Curl)
│           ├── Native HTTP Client
│           └── HttpExceptionFactory (error mapping)
├── Events (debugging/monitoring)
└── PendingHttpPool (concurrent execution)
    └── PoolDriver (driver-specific pool implementation)
        ├── HttpRequestList → [HttpRequest, ...]
        └── HttpResponseList ← [Result<HttpResponse>, ...]
```

**Request Flow**: `HttpRequest` → `MiddlewareStack.decorate(driver)` → `MiddlewareHandler` → `Driver.handle()` → `HttpResponse`

**Error Flow**: Native Exception → `HttpExceptionFactory.fromDriverException()` → Custom Exception → Event Dispatch

**Pool Flow**: `HttpRequestList` → `PendingHttpPool` → `PoolDriver.pool()` → Concurrent Execution → `HttpResponseList`

**Collection Flow**:
- Input: Array or variadic args → `HttpRequestList::of(...)` or `::fromArray()`
- Output: `HttpResponseList` → iterate or `->all()` for array access
- Results: Each element is `Result<HttpResponse>` (Success/Failure monad)

**Configuration**: Preset-based config resolution supports environment-specific defaults while allowing per-client overrides.

**Immutability**: All client operations and collections return new instances, enabling safe concurrent usage and configuration sharing.

## Advanced Features

### Concurrent Request Processing
- **Pool Support**: All major drivers implement `CanHandleRequestPool` for concurrent execution
- **Typed Collections**: `HttpRequestList` input, `HttpResponseList` output with Result monads
- **Driver-Specific Pools**:
  - `GuzzlePool` - Native Guzzle\Pool with promises
  - `LaravelPool` - Batched execution using Laravel HTTP client pool
  - `SymfonyPool` - Streaming response handling with Symfony HTTP client
  - `CurlPool` / `CurlNewPool` - curl_multi based concurrent execution
  - `MockDriver` - Does NOT support pools (sequential only via handle())
- **Configurable Concurrency**: `maxConcurrent` parameter controls parallel request limits
- **Mixed Results**: Pools handle success/failure combinations gracefully via Result monad
- **Order Preservation**: Response order matches request order (normalized internally)

### Collection API Benefits
- **Type Safety**: Compile-time checking for request/response collections
- **Functional Operations**: `filter()`, `map()`, `successful()`, `failed()`
- **Iteration Support**: Implements `IteratorAggregate` for foreach loops
- **Query Methods**: `isEmpty()`, `count()`, `first()`, `last()`
- **Analysis**: `hasFailures()`, `successCount()`, `failureCount()`
- **Immutability**: All operations return new instances

### Streaming & Real-time Processing
- **Server-Sent Events**: `EventSourceMiddleware` with listener system
- **Progressive Streaming**: `StreamByLineMiddleware` for line-by-line processing
- **Buffering Control**: Configurable response buffering via middleware
- **Debug Integration**: Real-time stream monitoring with `DispatchDebugEvents`

### Testing & Development
- **Record/Replay**: `RecordReplayMiddleware` for HTTP interaction capture
- **Mock Driver**: Full-featured mocking with expectations and callbacks
  - Fluent DSL: `$mock->on()->post()->withJsonSubset()->replyJson()`
  - Does NOT support concurrent pools - sequential execution only
- **Integration Test Server**: Built-in HTTP server for reliable testing
- **Debug Events**: Comprehensive debugging event system

### Error Handling Improvements
- **Status Code Handling**: Configurable error throwing via `failOnError` flag
- **Retry Indicators**: Built-in retry logic guidance via `isRetriable()` method
- **Context Preservation**: Full request/response context in all exceptions
- **Event Integration**: Error events for monitoring and logging integration
- **Pool Error Handling**: Individual request failures don't abort entire pool
  - Failed requests return `Result::failure()` in `HttpResponseList`
  - Success/failure mixed results handled gracefully

## Integration Strategy

The package eliminates HTTP client lock-in:
- **Instance Injection**: Accepts pre-configured client instances from DI containers
- **Preset System**: Environment-specific configurations (Laravel, Symfony, etc.)
- **Middleware Compatibility**: Consistent processing layer regardless of underlying client
- **Exception Consistency**: Same exception hierarchy across all HTTP implementations
- **Pool Abstraction**: Concurrent request handling without driver coupling
- **Collection Types**: Type-safe APIs eliminate array-related bugs
- **Driver Flexibility**: Easy switching between Guzzle, Laravel, Symfony, Curl implementations

## Design Principles Applied

**Type Safety**:
- Collections replace raw arrays for requests/responses
- Result monad for pool responses (Success/Failure)
- Strict typing throughout the codebase

**Immutability**:
- All data objects are readonly where possible
- HttpClient operations return new instances
- Collections follow functional immutability patterns

**Separation of Concerns**:
- Driver abstraction isolates HTTP client specifics
- Pool implementations separate concurrent execution logic
- Collections encapsulate collection behavior
- Middleware provides cross-cutting concerns

**Clean Code**:
- Single responsibility for each class
- Dependency injection throughout
- Interface-based contracts (CanHandleHttpRequest, CanHandleRequestPool)
- No nested control structures beyond 1-2 levels

**SOLID Principles**:
- Single Responsibility: Each driver/pool/collection has one job
- Open/Closed: Extensible via middleware and driver implementations
- Liskov Substitution: All drivers/pools are interchangeable
- Interface Segregation: Separate contracts for request handling and pools
- Dependency Inversion: Depend on interfaces, not concrete implementations

This design allows InstructorPHP and Polyglot to integrate transparently into any PHP application stack while maintaining performance, type safety, leveraging existing HTTP infrastructure, and providing robust error handling with intelligent retry capabilities.
