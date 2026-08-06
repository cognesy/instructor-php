---
title: HTTP Client
description: Framework-agnostic HTTP client — requests, responses, streaming, pooling, and middleware
package: http-client
---

# HTTP Client Cheat Sheet

Immutability rule: `with*()` methods return new instances.

## Core Entry Points

### `CanSendHttpRequests`
- `send(HttpRequest $request): PendingHttpResponse`

### `HttpClient`
- `HttpClient::default(): HttpClient`
- `HttpClient::using(string $preset, ?string $basePath = null): HttpClient`
- `HttpClient::fromConfig(HttpClientConfig $config): HttpClient`
- `HttpClient::fromDriver(CanHandleHttpRequest $driver): HttpClient`
- implements `CanSendHttpRequests`
- `send(HttpRequest $request): PendingHttpResponse`
- `withMiddleware(HttpMiddleware $middleware, ?string $name = null): HttpClient`
- `withoutMiddleware(string $name): HttpClient`
- `withMiddlewareStack(MiddlewareStack $stack): HttpClient`
- `runtime(): HttpClientRuntime`
- `config(): HttpClientConfig`
- `withSSEStream(): HttpClient` is deprecated

### `HttpClientRuntime`
- `HttpClientRuntime::fromConfig(?HttpClientConfig $config, ?CanHandleEvents $events, ?CanHandleHttpRequest $driver, ?CanProvideHttpDrivers $drivers, ?object $clientInstance, ?MiddlewareStack $middlewareStack): HttpClientRuntime` (all parameters optional)
- `client(): HttpClient`
- `send(HttpRequest $request): PendingHttpResponse`
- `withMiddleware(HttpMiddleware $middleware, ?string $name = null): self`
- `withoutMiddleware(string $name): self`
- `withMiddlewareStack(MiddlewareStack $stack): self`
- `driver(): CanHandleHttpRequest`
- `middlewareStack(): MiddlewareStack`
- `events(): CanHandleEvents`
- `config(): HttpClientConfig`

### `HttpClientBuilder`
- `new HttpClientBuilder(?CanHandleEvents $events = null)`
- `withConfig(HttpClientConfig $config): self`
- `withDsn(string $dsn): self`
- `withDebugConfig(DebugConfig $debugConfig): self`
- `withDriver(CanHandleHttpRequest $driver): self`
- `withDrivers(CanProvideHttpDrivers $drivers): self`
- `withClientInstance(string $driverName, object $clientInstance): self`
- `withMiddleware(HttpMiddleware ...$middleware): self`
- `withRetryPolicy(RetryPolicy $policy): self`
- `withCircuitBreakerPolicy(CircuitBreakerPolicy $policy, ?CanStoreCircuitBreakerState $stateStore = null): self`
- `withIdempotencyMiddleware(IdempotencyMiddleware $middleware): self`
- `withMock(?callable $configure = null): self`
- `withEventBus(CanHandleEvents $events): self`
- `create(): HttpClient`
- `createRuntime(): HttpClientRuntime`

### `HttpClientConfigFactory`
- `default(): HttpClientConfig`

## Config

### `HttpClientConfig`
Constructor fields:
- `driver`
- `connectTimeout`
- `requestTimeout`
- `idleTimeout`
- `streamChunkSize`
- `streamHeaderTimeout`
- `failOnError`

Methods:
- `HttpClientConfig::group(): string`
- `HttpClientConfig::fromPreset(string $preset, ?string $basePath = null): HttpClientConfig`
- `HttpClientConfig::fromDsn(string $dsn): HttpClientConfig`
- `HttpClientConfig::fromArray(array $config): HttpClientConfig`
- `withOverrides(array $overrides): self`
- `toArray(): array`

Available presets: `curl`, `guzzle`, `symfony`, `http-ollama`

