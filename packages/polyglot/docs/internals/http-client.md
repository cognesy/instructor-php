---
title: HTTP Client Layer
description: 'Understanding the HTTP client layer in Polyglot'
---

Polyglot talks to providers through `packages/http-client`.

What matters at the Polyglot boundary:

1. One transport contract: `Cognesy\Http\Contracts\CanSendHttpRequests`
2. One request type: `Cognesy\Http\Data\HttpRequest`
3. One response type: `Cognesy\Http\Data\HttpResponse`

## Runtime Boundary

Polyglot depends on the public HTTP transport contract, not on a specific driver:

```php
use Cognesy\Http\Contracts\CanSendHttpRequests;
use Cognesy\Http\Data\HttpRequest;

final class ExampleRuntime
{
    public function __construct(
        private readonly CanSendHttpRequests $http,
    ) {}

    public function request(HttpRequest $request): string
    {
        return $this->http->send($request)->get()->body();
    }
}
```

## Streaming

Streaming still returns `HttpResponse`. The response decides whether it is buffered or streamed:

```php
$response = $http->send($request)->get();

if ($response->isStreamed()) {
    foreach ($response->stream() as $chunk) {
        // consume chunks
    }
}
```

## Drivers

Bundled HTTP drivers are resolved by `packages/http-client`.
Laravel-specific HTTP transport lives in `packages/laravel`, not in the baseline HTTP package.
