---
title: Streaming Responses
description: 'Short streaming examples.'
---

Enable streaming on the request:

```php
$request = (new HttpRequest(
    url: 'https://api.example.com/stream',
    method: 'GET',
    headers: ['Accept' => 'text/event-stream'],
    body: '',
    options: [],
))->withStreaming(true);
```

Consume chunks:

```php
foreach ($client->send($request)->stream() as $chunk) {
    echo $chunk;
}
```

Streaming is one-pass.
