# HTTP Client Adapter - API Overview

Immutability rule: `with*()` methods return a new instance. Reassign (`$client = $client->withMiddleware(...)`) instead of relying on in-place mutation.

## Core Classes

### `HttpClient`
Primary client for making HTTP requests with middleware support.
- `HttpClient::default()` - Creates default client instance
- `HttpClient::using(string $preset)` - Creates client with specific preset ('guzzle', 'laravel', 'symfony', 'curl', 'mock')
- `withRequest(HttpRequest $request)` - Returns `PendingHttpResponse` for request execution
- `pool(HttpRequestList $requests, ?int $maxConcurrent)` - Executes concurrent requests, returns `HttpResponseList`
- `withPool(HttpRequestList $requests)` - Returns `PendingHttpPool` for deferred execution
- `withMiddleware(HttpMiddleware $middleware, ?string $name)` - Returns new client with middleware added
- `withoutMiddleware(string $name)` - Returns new client with named middleware removed
- `withMiddlewareStack(MiddlewareStack $stack)` - Returns new client with replaced middleware stack
- `withPoolHandler(CanHandleRequestPool $poolHandler)` - Returns new client with explicit pool handler override

### `HttpClientBuilder`
Fluent builder for configuring HTTP clients.
- `using(string $preset)` - Sets configuration preset
- `withConfig(HttpClientConfig $config)` - Sets custom configuration
- `withDsn(string $dsn)` - Configures from DSN string
- `withDriver(CanHandleHttpRequest $driver)` - Sets custom HTTP driver
- `withPoolHandler(CanHandleRequestPool $poolHandler)` - Sets explicit pool handler (custom/mock pooling)
- `withMiddleware(HttpMiddleware ...$middleware)` - Adds middleware
- `withEventBus(CanHandleEvents $events)` - Sets event dispatcher
- `withMock(callable $configurator)` - Configures mock driver for testing
- `create()` - Builds and returns `HttpClient`

### `HttpClientDriverFactory`
Driver and pool handler registration hooks.
- `registerDriver(string $name, string|callable $driver)` - Registers custom driver constructor
- `registerPoolHandler(string $name, string|callable $poolHandler)` - Registers custom pool handler constructor

### `HttpRequest`
HTTP request data container (immutable).
- `__construct(string $url, string $method, array $headers, string|array $body, array $options)`
- `url()`, `method()`, `headers()`, `body()`, `options()` - Getters
- `isStreamed()` - Checks if request is configured for streaming
- `withStreaming(bool $streaming)` - Returns new instance with streaming enabled/disabled
- `toArray()`, `fromArray(array $data)` - Serialization support

### `HttpRequestList`
Typed collection for HTTP requests (immutable).
- `HttpRequestList::empty()` - Creates empty collection
- `HttpRequestList::of(HttpRequest ...$requests)` - Creates from variadic args
- `HttpRequestList::fromArray(array $requests)` - Creates from array
- `all()` - Returns array of all requests
- `first()`, `last()` - Access first/last request
- `isEmpty()`, `count()` - Query methods
- `withAppended(HttpRequest $request)` - Returns new collection with request added
- `filter(callable $predicate)` - Returns filtered collection
- Implements `Countable`, `IteratorAggregate`

### `HttpResponseList`
Typed collection for HTTP responses (immutable).
- `HttpResponseList::empty()` - Creates empty collection
- `HttpResponseList::of(Result ...$responses)` - Creates from variadic args
- `HttpResponseList::fromArray(array $responses)` - Creates from array of Result objects
- `all()` - Returns array of all Result objects
- `successful()` - Returns array of successful HttpResponse objects
- `failed()` - Returns array of errors from failed requests
- `hasFailures()`, `hasSuccesses()` - Boolean checks
- `successCount()`, `failureCount()` - Count methods
- `withAppended(Result $response)` - Returns new collection with response added
- `filter(callable $predicate)`, `map(callable $mapper)` - Functional operations
- Implements `Countable`, `IteratorAggregate`

### `PendingHttpResponse`
Lazy HTTP response executor.
- `get()` - Executes request and returns `HttpResponse`
- `statusCode()`, `headers()`, `content()` - Direct access to response data
- `stream()` - Stream response in chunks (chunk size configured in `HttpClientConfig`)

### `PendingHttpPool`
Deferred pool execution (immutable).
- `__construct(HttpRequestList $requests, CanHandleRequestPool $poolHandler)`
- `all(?int $maxConcurrent)` - Executes all requests and returns `HttpResponseList`

