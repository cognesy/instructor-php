---
title: HTTP Client Layer
description: 'Understanding the HTTP client layer in Polyglot'
---

At the lowest level, Polyglot uses an HTTP client layer to communicate with provider APIs. This layer includes:

1. A unified `HttpClient` interface
2. Implementations for different HTTP libraries (Guzzle, Symfony, Laravel)
3. A middleware system for extending functionality


## HttpClient

The `HttpClient` class provides a unified interface for HTTP requests:

```php
namespace Cognesy\Http;

class HttpClient implements CanHandleHttpRequest {
    public function __construct(
        string $client = '',
        ?HttpClientConfig $config = null,
        ?EventDispatcher $events = null
    ) { ... }

    public static function make(
        string $client = '',
        ?HttpClientConfig $config = null,
        ?EventDispatcher $events = null
    ): self { ... }

    public function withClient(string $client): self { ... }
    public function withConfig(HttpClientConfig $config): self { ... }
    public function withMiddleware(...$middleware): self { ... }
    public function withDebug(bool $debug = true): self { ... }

    public function handle(HttpClientRequest $request): HttpClientResponse { ... }
    public function middleware(): MiddlewareStack { ... }
}
```



## HttpClientRequest and HttpClientResponse

These classes represent HTTP requests and responses:

```php
namespace Cognesy\Http\Data;

class HttpClientRequest {
    public function __construct(
        private string $url,
        private string $method,
        private array $headers,
        private mixed $body,
        private array $options
    ) { ... }

    public function url(): string { ... }
    public function method(): string { ... }
    public function headers(): array { ... }
    public function body(): HttpRequestBody { ... }
    public function options(): array { ... }
    public function isStreamed(): bool { ... }

    public function withStreaming(bool $streaming): self { ... }
}

interface HttpClientResponse {
    public function statusCode(): int;
    public function headers(): array;
    public function body(): string;
    public function stream(int $chunkSize = 1): Generator;
    public function original(): mixed;
}
```



## Middleware System

The HTTP client layer includes a middleware system that allows extending functionality:

```php
namespace Cognesy\Http;

interface HttpMiddleware {
    public function handle(
        HttpClientRequest $request,
        CanHandleHttpRequest $next
    ): HttpClientResponse;
}

abstract class BaseMiddleware implements HttpMiddleware {
    public function handle(
        HttpClientRequest $request,
        CanHandleHttpRequest $next
    ): HttpClientResponse { ... }

    protected function beforeRequest(HttpClientRequest $request): void {}

    protected function afterRequest(
        HttpClientRequest $request,
        HttpClientResponse $response
    ): HttpClientResponse {
        return $response;
    }

    protected function shouldDecorateResponse(
        HttpClientRequest $request,
        HttpClientResponse $response
    ): bool {
        return false;
    }

    protected function toResponse(
        HttpClientRequest $request,
        HttpClientResponse $response
    ): HttpClientResponse {
        return $response;
    }
}

class MiddlewareStack {
    public function append(HttpMiddleware $middleware, string $name = ''): self { ... }
    public function prepend(HttpMiddleware $middleware, string $name = ''): self { ... }
    public function remove(string $name): self { ... }
    public function replace(string $name, HttpMiddleware $middleware): self { ... }
    public function clear(): self { ... }
    public function has(string $name): bool { ... }
    public function get(string|int $nameOrIndex): ?HttpMiddleware { ... }
    public function all(): array { ... }
    public function process(
        HttpClientRequest $request,
        CanHandleHttpRequest $handler
    ): HttpClientResponse { ... }
}
```