### `DebugConfig`
Constructor fields:
- `httpEnabled`
- `httpTrace`
- `httpRequestUrl`
- `httpRequestHeaders`
- `httpRequestBody`
- `httpResponseHeaders`
- `httpResponseBody`
- `httpResponseStream`
- `httpResponseStreamByLine`

Methods:
- `DebugConfig::group(): string`
- `DebugConfig::fromPreset(string $preset, ?string $basePath = null): DebugConfig`
- `DebugConfig::fromArray(array $config): DebugConfig`
- `withOverrides(array $overrides): self`
- `toArray(): array`

## Data Types

### `HttpRequest`
Constructor:
- `new HttpRequest(string $url, string $method, array $headers, string|array $body, array $options, ?string $id = null, ?DateTimeImmutable $createdAt = null, ?DateTimeImmutable $updatedAt = null, ?Metadata $metadata = null)`

Readonly lifecycle fields:
- `id`
- `createdAt`
- `updatedAt`
- `metadata`

Methods:
- `url(): string`
- `method(): string`
- `headers(?string $key = null): mixed`
- `body(): HttpRequestBody`
- `options(): array`
- `isStreamed(): bool`
- `withHeader(string $key, string $value): self`
- `withStreaming(bool $streaming): self`
- `toArray(): array`
- `HttpRequest::fromArray(array $data): HttpRequest`

### `HttpRequestBody`
- `new HttpRequestBody(string|array $body)`
- arrays are JSON-encoded
- strings are sent verbatim
- JSON encoding failures throw `InvalidArgumentException`
- `toString(): string`
- `toArray(): array`

### `HttpResponse`
Factories:
- `HttpResponse::sync(int $statusCode, array $headers, string $body): HttpResponse`
- `HttpResponse::streaming(int $statusCode, array $headers, StreamInterface $stream): HttpResponse`
- `HttpResponse::empty(): HttpResponse`

Methods:
- `statusCode(): int`
- `headers(): array`
- `body(): string`
- `isStreamed(): bool`
- `isStreaming(): bool`
- `stream(): Generator`
- `rawStream(): StreamInterface`
- `withStream(StreamInterface $stream): HttpResponse`
- `toArray(): array`
- `HttpResponse::fromArray(array $data): HttpResponse`

Note:
- `body()` throws for streamed responses

### `StreamInterface`
- extends `IteratorAggregate<int, string>`
- `getIterator(): Traversable`
- `isCompleted(): bool`

### `PendingHttpResponse`
- `get(): HttpResponse`
- `statusCode(): int`
- `headers(): array`
- `content(): string`
- `stream(): Generator`

Notes:
- sync and streamed execution are cached separately
- `get()` follows the request streaming flag

## Collections

### `HttpRequestList`
- stores `HttpRequest`
- `empty()`, `of(...)`, `fromArray(...)`, `fromSerializedArray(...)`
- `all()`, `first()`, `last()`, `isEmpty()`, `count()`, `getIterator()`
- `withAppended()`, `withPrepended()`, `filter()`
- `toArray()`

### `HttpResponseList`
- stores `Result<HttpResponse, mixed>`
- `empty()`, `of(...)`, `fromArray(...)`, `fromSerializedArray(...)`
- `all()`, `first()`, `last()`, `isEmpty()`, `count()`, `getIterator()`
- `successful(): list<HttpResponse>`
- `failed(): list<mixed>`
- `hasFailures()`, `hasSuccesses()`
- `successCount()`, `failureCount()`
- `withAppended(Result $response)`, `filter()`, `map()`
- `toArray()`
- pooled request results live in `packages/http-pool`

## Middleware

### Contracts and Stack
- `HttpMiddleware::handle(HttpRequest $request, CanHandleHttpRequest $next): HttpResponse`
- `MiddlewareStack`: `append`, `appendMany`, `prepend`, `prependMany`, `remove`, `replace`, `clear`, `all`, `has`, `get`, `filter`, `decorate`, `toDebugArray`

