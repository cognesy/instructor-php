# HTTP Request Pooling

Focused guide for concurrent HTTP execution.

## When to Use Pooling

Use pooling when you need to call multiple endpoints in parallel and aggregate results (e.g. model fan-out, batch fetches).

## Quick Start

```php
use Cognesy\Http\Collections\HttpRequestList;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\HttpClient;

$client = HttpClient::default();

$requests = HttpRequestList::of(
    new HttpRequest('https://api.example.com/a', 'GET', [], '', []),
    new HttpRequest('https://api.example.com/b', 'GET', [], '', []),
    new HttpRequest('https://api.example.com/c', 'GET', [], '', []),
);

$results = $client->pool($requests, maxConcurrent: 2);
```

## Reading Results

`pool()` returns `HttpResponseList`, containing `Result` objects.

```php
echo "Success: {$results->successCount()}\n";
echo "Failure: {$results->failureCount()}\n";

foreach ($results as $result) {
    if ($result->isSuccess()) {
        $response = $result->unwrap();
        echo $response->statusCode() . "\n";
        continue;
    }

    $error = $result->error();
    echo (string)$error . "\n";
}
```

## Deferred Execution

```php
$pendingPool = $client->withPool($requests);
$results = $pendingPool->all(maxConcurrent: 3);
```

## Driver Notes

- Built-in HTTP drivers support pooling.
- If you pass an external driver without pool support, pooling throws explicitly.

## Practical Defaults

- Start with `maxConcurrent: 2` to `5`.
- Increase only when endpoint limits and latency profile justify it.
- Keep payloads small for high-concurrency fan-out paths.
