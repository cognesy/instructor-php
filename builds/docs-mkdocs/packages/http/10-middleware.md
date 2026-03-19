---
title: Middleware
description: 'Intercept, modify, and extend HTTP request/response behavior with middleware.'
---

Middleware is the primary extension mechanism for the HTTP client. It lets you add behaviors -- logging, retries, circuit breaking, authentication, response transformation -- without modifying drivers or request code. Each middleware sits in a pipeline: requests pass through in order on the way out, and responses pass through in reverse order on the way back.

## How Middleware Works

The middleware pipeline follows a simple pattern:

```text
Request  -> Middleware A -> Middleware B -> Middleware C -> Driver -> Server
Response <- Middleware A <- Middleware B <- Middleware C <- Driver <- Server
// @doctest id="06b0"
```

Each middleware receives the request and a reference to the next handler in the chain. It can modify the request, call the next handler, inspect or modify the response, or short-circuit the chain entirely by returning a response without calling next.

## The HttpMiddleware Interface

All middleware implements a single interface:

```php
namespace Cognesy\Http\Contracts;

interface HttpMiddleware
{
    public function handle(HttpRequest $request, CanHandleHttpRequest $next): HttpResponse;
}
// @doctest id="8799"
```

Here is a complete example that adds a header to every request:

```php
use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\HttpMiddleware;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;

final class AddHeaderMiddleware implements HttpMiddleware
{
    public function __construct(
        private string $name,
        private string $value,
    ) {}

    public function handle(HttpRequest $request, CanHandleHttpRequest $next): HttpResponse
    {
        $request = $request->withHeader($this->name, $this->value);
        return $next->handle($request);
    }
}
// @doctest id="d414"
```

## The BaseMiddleware Abstract Class

For most middleware, you do not need to implement the full `handle()` method. The `BaseMiddleware` class provides a template with overridable hooks:

```php
use Cognesy\Http\Extras\Support\BaseMiddleware;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;

final class TimingMiddleware extends BaseMiddleware
{
    private float $start;

    protected function beforeRequest(HttpRequest $request): HttpRequest
    {
        $this->start = microtime(true);
        return $request;
    }

    protected function afterRequest(HttpRequest $request, HttpResponse $response): HttpResponse
    {
        $duration = round((microtime(true) - $this->start) * 1000, 2);
        error_log("Request to {$request->url()} took {$duration}ms");
        return $response;
    }
}
// @doctest id="e602"
```

The available hooks are:

| Method | Purpose |
|--------|---------|
| `beforeRequest($request)` | Modify the request before sending. Return the (possibly modified) request. |
| `afterRequest($request, $response)` | Inspect or modify the response after receiving it. Return the response. |
| `shouldDecorateResponse($request, $response)` | Return `true` to wrap the response through `toResponse()`. Defaults to `true`. |
| `toResponse($request, $response)` | Return a decorated response (e.g., with a transformed stream). |
| `shouldExecute($request)` | Return `false` to skip this middleware entirely for a given request. |

## Registering Middleware

### On an Existing Client

The `HttpClient` is immutable. `withMiddleware()` returns a new client with the middleware appended:

```php
$client = $client->withMiddleware(new AddHeaderMiddleware('X-Request-ID', 'req-123'), 'request-id');
// @doctest id="d152"
```

The second argument is an optional name, which lets you remove the middleware later:

```php
$client = $client->withoutMiddleware('request-id');
// @doctest id="2621"
```

### Via the Builder

The builder collects middleware before creating the client:

```php
use Cognesy\Http\Creation\HttpClientBuilder;

$client = (new HttpClientBuilder())
    ->withMiddleware(new AddHeaderMiddleware('X-Api-Version', '2'))
    ->withMiddleware(new TimingMiddleware())
    ->create();
// @doctest id="732c"
```

## Built-in Middleware

The package ships with several production-ready middleware components.

### RetryMiddleware

Automatically retries failed requests with exponential backoff and jitter:

```php
use Cognesy\Http\Extras\Middleware\RetryMiddleware;
use Cognesy\Http\Extras\Support\RetryPolicy;

$client = (new HttpClientBuilder())
    ->withRetryPolicy(new RetryPolicy(
        maxRetries: 3,
        baseDelayMs: 250,
        maxDelayMs: 8000,
        jitter: 'full',                    // none, full, or equal
        retryOnStatus: [408, 429, 500, 502, 503, 504],
        respectRetryAfter: true,
    ))
    ->create();
// @doctest id="cc03"
```

The retry middleware only operates on synchronous (non-streamed) requests. It respects the `Retry-After` header when present. The jitter options are:

- `none` -- exact exponential backoff
- `full` -- random delay between 0 and the calculated backoff
- `equal` -- half the backoff plus a random portion of the other half

### CircuitBreakerMiddleware

Prevents repeated calls to a failing service by tracking failures per host:

```php
use Cognesy\Http\Extras\Middleware\CircuitBreakerMiddleware;
use Cognesy\Http\Extras\Support\CircuitBreakerPolicy;

$client = (new HttpClientBuilder())
    ->withCircuitBreakerPolicy(new CircuitBreakerPolicy(
        failureThreshold: 5,
        openForSec: 30,
        halfOpenMaxRequests: 2,
        successThreshold: 2,
        failureStatusCodes: [429, 500, 502, 503, 504],
    ))
    ->create();
// @doctest id="b196"
```

The circuit breaker follows the standard state machine:

