# HTTP Client Adapter - API Overview

## Core Classes

### `HttpClient`
Primary client for making HTTP requests with middleware support.
- `HttpClient::default()` - Creates default client instance
- `HttpClient::using(string $preset)` - Creates client with specific preset
- `withRequest(HttpRequest $request)` - Returns `PendingHttpResponse` for request execution
- `withMiddleware(HttpMiddleware $middleware, ?string $name)` - Adds middleware to client
- `withoutMiddleware(string $name)` - Removes named middleware
- `withMiddlewareStack(MiddlewareStack $stack)` - Replaces entire middleware stack

### `HttpClientBuilder`
Fluent builder for configuring HTTP clients.
- `using(string $preset)` - Sets configuration preset
- `withConfig(HttpClientConfig $config)` - Sets custom configuration
- `withDsn(string $dsn)` - Configures from DSN string
- `withDriver(CanHandleHttpRequest $driver)` - Sets custom HTTP driver
- `withMiddleware(HttpMiddleware ...$middleware)` - Adds middleware
- `withEventBus(CanHandleEvents $events)` - Sets event dispatcher
- `create()` - Builds and returns `HttpClient`

### `HttpRequest`
HTTP request data container.
- `__construct(string $url, string $method, array $headers, string|array $body, array $options)`
- `url()`, `method()`, `headers()`, `body()`, `options()` - Getters
- `isStreamed()` - Checks if request is configured for streaming
- `withStreaming(bool $streaming)` - Enables/disables streaming

### `PendingHttpResponse`
Lazy HTTP response executor.
- `get()` - Executes request and returns `HttpResponse`
- `statusCode()`, `headers()`, `content()` - Direct access to response data
- `stream(?int $chunkSize)` - Stream response in chunks

### `HttpResponse` (Interface)
Response contract for HTTP responses.
- `statusCode()` - HTTP status code
- `headers()` - Response headers array
- `body()` - Response body as string
- `isStreamed()` - Whether response is streamed
- `stream(?int $chunkSize)` - Stream response chunks

### `MiddlewareStack`
Manages HTTP middleware execution order.
- `append(HttpMiddleware $middleware, ?string $name)` - Adds middleware to end
- `prepend(HttpMiddleware $middleware, ?string $name)` - Adds middleware to start
- `remove(string $name)` - Removes named middleware
- `replace(string $name, HttpMiddleware $middleware)` - Replaces middleware
- `clear()` - Removes all middleware

## Key Middleware

### `BufferResponseMiddleware`
Buffers responses for multiple reads (auto-included).

### `EventSourceMiddleware`
Debug middleware with event listeners for request/response tracking.
- `withListeners(CanListenToHttpEvents ...$listeners)` - Adds event listeners

### `StreamByLineMiddleware`
Processes streaming responses line by line.
- `__construct(?callable $parser, ?EventDispatcherInterface $events)`

### `RecordReplayMiddleware`
Records HTTP interactions and replays them for testing.
- `__construct(string $mode, ?string $storageDir, bool $fallbackToRealRequests)`
- `setMode(string $mode)` - Changes between 'pass', 'record', 'replay' modes
- `getRecords()` - Access recorded interactions

### `HttpMiddleware` (Interface)
Contract for custom middleware.
- `handle(HttpRequest $request, CanHandleHttpRequest $next)` - Middleware handler

## Usage Pattern

```php
$client = HttpClient::default()
    ->withMiddleware(new CustomMiddleware());

$response = $client->withRequest(new HttpRequest(
    url: 'https://api.example.com/data',
    method: 'GET',
    headers: ['Accept' => 'application/json'],
    body: [],
    options: ['timeout' => 30]
))->get();
```