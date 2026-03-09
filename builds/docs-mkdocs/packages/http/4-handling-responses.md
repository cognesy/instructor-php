---
title: 'Handling Responses'
description: 'Read pending responses as buffered content or as a stream.'
---

`CanSendHttpRequests::send()` returns `PendingHttpResponse`.

```php
$pending = $client->send($request);
// @doctest id="32a3"
```

Core methods:
- `get()`
- `statusCode()`
- `headers()`
- `content()`
- `stream()`

## Buffered Responses

For a normal request, call `get()` and read the final `HttpResponse`:

```php
$response = $pending->get();

$status = $response->statusCode();
$headers = $response->headers();
$body = $response->body();
// @doctest id="69f0"
```

## Streaming Responses

For a streamed request, call `stream()` on the pending response:

```php
foreach ($pending->stream() as $chunk) {
    echo $chunk;
}
// @doctest id="83fa"
```

Use `content()` and `body()` for buffered responses. Use `stream()` for streamed responses.

With `failOnError: true`, HTTP 4xx and 5xx throw typed exceptions.
