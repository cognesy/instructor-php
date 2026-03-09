---
title: Middleware
description: 'Add small transport behaviors around request execution.'
---

Middleware lets you change request and response behavior without changing drivers.

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
// @doctest id="8d67"
```

## Register Middleware

```php
$client = $client->withMiddleware(new AddHeaderMiddleware(), 'request-id');
$client = $client->withoutMiddleware('request-id');
// @doctest id="212d"
```

You can also register middleware while building the client:

```php
use Cognesy\Http\Creation\HttpClientBuilder;

$client = (new HttpClientBuilder())
    ->withMiddleware(new AddHeaderMiddleware())
    ->create();
// @doctest id="8623"
```

The builder also has focused helpers for the built-in middleware:

- `withRetryPolicy(...)`
- `withCircuitBreakerPolicy(...)`
- `withIdempotencyMiddleware(...)`

For streaming event payloads, add `EventSourceMiddleware` with `withMiddleware(...)`.

Keep middleware small and deterministic.
