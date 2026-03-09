---
title: Handling Responses
description: 'How to read pending and final responses.'
---

`CanSendHttpRequests::send()` returns `PendingHttpResponse`.

```php
$pending = $client->send($request);
```

Core methods:
- `get()`
- `statusCode()`
- `headers()`
- `content()`
- `stream()`

### Final response

```php
$response = $pending->get(); // default request mode is non-streamed

$status = $response->statusCode();
$headers = $response->headers();
$body = $response->body();
```

With `failOnError: true`, HTTP 4xx and 5xx throw typed exceptions.
