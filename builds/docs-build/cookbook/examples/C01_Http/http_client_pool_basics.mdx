---
title: 'HTTP Client – Pool Basics'
docname: 'http_client_pool_basics'
id: 'bb83'
---
## Overview

Deterministic pool example using an in-memory pool handler. In production, call
`$client->pool($requests, $maxConcurrent)` with a built-in HTTP driver.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Http\Collections\HttpRequestList;
use Cognesy\Http\Collections\HttpResponseList;
use Cognesy\Http\Contracts\CanHandleRequestPool;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\PendingHttpPool;
use Cognesy\Utils\Result\Result;

final class InMemoryPool implements CanHandleRequestPool
{
    public function pool(HttpRequestList $requests, ?int $maxConcurrent = null): HttpResponseList {
        $results = [];

        foreach ($requests as $request) {
            $results[] = Result::success(HttpResponse::sync(
                statusCode: 200,
                headers: ['Content-Type' => 'application/json'],
                body: json_encode(['url' => $request->url(), 'ok' => true]),
            ));
        }

        return HttpResponseList::fromArray($results);
    }
}

$requests = HttpRequestList::of(
    new HttpRequest('https://api.example.local/a', 'GET', [], '', []),
    new HttpRequest('https://api.example.local/b', 'GET', [], '', []),
    new HttpRequest('https://api.example.local/c', 'GET', [], '', []),
);

$pool = new PendingHttpPool($requests, new InMemoryPool());
$results = $pool->all(maxConcurrent: 2);

echo "success={$results->successCount()} failures={$results->failureCount()}\n";

foreach ($results->successful() as $response) {
    echo $response->body() . "\n";
}
?>
```
