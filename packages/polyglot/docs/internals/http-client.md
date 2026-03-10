---
title: HTTP Client
description: Polyglot depends on the shared Instructor HTTP transport.
---

Polyglot does not implement its own HTTP transport. It builds on the shared HTTP package, keeping transport concerns separate from request and response normalization. This separation means you can swap HTTP implementations, add middleware, or inject test doubles without touching the driver layer.


## The Transport Contract

All HTTP communication flows through a single contract:

```php
interface CanSendHttpRequests
{
    public function handle(HttpRequest $request): HttpResponse;
}
```

Every inference and embeddings driver receives a `CanSendHttpRequests` implementation. The driver translates its `InferenceRequest` into an `HttpRequest`, hands it to the client, and translates the `HttpResponse` back.


## Default Client

When you call `InferenceRuntime::fromConfig(...)` or `EmbeddingsRuntime::fromConfig(...)` without providing an HTTP client, Polyglot creates a default one using `HttpClientBuilder`:

```php
$httpClient = (new HttpClientBuilder(events: $events))->create();
```

The builder selects an appropriate underlying HTTP library (Guzzle, Symfony HttpClient, or Laravel's HTTP client) based on what is available in your project.

You can inject your own client when you need specific configuration:

```php
use Cognesy\Http\Creation\HttpClientBuilder;

$httpClient = (new HttpClientBuilder())
    ->withMiddleware(new MyLoggingMiddleware())
    ->create();

$runtime = InferenceRuntime::fromConfig($config, httpClient: $httpClient);
```


## HttpRequest and HttpResponse

These data objects represent the HTTP layer's request and response. Drivers create `HttpRequest` objects through their request adapters and read `HttpResponse` objects through their response adapters.

### HttpRequest

```php
$request = new HttpRequest(
    url: 'https://api.openai.com/v1/chat/completions',
    method: 'POST',
    headers: ['Authorization' => 'Bearer ...', 'Content-Type' => 'application/json'],
    body: ['model' => 'gpt-4.1-nano', 'messages' => [...]],
    options: ['stream' => true],
);

$request->url();        // string
$request->method();     // string
$request->headers();    // array
$request->body();       // HttpRequestBody
$request->options();    // array
$request->isStreamed(); // bool

// Create a copy with streaming toggled
$streamRequest = $request->withStreaming(true);
```

### HttpResponse

The `HttpResponse` interface provides access to the response data:

```php
$response->statusCode(); // int
$response->headers();    // array
$response->body();       // string -- the full response body
$response->stream();     // Generator -- for streaming responses
$response->original();   // mixed -- the underlying library's native response
```

For streaming, the `stream()` method returns a `Generator` that yields chunks as they arrive from the provider. The driver's response adapter parses these chunks into SSE events and then into `PartialInferenceDelta` objects.


## Middleware

The HTTP client supports a middleware stack for cross-cutting concerns like logging, retries, caching, and authentication. Middleware implements the `HttpMiddleware` interface:

```php
interface HttpMiddleware
{
    public function handle(
        HttpRequest $request,
        CanHandleHttpRequest $next,
    ): HttpResponse;
}
```

The `BaseMiddleware` abstract class provides convenient hooks so you do not need to manage the chain manually:

```php
abstract class BaseMiddleware implements HttpMiddleware
{
    // Called before the request is sent
    protected function beforeRequest(HttpRequest $request): void {}

    // Called after the response is received
    protected function afterRequest(
        HttpRequest $request,
        HttpResponse $response,
    ): HttpResponse {
        return $response;
    }

    // Determines whether to wrap the response
    protected function shouldDecorateResponse(
        HttpRequest $request,
        HttpResponse $response,
    ): bool {
        return false;
    }

    // Wraps the response if shouldDecorateResponse returns true
    protected function toResponse(
        HttpRequest $request,
        HttpResponse $response,
    ): HttpResponse {
        return $response;
    }
}
```

### Managing the Middleware Stack

The `MiddlewareStack` supports named middleware for easy manipulation:

```php
$client->middleware()->append($middleware, name: 'logging');
$client->middleware()->prepend($middleware, name: 'auth');
$client->middleware()->replace('logging', $newMiddleware);
$client->middleware()->remove('logging');
$client->middleware()->has('logging'); // bool
$client->middleware()->get('logging'); // ?HttpMiddleware
$client->middleware()->all();          // array
$client->middleware()->clear();        // self
```

Middleware runs in stack order: middleware added with `prepend()` runs before middleware added with `append()`. The `name` parameter is optional but recommended -- it allows you to replace or remove middleware later without tracking references.


## Stream Cache Manager

For advanced use cases, Polyglot supports stream caching through the `CanManageStreamCache` contract. When provided, the stream cache manager can record and replay streaming responses, which is useful for testing and development:

```php
$runtime = InferenceRuntime::fromConfig(
    $config,
    streamCacheManager: $cacheManager,
);
```

The cache behavior is controlled per-request through the `ResponseCachePolicy` enum on the `InferenceRequest`. You can set this through the facade:

```php
use Cognesy\Polyglot\Inference\Enums\ResponseCachePolicy;

$inference->withResponseCachePolicy(ResponseCachePolicy::ReadOrWrite);
```


## Shared Event Dispatcher

The HTTP client shares the same event dispatcher as the runtime that created it. This means HTTP-level events (connection errors, timeouts, etc.) flow through the same event system as inference events, providing a unified observability pipeline.