### Built-in Middleware
- `RetryMiddleware(RetryPolicy $policy)`
- `CircuitBreakerMiddleware(CircuitBreakerPolicy $policy, ?CanStoreCircuitBreakerState $store)`
- `IdempotencyMiddleware(string $headerName, array $methods, ?array $hostAllowList, ?callable $keyProvider)`
- `EventSourceMiddleware(bool $enabled)`
  - `withListeners(CanListenToHttpEvents ...$listeners): self`
  - `withParser(callable $parser): self`
- `RecordReplayMiddleware::recordTo(string $directory, ?RecordReplayPolicy $policy = null, ?EventDispatcherInterface $events = null): self`
- `RecordReplayMiddleware::replayFrom(string $directory, ?RecordReplayPolicy $policy = null, ?EventDispatcherInterface $events = null): self`
- `RecordReplayMiddleware::recordWith(CassetteStore $store, ?RecordReplayPolicy $policy = null, ?EventDispatcherInterface $events = null): self`
- `RecordReplayMiddleware::replayWith(CassetteStore $store, ?RecordReplayPolicy $policy = null, ?EventDispatcherInterface $events = null): self`
- `RecordReplayPolicy(RequestMatcher $matcher, FixtureSanitizer $sanitizer, ReplayMissPolicy $onMissing)`
- replay is hermetic by default; use `ReplayMissPolicy::Passthrough` explicitly for live misses
- `StreamSSEsMiddleware` (deprecated, use `EventSourceMiddleware::withParser()` instead)

### Always-on Middleware
- `TraceContextMiddleware` is appended by `HttpClientBuilder` under the name `internal:trace-context`,
  innermost in the stack so the `traceparent` it writes describes the request that actually goes
  on the wire. Remove it with `remove('internal:trace-context')` to place your own.

## Distributed Trace Propagation

Three parts, and all three must be present — this is the only supported path:

1. **Producer** — a `TraceContext`. Either a span you have open (`Telemetry::traceContext($key)`,
   or `TelemetryContinuation::context()` for a trace handed to you by an upstream caller), or
   `TraceContext::fresh()` to start one.
2. **Stamp** — `HttpRequestTelemetry::withTraceContext(HttpRequest $request, TraceContext $context): HttpRequest`.
   This is the canonical seam. It writes the context under `TraceContextMiddleware::METADATA_KEY`
   (`telemetry.trace_context`) as a serializable array, mirroring how
   `HttpRequestTelemetry::withCorrelation()` writes local operation correlation.
3. **Transport** — `TraceContextMiddleware`, registered by default (above). It injects
   `traceparent` and, when present, `tracestate`.

Without a stamp the middleware is inert: headers are left untouched. The same metadata is what
puts a trace on the envelope from `HttpRequestTelemetry::requestEnvelope()`, so stamping links
the local span tree and the remote peer's in one step.

```php
$request = HttpRequestTelemetry::withTraceContext(
    new HttpRequest($url, 'POST', [], $body, []),
    $telemetry->traceContext('llm.call'),
);
$client->send($request)->get(); // traceparent is on the wire
```

Inbound side: `TraceContextPropagator::extract(HttpRequest $request): ?TraceContext` reads the
headers back off a request, for a service continuing a caller's trace.

Record/replay notes:
- the default fingerprint includes method, credential-normalized URL, stream mode, body, and `Accept`/`Content-Type`
- explicit JSON request bodies are canonicalized; non-JSON and binary bodies remain byte-exact
- one cassette session replays interactions in strict recorded order
- streamed frames are binary-safe and replayed lazily one-shot; no PHP list contains all chunks
- common credentials are sanitized, but prompts/model outputs/PII still require fixture review

## Drivers

### Driver Contract
- `CanHandleHttpRequest::handle(HttpRequest $request): HttpResponse`

