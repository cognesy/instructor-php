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
- Lightweight validation or policy checks

Use the event bus for debug/observability rather than embedding verbose logging directly in middleware logic.

## Keep Middleware Practical

- Keep logic deterministic and focused
- Avoid hidden I/O side effects in transformation middleware
- Prefer one responsibility per middleware

## See Also

- [Processing with middleware](11-processing-with-middleware.md)
- [Reliability middleware (extras)](12-reliability-middleware.md)
- [Record and replay (extras)](13-record-replay.md)
