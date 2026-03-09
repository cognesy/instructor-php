---
title: 'Streaming Responses'
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
// @doctest id="b5f7"
```

Consume chunks:

```php
foreach ($client->send($request)->stream() as $chunk) {
    echo $chunk;
}
// @doctest id="c21d"
```

Streaming is one-pass.
