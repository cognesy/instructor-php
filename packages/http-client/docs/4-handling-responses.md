---
title: Handling Responses
description: 'How to work with pending responses, metadata access, and response modes.'
---

## Pending Response API

`withRequest()` returns `PendingHttpResponse`.

```php
$pending = $client->withRequest($request);
```

Available access patterns:

- `$pending->get()` returns `HttpResponse` for the request mode
- `$pending->statusCode()` reads status quickly
- `$pending->headers()` reads response headers
- `$pending->content()` reads body text (non-streamed mode)
- `$pending->stream()` yields streamed chunks

## Sync vs Stream Contract

`PendingHttpResponse` keeps sync and stream executions separate.

- `get()` follows request mode (`withStreaming(false|true)`)
- `content()` uses non-streamed execution
- `stream()` uses streamed execution
- If you use both paths, they execute independently and cache per mode

This avoids hidden mode collisions.

## Working with HttpResponse

```php
$response = $pending->get(); // default request mode is non-streamed

$status = $response->statusCode();
$headers = $response->headers();
$body = $response->body();
```

For streamed responses, consume chunks with `stream()`.

## Exceptions

With `failOnError: true`, expect typed exceptions (e.g. timeout, connection, HTTP status exceptions). Let them bubble unless you have explicit recovery logic.