- **Closed** -- requests flow normally; failures are counted.
- **Open** -- after `failureThreshold` failures, the circuit opens and all requests throw `CircuitBreakerOpenException` for `openForSec` seconds.
- **Half-open** -- after the timeout, a limited number of probe requests are allowed. If `successThreshold` probes succeed, the circuit closes. If any fail, it reopens.

State is stored in APCu when available, with an in-memory fallback for environments without it.

### IdempotencyMiddleware

Attaches a unique idempotency key to requests, which prevents duplicate processing when retries occur:

```php
use Cognesy\Http\Extras\Middleware\IdempotencyMiddleware;

$client = (new HttpClientBuilder())
    ->withIdempotencyMiddleware(new IdempotencyMiddleware(
        headerName: 'Idempotency-Key',
        methods: ['POST'],
        hostAllowList: ['api.stripe.com'],
    ))
    ->create();
// @doctest id="ee44"
```

The middleware only attaches keys to the specified HTTP methods and hosts. If the request already has an idempotency key header, it is left unchanged.

### EventSourceMiddleware

Parses server-sent event streams into clean payloads. See [Streaming Responses](5-streaming-responses.md) for usage details.

### RecordReplayMiddleware

Records HTTP interactions to disk and replays them later, which is invaluable for testing and development:

```php
use Cognesy\Http\Extras\Middleware\RecordReplay\RecordReplayMiddleware;

// Record mode -- real requests are made and saved
$recorder = new RecordReplayMiddleware(
    mode: RecordReplayMiddleware::MODE_RECORD,
    storageDir: '/tmp/http_recordings',
);

// Replay mode -- saved responses are returned without network calls
$replayer = new RecordReplayMiddleware(
    mode: RecordReplayMiddleware::MODE_REPLAY,
    storageDir: '/tmp/http_recordings',
    fallbackToRealRequests: true,
);

$client = (new HttpClientBuilder())
    ->withMiddleware($replayer)
    ->create();
// @doctest id="b38a"
```

When `fallbackToRealRequests` is `true`, unrecorded requests are sent to the real server. When `false`, a `RecordingNotFoundException` is thrown.

Record/replay matching is intentionally narrow in 2.0.0: recordings are keyed by request method, full URL, and body. Request headers and request options are not part of the identity contract.

For streamed responses, recording mode buffers the full upstream stream before returning a replayable streamed response. That keeps replay deterministic, but it means recording mode is not a transparent progressive-streaming path.

## Response Decoration

For middleware that needs to transform streamed responses, use `BaseResponseDecorator` to wrap the stream with a transformation function:

```php
use Cognesy\Http\Extras\Support\BaseResponseDecorator;

$decorated = BaseResponseDecorator::decorate(
    $response,
    fn(string $chunk): string => strtoupper($chunk),
);
// @doctest id="5f31"
```

This creates a new `HttpResponse` with a `TransformStream` that applies your function to each chunk. The original response is not modified.

## Writing Custom Middleware

Here is a practical example of authentication middleware:

```php
use Cognesy\Http\Extras\Support\BaseMiddleware;
use Cognesy\Http\Data\HttpRequest;

final class BearerAuthMiddleware extends BaseMiddleware
{
    public function __construct(
        private string $token,
    ) {}

    protected function beforeRequest(HttpRequest $request): HttpRequest
    {
        return $request->withHeader('Authorization', 'Bearer ' . $this->token);
    }
}
// @doctest id="6f0b"
```

And a logging middleware that records request duration:

```php
use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\HttpMiddleware;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Psr\Log\LoggerInterface;

final class LoggingMiddleware implements HttpMiddleware
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    public function handle(HttpRequest $request, CanHandleHttpRequest $next): HttpResponse
    {
        $this->logger->info('HTTP request', [
            'method' => $request->method(),
            'url' => $request->url(),
        ]);

        $start = microtime(true);
        $response = $next->handle($request);
        $duration = microtime(true) - $start;

        $this->logger->info('HTTP response', [
            'status' => $response->statusCode(),
            'duration_ms' => round($duration * 1000, 2),
        ]);

        return $response;
    }
}
// @doctest id="ccc2"
```

## Middleware Order

The order you register middleware determines the execution flow. Middleware registered first is the outermost layer:

```php
$client = (new HttpClientBuilder())
    ->withMiddleware(new LoggingMiddleware($logger))      // 1st: logs everything
    ->withMiddleware(new RetryMiddleware($retryPolicy))   // 2nd: retries include auth
    ->withMiddleware(new BearerAuthMiddleware($token))    // 3rd: adds auth header
    ->create();
// @doctest id="b640"
```

In this setup:
- **Request flow:** Logging -> Retry -> Auth -> Driver
- **Response flow:** Driver -> Auth -> Retry -> Logging

The retry middleware wraps the auth middleware, so retried requests get fresh auth headers. The logging middleware sees all attempts, including retries.

## Middleware Stack API

The `MiddlewareStack` class provides fine-grained control over the middleware collection:

```php
$stack->append($middleware, 'name');     // Add to end
$stack->prepend($middleware, 'name');    // Add to beginning
$stack->remove('name');                 // Remove by name
$stack->replace('name', $newMiddleware); // Replace by name
$stack->has('name');                    // Check existence
$stack->get('name');                    // Get by name
$stack->clear();                       // Remove all
$stack->all();                         // Get all middleware
// @doctest id="dd39"
```

You can replace the entire stack on a client:

```php
$client = $client->withMiddlewareStack($newStack);
// @doctest id="be81"
```

## See Also

- [Streaming Responses](5-streaming-responses.md) -- EventSourceMiddleware for SSE parsing.
- [Custom Clients](9-1-custom-clients.md) -- create drivers that middleware wraps around.
