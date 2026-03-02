# HTTP Client Cheat Sheet

Immutability rule: `with*()` methods return new instances. Reassign (`$client = $client->withMiddleware(...)`).

## Core Entry Points

### `HttpClient`
- `HttpClient::default(): HttpClient`
- `HttpClient::using(string $preset): HttpClient`
- `withRequest(HttpRequest $request): PendingHttpResponse`
- `pool(HttpRequestList $requests, ?int $maxConcurrent = null): HttpResponseList`
- `withPool(HttpRequestList $requests): PendingHttpPool`
- `withMiddleware(HttpMiddleware $middleware, ?string $name = null): HttpClient`
- `withoutMiddleware(string $name): HttpClient`
- `withMiddlewareStack(MiddlewareStack $stack): HttpClient`
- `withPoolHandler(CanHandleRequestPool $poolHandler): HttpClient`
- `withSSEStream(): HttpClient` (deprecated)

### `HttpClientBuilder`
- `withPreset(string $preset): self`
- `withConfig(HttpClientConfig $config): self`
- `withDsn(string $dsn): self`
- `withDriver(CanHandleHttpRequest $driver): self`
- `withPoolHandler(CanHandleRequestPool $poolHandler): self`
- `withClientInstance(string $driverName, object $clientInstance): self`
- `withMiddleware(HttpMiddleware ...$middleware): self`
- `withRetryPolicy(RetryPolicy $policy): self`
- `withCircuitBreakerPolicy(CircuitBreakerPolicy $policy, ?CanStoreCircuitBreakerState $stateStore = null): self`
- `withIdempotencyMiddleware(IdempotencyMiddleware $middleware): self`
- `withMock(?callable $configure = null): self`
- `withEventBus(CanHandleEvents $events): self`
- `create(): HttpClient`
- Deprecated aliases: `using()`, `withDebugPreset()`

## Data Types

### `HttpRequest`
Constructor:
- `new HttpRequest(string $url, string $method, array $headers, string|array $body, array $options)`
- array bodies are JSON-encoded; encoding failures throw `InvalidArgumentException` (no silent empty-body fallback)

Methods:
- `url(): string`
- `method(): string`
- `headers(?string $key = null): mixed`
- `body(): HttpRequestBody`
- `options(): array`
- `isStreamed(): bool`
- `withHeader(string $key, string $value): self`
- `withStreaming(bool $streaming): self`
- lifecycle fields are readonly (`id`, `createdAt`, `updatedAt`, `metadata`)

### `HttpResponse`
Factories:
- `HttpResponse::sync(int $statusCode, array $headers, string $body): HttpResponse`
- `HttpResponse::streaming(int $statusCode, array $headers, StreamInterface $stream): HttpResponse`
- `HttpResponse::empty(): HttpResponse`

Methods:
- `statusCode(): int`
- `headers(): array`
- `body(): string` (throws for streamed responses)
- `isStreamed(): bool`
- `isStreaming(): bool`
- `stream(): Generator`
- `rawStream(): StreamInterface`
- `withStream(StreamInterface $stream): HttpResponse`

### `PendingHttpResponse`
- `get(): HttpResponse` (uses request mode)
- `statusCode(): int`
- `headers(): array`
- `content(): string` (non-streamed path)
- `stream(): Generator`

### `PendingHttpPool`
- `all(?int $maxConcurrent = null): HttpResponseList`

## Collections

### `HttpRequestList`
- `empty()`, `of(...)`, `fromArray(...)`
- `all()`, `first()`, `last()`, `isEmpty()`, `count()`
- `withAppended()`, `withPrepended()`, `filter()`

### `HttpResponseList`
- `empty()`, `of(...)`, `fromArray(...)`
- `all()`, `first()`, `last()`, `isEmpty()`, `count()`
- `successful()`, `failed()`, `hasFailures()`, `hasSuccesses()`
- `successCount()`, `failureCount()`
- `withAppended()`, `filter()`, `map()`
- Pool entries are `Result` objects (`isSuccess()`, `unwrap()`, `error()`).

## Middleware

### Contracts and Stack
- `HttpMiddleware::handle(HttpRequest $request, CanHandleHttpRequest $next): HttpResponse`
- `MiddlewareStack`: `append`, `appendMany`, `prepend`, `prependMany`, `remove`, `replace`, `clear`, `all`, `has`, `get`, `filter`, `decorate`

### Built-in Middleware
- `RetryMiddleware` + `RetryPolicy`
- `RetryPolicy` honors `Retry-After` only for numeric seconds or RFC7231 HTTP-date values
- `CircuitBreakerMiddleware` + `CircuitBreakerPolicy`
- Circuit breaker state stores: `CanStoreCircuitBreakerState`, `InMemoryCircuitBreakerStateStore`, `ApcuCircuitBreakerStateStore`
- `IdempotencyMiddleware`
- `EventSource\EventSourceMiddleware`
- `RecordReplay\RecordReplayMiddleware`

Compatibility/deprecated path still present:
- `ServerSideEvents\*`

### `RecordReplayMiddleware` modes
- `MODE_PASS`
- `MODE_RECORD`
- `MODE_REPLAY`

## Drivers

Built-in driver names:
- `curl`
- `exthttp`
- `guzzle`
- `symfony`
- `laravel`

Built-in pool handlers exist for the same names.

Custom registration:
- `HttpClientDriverFactory::registerDriver(string $name, string|callable)`
- `HttpClientDriverFactory::registerPoolHandler(string $name, string|callable)`

## Exceptions

- Base: `HttpRequestException`
- Network: `NetworkException`, `ConnectionException`, `TimeoutException`
- HTTP status: `HttpClientErrorException` (4xx), `ServerErrorException` (5xx)
- `HttpExceptionFactory::fromStatusCode(...)`

## Minimal Usage

### Basic request

```php
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\HttpClient;

$client = HttpClient::default();

$request = new HttpRequest(
    url: 'https://api.example.com/health',
    method: 'GET',
    headers: ['Accept' => 'application/json'],
    body: '',
    options: [],
);

$response = $client->withRequest($request)->get();
echo $response->statusCode();
```

### Streaming request

```php
$request = (new HttpRequest(
    url: 'https://api.example.com/stream',
    method: 'GET',
    headers: ['Accept' => 'text/event-stream'],
    body: '',
    options: [],
))->withStreaming(true);

foreach ($client->withRequest($request)->stream() as $chunk) {
    echo $chunk;
}
```

### Pooled requests

```php
use Cognesy\Http\Collections\HttpRequestList;
use Cognesy\Http\Data\HttpRequest;

$requests = HttpRequestList::of(
    new HttpRequest('https://api.example.com/a', 'GET', [], '', []),
    new HttpRequest('https://api.example.com/b', 'GET', [], '', []),
);

$results = $client->pool($requests, maxConcurrent: 2);

foreach ($results as $result) {
    if ($result->isSuccess()) {
        $response = $result->unwrap();
        continue;
    }

    $error = $result->error();
}
```

### Mock driver

```php
use Cognesy\Http\Creation\HttpClientBuilder;

$client = (new HttpClientBuilder())
    ->withMock(function ($mock) {
        $mock->expect()
            ->get('https://api.example.com/health')
            ->replyJson(['ok' => true]);
    })
    ->create();
```

## DSN Note

`withDsn()` supports typed fields and coerces values to `HttpClientConfig` types.

Example: `driver=symfony,connectTimeout=2,requestTimeout=20,streamHeaderTimeout=5,failOnError=true`
