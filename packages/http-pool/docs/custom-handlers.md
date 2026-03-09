---
title: Custom Handlers
description: Plug in your own pool implementation.
---

# Custom Handlers

If you already have a pool implementation, inject it directly.

```php
use Cognesy\Http\Collections\HttpRequestList;
use Cognesy\Http\Collections\HttpResponseList;
use Cognesy\HttpPool\Contracts\CanHandleRequestPool;
use Cognesy\HttpPool\Creation\HttpPoolBuilder;

final class MyPool implements CanHandleRequestPool
{
    public function pool(HttpRequestList $requests, ?int $maxConcurrent = null): HttpResponseList {
        // execute requests here
    }
}

$pool = (new HttpPoolBuilder())
    ->withPoolHandler(new MyPool())
    ->create();
```

If you want a named reusable driver, register it in `HttpPoolRegistry`.
