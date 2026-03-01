---
title: Streaming Responses
description: 'Practical patterns for chunked and SSE-like response processing.'
---

## Enable Streaming on Request

```php
$request = (new HttpRequest(
    url: 'https://api.example.com/stream',
    method: 'GET',
    headers: ['Accept' => 'text/event-stream'],
    body: '',
    options: [],
))->withStreaming(true);
```

## Consume Chunks

```php
foreach ($client->withRequest($request)->stream() as $chunk) {
    // parse or forward chunk
    echo $chunk;
}
```

## Middleware + Streaming

You can transform stream chunks in middleware before downstream consumers receive them.

```php
$client = $client->withMiddleware($myStreamMiddleware);
```

Keep stream middleware focused and cheap; avoid heavy buffering unless required.

## SSE Parsing with EventSourceMiddleware

```php
use Cognesy\Http\Middleware\EventSource\EventSourceMiddleware;

$client = $client->withMiddleware(
    (new EventSourceMiddleware())
        ->withParser(static fn(string $payload): string|bool => $payload)
);
```

`withParser()` receives assembled SSE `data:` payloads.

## Operational Notes

- Streaming is one-pass by nature at the transport layer.
- If you need replay/caching, do it explicitly with dedicated stream cache components.
- For mixed usage (`stream()` and `content()`), treat them as separate execution paths.
- `HttpClient::withSSEStream()` is deprecated; use `EventSourceMiddleware` instead.
