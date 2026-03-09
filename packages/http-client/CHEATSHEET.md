# HTTP Client Cheat Sheet

Immutability rule: `with*()` methods return new instances.

## Core Entry Points

### `CanSendHttpRequests`
- `send(HttpRequest $request): PendingHttpResponse`

### `HttpClient`
- `HttpClient::default(): HttpClient`
- `HttpClient::fromConfig(HttpClientConfig $config): HttpClient`
- implements `CanSendHttpRequests`
- `withMiddleware(HttpMiddleware $middleware, ?string $name = null): HttpClient`
- `withoutMiddleware(string $name): HttpClient`
- `withMiddlewareStack(MiddlewareStack $stack): HttpClient`

### `HttpClientBuilder`
- `withConfig(HttpClientConfig $config): self`
- `withDsn(string $dsn): self`
- `withDriver(CanHandleHttpRequest $driver): self`
- `withClientInstance(string $driverName, object $clientInstance): self`
- `withMiddleware(HttpMiddleware ...$middleware): self`
- `withMock(?callable $configure = null): self`
- `withEventBus(CanHandleEvents $events): self`
- `create(): HttpClient`

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
- For pooled request results, see `packages/http-pool`.

## Middleware

### Contracts and Stack
- `HttpMiddleware::handle(HttpRequest $request, CanHandleHttpRequest $next): HttpResponse`
- `MiddlewareStack`: `append`, `appendMany`, `prepend`, `prependMany`, `remove`, `replace`, `clear`, `all`, `has`, `get`, `filter`, `decorate`

## Drivers

Built-in driver names:
- `curl`
- `exthttp`
- `guzzle`
- `symfony`

## Exceptions

- Base: `HttpRequestException`
- Network: `NetworkException`, `ConnectionException`, `TimeoutException`
- HTTP status: `HttpClientErrorException` (4xx), `ServerErrorException` (5xx)
- `HttpExceptionFactory::fromStatusCode(...)`

## Minimal Usage

### Basic request

```php
use Cognesy\Http\Contracts\CanSendHttpRequests;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\HttpClient;

$client = HttpClient::default(); // default CanSendHttpRequests implementation

$request = new HttpRequest(
    url: 'https://api.example.com/health',
    method: 'GET',
    headers: ['Accept' => 'application/json'],
    body: '',
    options: [],
);

$response = $client->send($request)->get();
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

foreach ($client->send($request)->stream() as $chunk) {
    echo $chunk;
}
```

### Request pooling

Use `packages/http-pool`.

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
