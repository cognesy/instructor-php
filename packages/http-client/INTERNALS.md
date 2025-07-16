# HTTP Client Package - Internal Design

## Core Architecture

**HttpClient** serves as the unified public API that abstracts underlying HTTP implementations (Guzzle, Laravel, Symfony) behind a consistent interface. The design enables seamless integration into diverse PHP environments without coupling to specific HTTP client libraries.

### Key Components

**HttpClient** - Main entry point providing immutable fluent API:
- `HttpClient::default()` / `HttpClient::using(preset)` - Factory methods
- `withRequest(HttpRequest)` → `PendingHttpResponse` - Lazy execution model
- `PendingHttpResponse` - Represents a pending execution, let's you choose and chain further operations (e.g., regular call vs streaming)
- `withMiddleware()` / `withoutMiddleware()` - Stack manipulation
- Internally wraps: `CanHandleHttpRequest` driver + `MiddlewareStack`

**HttpClientBuilder** - Fluent configuration builder:
- Resolves driver from config presets or explicit instances
- Assembles middleware stack (default: BufferResponse + optional debug)
- Manages event dispatching and dependency injection

**Driver Abstraction** - `CanHandleHttpRequest` contract:
- Single method: `handle(HttpRequest) → HttpResponse`
- Implementations: `GuzzleDriver`, `LaravelDriver`, `SymfonyDriver`, `MockDriver`
- Each driver wraps native client (Guzzle\Client, Laravel\Factory, Symfony\Client)
- Standardizes config mapping (timeouts, headers, streaming)

**Middleware System** - Onion-pattern request/response processing:
- `MiddlewareStack` - manages ordered collection with named middleware
- `MiddlewareHandler` - executes stack recursively around driver
- `HttpMiddleware` contract: `handle(HttpRequest, next) → HttpResponse`
- Built-in: BufferResponse, EventSource, StreamByLine, RecordReplay

**Data Layer**:
- `HttpRequest` - immutable request container (url, method, headers, body, options)
- `HttpResponse` - interface for response data (status, headers, body, streaming)
- `PendingHttpResponse` - lazy executor that triggers middleware stack + driver

## Mental Model

```
HttpClient
├── MiddlewareStack (BufferResponse + custom)
│   └── MiddlewareHandler (recursive execution)
│       └── Driver (Guzzle/Laravel/Symfony)
│           └── Native HTTP Client
└── Events (optional debugging/monitoring)
```

**Request Flow**: `HttpRequest` → `MiddlewareStack.decorate(driver)` → `MiddlewareHandler` → `Driver.handle()` → `HttpResponse`

**Configuration**: Preset-based config resolution supports environment-specific defaults while allowing per-client overrides.

**Immutability**: All client operations return new instances, enabling safe concurrent usage and configuration sharing.

## Integration Strategy

The package eliminates HTTP client lock-in:
- **Instance Injection**: Accepts pre-configured client instances from DI containers
- **Preset System**: Environment-specific configurations (Laravel, Symfony, etc.)
- **Middleware Compatibility**: Consistent processing layer regardless of underlying client

This design allows InstructorPHP to integrate transparently into any PHP application stack while maintaining performance and leveraging existing HTTP infrastructure.