### `HttpResponse` (Interface)
Response contract for HTTP responses.
- `statusCode()` - HTTP status code
- `headers()` - Response headers array
- `body()` - Response body as string
- `isStreamed()` - Whether response is streamed
- `stream()` - Stream response chunks (chunk size configured in `HttpClientConfig`)
- `toArray()` - Serialization support

### `MiddlewareStack`
Manages HTTP middleware execution order (immutable).
- `append(HttpMiddleware $middleware, ?string $name)` - Returns new stack with middleware added to end
- `prepend(HttpMiddleware $middleware, ?string $name)` - Returns new stack with middleware added to start
- `remove(string $name)` - Returns new stack with named middleware removed
- `replace(string $name, HttpMiddleware $middleware)` - Returns new stack with middleware replaced
- `clear()` - Returns new empty stack

## Key Middleware

### `BufferResponseMiddleware`
Buffers responses for multiple reads (auto-included by default).

### `EventSourceMiddleware`
Processes Server-Sent Events (SSE) with event listeners.
- `withListeners(CanListenToHttpEvents ...$listeners)` - Adds event listeners
- Handles streaming SSE responses with proper event parsing

### `StreamByLineMiddleware`
Processes streaming responses line by line.
- `__construct(?callable $parser, ?EventDispatcherInterface $events)`
- Useful for newline-delimited JSON (NDJSON) streams

### `RecordReplayMiddleware`
Records HTTP interactions and replays them for testing.
- `__construct(string $mode, ?string $storageDir, bool $fallbackToRealRequests)`
- `setMode(string $mode)` - Changes between 'pass', 'record', 'replay' modes
- `getRecords()` - Access recorded interactions

### `HttpMiddleware` (Interface)
Contract for custom middleware.
- `handle(HttpRequest $request, CanHandleHttpRequest $next)` - Middleware handler
- Follows onion/pipeline pattern

## Exception Hierarchy

### `HttpRequestException`
Base exception for all HTTP errors.
- `getRequest()` - Get the request that caused the exception
- `getResponse()` - Get the response (if available)
- `getDuration()` - Get request duration in seconds
- `getStatusCode()` - Get HTTP status code (if available)
- `isRetriable()` - Check if error is retriable

### `NetworkException` extends `HttpRequestException`
Connection and transport errors (retriable).
- `ConnectionException` - DNS, connection refused
- `TimeoutException` - Request/connection timeouts

### `HttpClientErrorException` extends `HttpRequestException`
4xx client errors (only 429 is retriable).

### `ServerErrorException` extends `HttpRequestException`
5xx server errors (all retriable).

## Drivers

Available HTTP drivers (via `HttpClient::using()`):
- `'guzzle'` - Guzzle HTTP client
- `'laravel'` - Laravel HTTP client
- `'symfony'` - Symfony HTTP client
- `'curl'` - Legacy cURL implementation
- `'curl-new'` - Modern cURL implementation with better streaming
- `'mock'` - Mock driver for testing

## Usage Patterns

### Basic Request

```php
use Cognesy\Http\HttpClient;
use Cognesy\Http\Data\HttpRequest;

$client = HttpClient::default();

$response = $client->withRequest(new HttpRequest(
    url: 'https://api.example.com/data',
    method: 'GET',
    headers: ['Accept' => 'application/json'],
    body: [],
    options: ['timeout' => 30]
))->get();

echo $response->body();
```

### Concurrent Requests (Pool)

```php
use Cognesy\Http\Collections\HttpRequestList;
use Cognesy\Http\Data\HttpRequest;

$requests = HttpRequestList::of(
    new HttpRequest('https://api.example.com/users/1', 'GET'),
    new HttpRequest('https://api.example.com/users/2', 'GET'),
    new HttpRequest('https://api.example.com/users/3', 'GET'),
);

// Immediate execution
$responses = $client->pool($requests, maxConcurrent: 3);

// Check results
foreach ($responses as $result) {
    if ($result->isSuccess()) {
        $response = $result->unwrap();
        echo $response->body();
    } else {
        $error = $result->error();
        echo "Failed: " . $error->getMessage();
    }
}

// Analyze results
echo "Success: " . $responses->successCount();
echo "Failed: " . $responses->failureCount();
```

### Deferred Pool Execution

```php
$pendingPool = $client->withPool($requests);

// Execute later
$responses = $pendingPool->all(maxConcurrent: 5);
```

### Streaming Response

```php
$request = (new HttpRequest(
    url: 'https://api.example.com/stream',
    method: 'GET',
    headers: [],
    body: [],
    options: ['stream' => true]
))->withStreaming(true);

$stream = $client->withRequest($request)->stream();

foreach ($stream as $chunk) {
    echo $chunk;
}
```

