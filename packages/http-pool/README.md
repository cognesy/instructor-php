# HTTP Pool

Concurrent HTTP request execution for Instructor.

`http-pool` is intentionally separate from `http-client`:

- `http-client` handles one request at a time
- `http-pool` handles many requests at once

## Quick Start

```php
use Cognesy\Http\Collections\HttpRequestList;
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\HttpPool\HttpPool;

$pool = HttpPool::fromConfig(new HttpClientConfig(driver: 'guzzle'));

$responses = $pool->pool(
    HttpRequestList::of(
        new HttpRequest('https://example.com/a', 'GET', [], '', []),
        new HttpRequest('https://example.com/b', 'GET', [], '', []),
    ),
    maxConcurrent: 2,
);
```

## Docs

- `packages/http-pool/docs/overview.md`
- `packages/http-pool/docs/quickstart.md`
- `packages/http-pool/docs/custom-handlers.md`
