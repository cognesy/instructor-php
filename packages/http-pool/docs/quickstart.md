---
title: Quick Start
description: The smallest useful pool example.
---

# Quick Start

```php
use Cognesy\Http\Collections\HttpRequestList;
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\HttpPool\HttpPool;

$pool = HttpPool::fromConfig(new HttpClientConfig(driver: 'guzzle'));

$requests = HttpRequestList::of(
    new HttpRequest('https://example.com/a', 'GET', [], '', []),
    new HttpRequest('https://example.com/b', 'GET', [], '', []),
);

$responses = $pool->pool($requests, maxConcurrent: 2);
```

## Deferred Execution

```php
$pending = $pool->withRequests($requests);
$responses = $pending->all(maxConcurrent: 2);
```

## Result Shape

The returned collection contains `Result` values.

- success: `$result->unwrap()`
- failure: `$result->error()`
