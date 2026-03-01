---
title: Processing with Middleware (Extras)
description: Advanced middleware processing patterns for request/response transformations.
---

## Middleware Contract

```php
use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\HttpMiddleware;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;

final class AddRequestIdMiddleware implements HttpMiddleware
{
    public function handle(HttpRequest $request, CanHandleHttpRequest $next): HttpResponse {
        return $next->handle($request->withHeader('X-Request-Id', uniqid('req_', true)));
    }
}
```

## Base Middleware Hooks

```php
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Middleware\Base\BaseMiddleware;

final class AuthMiddleware extends BaseMiddleware
{
    protected function beforeRequest(HttpRequest $request): HttpRequest {
        return $request->withHeader('Authorization', 'Bearer ' . getenv('API_TOKEN'));
    }

    protected function afterRequest(HttpRequest $request, HttpResponse $response): HttpResponse {
        return $response;
    }
}
```

## Decorate Streaming Responses

```php
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Middleware\Base\BaseMiddleware;
use Cognesy\Http\Middleware\Base\BaseResponseDecorator;

final class TrimChunkMiddleware extends BaseMiddleware
{
    protected function shouldDecorateResponse(HttpRequest $request, HttpResponse $response): bool {
        return $response->isStreamed();
    }

    protected function toResponse(HttpRequest $request, HttpResponse $response): HttpResponse {
        return BaseResponseDecorator::decorate($response, static fn(string $chunk): string => trim($chunk));
    }
}
```

## Process SSE Payloads

```php
use Cognesy\Http\Middleware\EventSource\EventSourceMiddleware;

$client = $client->withMiddleware(
    (new EventSourceMiddleware())
        ->withParser(static fn(string $payload): string|bool => $payload)
);
```

The parser runs on assembled SSE `data:` payloads.

## Register Middleware

```php
$client = $client->withMiddleware(new AddRequestIdMiddleware(), 'request-id');
$client = $client->withoutMiddleware('request-id');
```

Both methods are immutable and return a new `HttpClient` instance.

## See Also

- [Middleware](10-middleware.md)
- [Handling responses](4-handling-responses.md)
- [Streaming responses](5-streaming-responses.md)
- [Reliability middleware (extras)](12-reliability-middleware.md)