### Driver Registry
- `HttpDriverRegistry::make(): HttpDriverRegistry`
- `HttpDriverRegistry::fromArray(array $drivers): HttpDriverRegistry`
- `withDriver(string $name, string|callable $driver): self`
- `withoutDriver(string $name): self`
- `has(string $name): bool`
- `driverNames(): array`
- `makeDriver(string $name, HttpClientConfig $config, CanHandleEvents $events, ?object $clientInstance = null): CanHandleHttpRequest`

### Built-in Driver Names
- `curl`
- `guzzle`
- `symfony`

## Mock Driver

### `MockHttpDriver`
- `addResponse(HttpResponse|callable $response, string|callable|null $url = null, ?string $method = null, string|callable|null $body = null): self`
- `expect(): MockExpectation`
- `on(): MockExpectation`
- `getReceivedRequests(): array`
- `getLastRequest(): ?HttpRequest`
- `reset(): self`
- `clearResponses(): self`

### `MockExpectation`
Matchers:
- `method()`, `get()`, `post()`, `put()`, `patch()`, `delete()`
- `url()`, `urlStartsWith()`, `urlMatches()`, `path()`
- `header()`, `headers()`
- `withStream()`
- `bodyEquals()`, `bodyContains()`, `bodyMatchesRegex()`, `withJsonSubset()`, `body()`
- `times()`

Replies:
- `reply(HttpResponse|callable $response): MockHttpDriver`
- `replyJson(array|string|\JsonSerializable $data, int $status = 200, array $headers = []): MockHttpDriver`
- `replyText(string $text, int $status = 200, array $headers = []): MockHttpDriver`
- `replyStreamChunks(array $chunks, int $status = 200, array $headers = []): MockHttpDriver`
- `replySSEFromJson(array $payloads, bool $addDone = true, int $status = 200, array $headers = []): MockHttpDriver`

### `MockHttpResponseFactory`
- `success(int $statusCode = 200, array $headers = [], string $body = '', array $chunks = []): HttpResponse`
- `error(int $statusCode = 500, array $headers = [], string $body = '', array $chunks = []): HttpResponse`
- `streaming(int $statusCode = 200, array $headers = [], array $chunks = []): HttpResponse`
- `json(array|string|\JsonSerializable $data, int $statusCode = 200, array $headers = []): HttpResponse`
- `sse(array $payloads, bool $addDone = true, int $statusCode = 200, array $headers = []): HttpResponse`

Testing guidance:

- use `withMock(...)` for most deterministic client tests
- use `MockHttpDriver` directly when you need request inspection or reusable expectations
- use `MockHttpResponseFactory` for richer JSON, error, streaming, or SSE reply shapes

## Exceptions

- Base: `HttpRequestException`
- Network: `NetworkException`, `ConnectionException`, `TimeoutException`
- HTTP status: `HttpClientErrorException`, `ServerErrorException`
- Middleware-related: `CircuitBreakerOpenException`
- Factory: `HttpExceptionFactory::fromStatusCode(int $statusCode, ?HttpRequest $request, ?HttpResponse $response, ?float $duration, ?Throwable $previous): HttpRequestException`

Useful exception methods:
- `getRequest(): ?HttpRequest`
- `getResponse(): ?HttpResponse`
- `getDuration(): ?float`
- `getStatusCode(): ?int`
- `isRetriable(): bool`

## Minimal Usage

### Basic Request

```php
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\HttpClient;

$client = HttpClient::default();

$response = $client->send(new HttpRequest(
    url: 'https://api.example.com/health',
    method: 'GET',
    headers: ['Accept' => 'application/json'],
    body: '',
    options: [],
))->get();

echo $response->statusCode();
```

### Streaming Request

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

### Mock Driver

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

## Notes

- pooling lives in `packages/http-pool`
- `withDsn()` coerces values to `HttpClientConfig` field types
- `EventSourceMiddleware` is the current SSE middleware path
