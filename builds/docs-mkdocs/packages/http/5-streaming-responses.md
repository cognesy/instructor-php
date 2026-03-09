---
title: 'Streaming Responses'
description: 'Turn on streaming for a request and consume chunks as they arrive.'
---

Set the request to streaming mode:

```php
$request = (new HttpRequest(
    url: 'https://api.example.com/stream',
    method: 'GET',
    headers: ['Accept' => 'text/event-stream'],
    body: '',
    options: [],
))->withStreaming(true);
// @doctest id="4a18"
```

Then consume the response as chunks:

```php
foreach ($client->send($request)->stream() as $chunk) {
    echo $chunk;
}
// @doctest id="aa65"
```

If you need to parse server-sent events, add `EventSourceMiddleware` before sending the request.
