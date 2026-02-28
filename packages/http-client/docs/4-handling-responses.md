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

- `$pending->get()` returns `HttpResponse`
- `$pending->statusCode()` reads status quickly
- `$pending->headers()` reads response headers
- `$pending->content()` reads sync body text
- `$pending->stream()` yields streamed chunks

## Sync vs Stream Contract

`PendingHttpResponse` keeps sync and stream executions separate.

- Calling `content()`/`get()` uses sync execution
- Calling `stream()` uses streamed execution
- If you use both paths, they are executed independently and cached per mode

This avoids hidden mode collisions.

## Working with HttpResponse

```php
$response = $pending->get();

$status = $response->statusCode();
$headers = $response->headers();
$body = $response->body();
```

For streamed responses, consume chunks with `stream()`.

## Exceptions

With `failOnError: true`, expect typed exceptions (e.g. timeout, connection, HTTP status exceptions). Let them bubble unless you have explicit recovery logic.
