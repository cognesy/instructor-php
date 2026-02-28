---
title: Middleware
description: 'Core middleware contract and practical usage patterns.'
---

## Middleware Contract

```php
use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\HttpMiddleware;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;

final class AddHeaderMiddleware implements HttpMiddleware
{
    public function handle(HttpRequest $request, CanHandleHttpRequest $next): HttpResponse {
        $request = $request->withHeader('X-Request-ID', 'req-123');
        return $next->handle($request);
    }
}
```

## Register Middleware (Immutable)

```php
$client = $client->withMiddleware(new AddHeaderMiddleware(), 'request-id');
$client = $client->withoutMiddleware('request-id');
```

## Typical Middleware Use Cases

- Request decoration (auth headers, tracing IDs)
- Response normalization (small shape adaptations)
- Retry/circuit-breaker/idempotency policies
- Stream chunk transformation for SSE/chunked flows

## Built-In Reliability Middleware

```php
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Middleware\RetryPolicy;

$client = (new HttpClientBuilder())
    ->withRetryPolicy(new RetryPolicy(maxRetries: 3))
    ->create();
```

Use the event bus for debug/observability rather than embedding verbose logging directly in middleware logic.

## Keep Middleware Practical

- Keep logic deterministic and focused
- Avoid hidden I/O side effects in transformation middleware
- Prefer one responsibility per middleware