### Server-Sent Events (SSE)

```php
use Cognesy\Http\Middleware\EventSourceMiddleware;

$listener = new class implements CanListenToHttpEvents {
    public function onEvent(string $event, array $data): void {
        echo "Event: $event\n";
        print_r($data);
    }
};

$client = HttpClient::default()
    ->withMiddleware(
        (new EventSourceMiddleware())->withListeners($listener)
    );

$request = (new HttpRequest(
    url: 'https://api.example.com/events',
    method: 'GET',
    headers: ['Accept' => 'text/event-stream'],
    body: [],
    options: []
))->withStreaming(true);

$client->withRequest($request)->get();
```

### Custom Middleware

```php
use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\HttpMiddleware;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;

class LoggingMiddleware implements HttpMiddleware {
    #[\Override]
    public function handle(HttpRequest $request, CanHandleHttpRequest $next): HttpResponse {
        $start = microtime(true);

        $response = $next->handle($request);

        $duration = microtime(true) - $start;
        error_log("Request to {$request->url()} took {$duration}s");

        return $response;
    }
}

$client = HttpClient::default()
    ->withMiddleware(new LoggingMiddleware());
```

### Mock Driver for Testing

```php
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Drivers\Mock\MockHttpResponseFactory;

$client = (new HttpClientBuilder())
    ->withMock(function ($mock) {
        $mock->on()
            ->post('https://api.example.com/data')
            ->withJsonSubset(['user_id' => 123])
            ->replyJson(['success' => true, 'id' => 456]);
    })
    ->create();

// Test your code that uses $client
```

### Error Handling

```php
use Cognesy\Http\Exceptions\HttpClientErrorException;
use Cognesy\Http\Exceptions\ServerErrorException;
use Cognesy\Http\Exceptions\TimeoutException;

try {
    $response = $client->withRequest($request)->get();
} catch (TimeoutException $e) {
    // Handle timeout - usually retriable
    echo "Request timed out after {$e->getDuration()}s";
} catch (HttpClientErrorException $e) {
    // Handle 4xx errors
    if ($e->getStatusCode() === 429) {
        // Rate limited - retriable
        echo "Rate limited, retry after delay";
    } else {
        echo "Client error: {$e->getStatusCode()}";
    }
} catch (ServerErrorException $e) {
    // Handle 5xx errors - usually retriable
    echo "Server error: {$e->getStatusCode()}";
}
```

## Configuration

### Using Presets

```php
// Laravel preset
$client = HttpClient::using('laravel');

// Guzzle preset
$client = HttpClient::using('guzzle');

// Symfony preset
$client = HttpClient::using('symfony');
```

### Custom Configuration

```php
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Creation\HttpClientBuilder;

$config = new HttpClientConfig(
    driver: 'guzzle',
    connectTimeout: 5,
    requestTimeout: 30,
    maxConcurrent: 10,
    failOnError: true,
    streamChunkSize: 1024,
);

$client = (new HttpClientBuilder())
    ->withConfig($config)
    ->create();
```

## Collection Utilities

### Filtering Successful Responses

```php
$results = $client->pool($requests);

// Get only successful responses
$successfulResponses = $results->successful();

foreach ($successfulResponses as $response) {
    echo $response->body();
}
```

### Handling Failures

```php
$results = $client->pool($requests);

if ($results->hasFailures()) {
    $errors = $results->failed();

    foreach ($errors as $error) {
        if ($error instanceof \Throwable) {
            echo "Error: " . $error->getMessage();
        }
    }
}
```

### Custom Result Processing

```php
$results = $client->pool($requests);

// Map responses to extract specific data
$processedResults = $results->map(function($result) {
    if ($result->isSuccess()) {
        $response = $result->unwrap();
        return json_decode($response->body(), true);
    }
    return null;
});

// Filter out failed requests
$successOnly = $results->filter(fn($r) => $r->isSuccess());
```

## Best Practices

1. **Use Collections**: Always use `HttpRequestList` for pools, not raw arrays
2. **Check Result Status**: Pool responses are Result monads - check `isSuccess()` before unwrapping
3. **Handle Errors**: Use try-catch for single requests, check Result status for pools
4. **Set Timeouts**: Always configure appropriate timeouts for your use case
5. **Use Middleware**: Leverage middleware for cross-cutting concerns (logging, caching, etc.)
6. **Mock in Tests**: Use the mock driver with fluent expectations for unit tests
7. **Immutability**: Remember all operations return new instances
8. **Type Safety**: Leverage typed collections for compile-time safety
