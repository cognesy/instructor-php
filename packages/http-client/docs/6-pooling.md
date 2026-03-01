---
title: Request Pooling
description: 'Concurrent request execution with typed request/response collections.'
---

## Build Request List

```php
use Cognesy\Http\Collections\HttpRequestList;
use Cognesy\Http\Data\HttpRequest;

$requests = HttpRequestList::of(
    new HttpRequest('https://api.example.com/a', 'GET', [], '', []),
    new HttpRequest('https://api.example.com/b', 'GET', [], '', []),
    new HttpRequest('https://api.example.com/c', 'GET', [], '', []),
);
```

## Execute Immediately

```php
$results = $client->pool($requests, maxConcurrent: 2);

echo $results->successCount();
echo $results->failureCount();
```

Each entry is a `Result` (`Success` or `Failure`).

## Deferred Pool

```php
$pendingPool = $client->withPool($requests);
$results = $pendingPool->all(maxConcurrent: 3);
```

## Failure Handling

```php
foreach ($results as $result) {
    if ($result->isSuccess()) {
        $response = $result->unwrap();
        // handle response
        continue;
    }

    $error = $result->error();
    // handle per-request failure
}
```

## Driver Support

Pooling is supported by built-in HTTP drivers. If you inject an external custom driver without pool support, pooling fails explicitly.
