# HTTP Request Pool

The HTTP Client provides concurrent request pooling capabilities for executing multiple HTTP requests in parallel using typed, immutable collections. This is particularly useful for LLM API calls, mixture-of-experts patterns, and any scenario requiring multiple simultaneous HTTP requests.

## Features

- **Typed Collections**: Type-safe `HttpRequestList` and `HttpResponseList` for compile-time safety
- **Concurrent Execution**: Execute multiple requests simultaneously with configurable concurrency limits
- **Driver Agnostic**: Works with all supported HTTP client drivers (Guzzle, Symfony, Laravel, Curl, CurlNew)
- **Deferred Execution**: Create pools that can be executed later with different parameters
- **Result Monad**: Graceful handling of failures with `Result<HttpResponse>` objects
- **Functional API**: Filter, map, and analyze results with collection methods
- **Immutability**: All collections return new instances for safe concurrent usage

## Quick Start

```php
use Cognesy\Http\HttpClient;
use Cognesy\Http\Collections\HttpRequestList;
use Cognesy\Http\Data\HttpRequest;

$client = HttpClient::default();

$requests = HttpRequestList::of(
    new HttpRequest('https://api.openai.com/v1/chat/completions', 'POST', [], $prompt1, []),
    new HttpRequest('https://api.anthropic.com/v1/messages', 'POST', [], $prompt2, []),
    new HttpRequest('https://api.cohere.com/v1/generate', 'POST', [], $prompt3, []),
);

// Execute pool - returns HttpResponseList
$results = $client->pool($requests, maxConcurrent: 3);

// Access successful responses
foreach ($results->successful() as $response) {
    echo $response->body();
}

// Check for failures
if ($results->hasFailures()) {
    $errors = $results->failed();
    foreach ($errors as $error) {
        echo "Error: " . $error->getMessage();
    }
}
```

## Core Concepts

### HttpRequestList

Type-safe immutable collection for HTTP requests.

```php
use Cognesy\Http\Collections\HttpRequestList;
use Cognesy\Http\Data\HttpRequest;

// Create from variadic arguments
$requests = HttpRequestList::of(
    new HttpRequest('https://api.example.com/users/1', 'GET'),
    new HttpRequest('https://api.example.com/users/2', 'GET'),
    new HttpRequest('https://api.example.com/users/3', 'GET'),
);

// Create from array
$requestArray = [
    new HttpRequest('https://api.example.com/data', 'GET'),
    new HttpRequest('https://api.example.com/more', 'GET'),
];
$requests = HttpRequestList::fromArray($requestArray);

// Create empty collection
$requests = HttpRequestList::empty();

// Query methods
$count = $requests->count();
$isEmpty = $requests->isEmpty();
$first = $requests->first();
$last = $requests->last();
$all = $requests->all(); // Get underlying array

// Add requests (returns new instance)
$newRequests = $requests->withAppended(new HttpRequest('https://example.com', 'GET'));

// Filter requests
$filtered = $requests->filter(fn($req) => $req->method() === 'POST');
```

### HttpResponseList

Type-safe immutable collection for pool results. Each element is a `Result<HttpResponse>` monad (Success or Failure).

```php
use Cognesy\Http\Collections\HttpResponseList;

// Pool returns HttpResponseList
$results = $client->pool($requests);

// Access all Result objects
$allResults = $results->all(); // Array of Result objects

// Get only successful HttpResponse objects
$successful = $results->successful(); // Array of HttpResponse objects

// Get only errors from failed requests
$errors = $results->failed(); // Array of exceptions

// Query methods
$hasFailures = $results->hasFailures();
$hasSuccesses = $results->hasSuccesses();
$successCount = $results->successCount();
$failureCount = $results->failureCount();
$total = $results->count();

// Functional operations
$mapped = $results->map(function($result) {
    if ($result->isSuccess()) {
        return json_decode($result->unwrap()->body(), true);
    }
    return null;
});

$filtered = $results->filter(fn($result) => $result->isSuccess());

// Iterate over Result objects
foreach ($results as $result) {
    if ($result->isSuccess()) {
        $response = $result->unwrap();
        echo $response->body();
    } else {
        $error = $result->error();
        echo $error->getMessage();
    }
}
```

## Usage Patterns

### Immediate Execution

Execute a pool of requests immediately and get results:

```php
use Cognesy\Http\HttpClient;
use Cognesy\Http\Collections\HttpRequestList;
use Cognesy\Http\Data\HttpRequest;

$client = HttpClient::default();

$requests = HttpRequestList::of(
    new HttpRequest('https://api.openai.com/v1/chat/completions', 'POST', [], $prompt1, []),
    new HttpRequest('https://api.anthropic.com/v1/messages', 'POST', [], $prompt2, []),
    new HttpRequest('https://api.cohere.com/v1/generate', 'POST', [], $prompt3, []),
);

// Returns HttpResponseList
$results = $client->pool($requests, maxConcurrent: 3);

// Process results
echo "Completed: {$results->successCount()} successful, {$results->failureCount()} failed\n";
```

### Deferred Execution

Create a pool that can be executed later with different parameters:

```php
use Cognesy\Http\PendingHttpPool;

// Create pending pool (does not execute)
$pool = $client->withPool($requests);

// Execute when ready with specific concurrency
$results = $pool->all(maxConcurrent: 2);

// Can be executed multiple times with different settings
$serialResults = $pool->all(maxConcurrent: 1);
$parallelResults = $pool->all(maxConcurrent: 10);
```

### Building Request Collections Dynamically

```php
use Cognesy\Http\Collections\HttpRequestList;

// Start with empty collection
$requests = HttpRequestList::empty();

// Build collection
foreach ($userIds as $id) {
    $request = new HttpRequest(
        url: "https://api.example.com/users/{$id}",
        method: 'GET',
        headers: ['Accept' => 'application/json'],
        body: [],
        options: []
    );
    $requests = $requests->withAppended($request);
}

// Execute
$results = $client->pool($requests, maxConcurrent: 5);
```

## Use Cases

### Multiple LLM APIs

Query multiple LLM providers in parallel:

```php
use Cognesy\Http\Collections\HttpRequestList;

$prompt = ['model' => 'gpt-4', 'messages' => [['role' => 'user', 'content' => 'Explain AI']]];

$requests = HttpRequestList::of(
    new HttpRequest(
        'https://api.openai.com/v1/chat/completions',
        'POST',
        ['Authorization' => 'Bearer ' . $openaiKey],
        json_encode($prompt),
        []
    ),
    new HttpRequest(
        'https://api.anthropic.com/v1/messages',
        'POST',
        ['x-api-key' => $anthropicKey],
        json_encode($prompt),
        []
    ),
    new HttpRequest(
        'https://api.cohere.com/v1/generate',
        'POST',
        ['Authorization' => 'Bearer ' . $cohereKey],
        json_encode($prompt),
        []
    ),
);

$results = $client->pool($requests);

// Get fastest response
$successful = $results->successful();
if (!empty($successful)) {
    $fastestResponse = $successful[0];
    echo $fastestResponse->body();
}
```

### Mixture of Experts

Send the same query to multiple models for comparison:

```php
$experts = [
    ['endpoint' => 'https://api.openai.com/v1/chat/completions', 'model' => 'gpt-4'],
    ['endpoint' => 'https://api.openai.com/v1/chat/completions', 'model' => 'gpt-3.5-turbo'],
    ['endpoint' => 'https://api.anthropic.com/v1/messages', 'model' => 'claude-3-opus'],
    ['endpoint' => 'https://api.anthropic.com/v1/messages', 'model' => 'claude-3-sonnet'],
];

$requestArray = array_map(function($expert) use ($prompt, $headers) {
    $payload = array_merge($prompt, ['model' => $expert['model']]);
    return new HttpRequest(
        $expert['endpoint'],
        'POST',
        $headers,
        json_encode($payload),
        []
    );
}, $experts);

$requests = HttpRequestList::fromArray($requestArray);
$results = $client->pool($requests, maxConcurrent: 4);

// Compare responses
foreach ($results as $index => $result) {
    if ($result->isSuccess()) {
        $response = $result->unwrap();
        echo "Expert {$experts[$index]['model']}: " . $response->body() . "\n";
    }
}
```

### Batch Processing

Process multiple API calls efficiently:

```php
$userIds = range(1, 100);

$requestArray = array_map(function($id) {
    return new HttpRequest(
        "https://api.example.com/users/{$id}",
        'GET',
        ['Accept' => 'application/json'],
        [],
        ['timeout' => 10]
    );
}, $userIds);

$requests = HttpRequestList::fromArray($requestArray);
$results = $client->pool($requests, maxConcurrent: 10);

// Process results
$userData = [];
foreach ($results as $index => $result) {
    if ($result->isSuccess()) {
        $response = $result->unwrap();
        $userData[] = json_decode($response->body(), true);
    } else {
        error_log("Failed to fetch user {$userIds[$index]}: " . $result->error()->getMessage());
    }
}
```

### Fan-out / Fan-in Pattern

Make multiple parallel requests and aggregate results:

```php
// Fan-out: Send requests to multiple services
$services = [
    'https://api.service1.com/data',
    'https://api.service2.com/data',
    'https://api.service3.com/data',
];

$requests = HttpRequestList::fromArray(
    array_map(fn($url) => new HttpRequest($url, 'GET'), $services)
);

$results = $client->pool($requests, maxConcurrent: 3);

// Fan-in: Aggregate successful responses
$aggregated = [
    'timestamp' => time(),
    'sources' => [],
    'data' => [],
];

foreach ($results->successful() as $response) {
    $data = json_decode($response->body(), true);
    $aggregated['sources'][] = $response->headers()['X-Service-Name'] ?? 'unknown';
    $aggregated['data'][] = $data;
}

echo json_encode($aggregated);
```

## Result Handling

### Checking Success/Failure

```php
$results = $client->pool($requests);

// Quick checks
if ($results->hasFailures()) {
    echo "Some requests failed: {$results->failureCount()} failures\n";
}

if ($results->hasSuccesses()) {
    echo "Got {$results->successCount()} successful responses\n";
}

// All or nothing
if ($results->failureCount() === 0) {
    echo "All requests succeeded!\n";
    $responses = $results->successful();
    // Process all responses
}
```

### Processing Results

```php
$results = $client->pool($requests);

// Process successful responses only
foreach ($results->successful() as $response) {
    $data = json_decode($response->body(), true);
    processData($data);
}

// Handle errors
if ($results->hasFailures()) {
    $errors = $results->failed();
    foreach ($errors as $error) {
        if ($error instanceof TimeoutException) {
            // Retry timeout errors
            retryRequest($error->getRequest());
        } else {
            // Log other errors
            error_log($error->getMessage());
        }
    }
}
```

### Mapping and Filtering Results

```php
$results = $client->pool($requests);

// Extract JSON data from successful responses
$jsonData = $results
    ->filter(fn($result) => $result->isSuccess())
    ->map(function($result) {
        $response = $result->unwrap();
        return json_decode($response->body(), true);
    });

// Get only 200 OK responses
$okResponses = $results
    ->filter(function($result) {
        return $result->isSuccess()
            && $result->unwrap()->statusCode() === 200;
    });
```

### Accessing Individual Results

```php
$results = $client->pool($requests);
$allResults = $results->all(); // Get underlying array of Result objects

foreach ($allResults as $i => $result) {
    echo "Request {$i}: ";

    if ($result->isSuccess()) {
        $response = $result->unwrap();
        echo "Success - Status: {$response->statusCode()}\n";
    } else {
        $error = $result->error();
        echo "Failed - Error: {$error->getMessage()}\n";

        // Access request that failed
        if ($error instanceof HttpRequestException) {
            $failedRequest = $error->getRequest();
            echo "Failed URL: {$failedRequest->url()}\n";
        }
    }
}
```

## Driver Support

Pool functionality is implemented by all HTTP client drivers with optimized concurrent execution strategies:

### Guzzle Driver

Uses Guzzle's native Promise-based pooling:

```php
$guzzleClient = HttpClient::using('guzzle');
$results = $guzzleClient->pool($requests, maxConcurrent: 5);
```

- Native concurrent execution via promises
- Efficient connection pooling
- Best performance for high-concurrency scenarios

### Symfony Driver

Uses Symfony HTTP Client's streaming responses:

```php
$symfonyClient = HttpClient::using('symfony');
$results = $symfonyClient->pool($requests, maxConcurrent: 10);
```

- Native concurrent execution
- Excellent streaming support
- Good performance with many concurrent requests

### Laravel Driver

Uses Laravel HTTP Client's batched pool execution:

```php
$laravelClient = HttpClient::using('laravel');
$results = $laravelClient->pool($requests, maxConcurrent: 3);
```

- Batched execution when request count exceeds maxConcurrent
- Integrates with Laravel's HTTP facade
- Suitable for Laravel applications

### CurlNew Driver

Modern curl_multi-based implementation:

```php
$curlClient = HttpClient::using('curl-new');
$results = $curlClient->pool($requests, maxConcurrent: 8);
```

- No external dependencies
- Efficient streaming support
- Good for environments without Guzzle/Symfony

### Curl Driver (Legacy)

Traditional curl_multi implementation:

```php
$curlClient = HttpClient::using('curl');
$results = $curlClient->pool($requests, maxConcurrent: 5);
```

- Legacy implementation
- No external dependencies
- Use CurlNew for new projects

### Mock Driver

**Note**: The Mock driver does NOT implement concurrent pool execution. It processes requests sequentially via the standard `handle()` method. This is intentional for testing scenarios.

```php
use Cognesy\Http\Creation\HttpClientBuilder;

$client = (new HttpClientBuilder())
    ->withMock(function ($mock) {
        $mock->on()->post('https://api.example.com/batch/1')->replyJson(['result' => 1]);
        $mock->on()->post('https://api.example.com/batch/2')->replyJson(['result' => 2]);
        $mock->on()->post('https://api.example.com/batch/3')->replyJson(['result' => 3]);
    })
    ->create();

$requests = HttpRequestList::of(
    new HttpRequest('https://api.example.com/batch/1', 'POST'),
    new HttpRequest('https://api.example.com/batch/2', 'POST'),
    new HttpRequest('https://api.example.com/batch/3', 'POST'),
);

// Executes sequentially through handle(), not concurrently
$results = $client->pool($requests);
```

## Configuration

### Setting Concurrency Limits

```php
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Creation\HttpClientBuilder;

// Configure default concurrency
$config = new HttpClientConfig(
    driver: 'guzzle',
    maxConcurrent: 10,
    poolTimeout: 60,
    connectTimeout: 5,
    requestTimeout: 30,
);

$client = (new HttpClientBuilder())
    ->withConfig($config)
    ->create();

// Override per pool execution
$results = $client->pool($requests, maxConcurrent: 5);
```

### Pool Timeout

```php
$config = new HttpClientConfig(
    driver: 'symfony',
    poolTimeout: 120, // Maximum time for entire pool execution
);

$client = (new HttpClientBuilder())->withConfig($config)->create();
```

## Performance Considerations

### Concurrency Limits

Choose appropriate concurrency limits based on:

- **API Rate Limits**: Don't exceed target API's rate limits
- **System Resources**: Balance against available CPU/memory
- **Network Bandwidth**: Consider connection limits

```php
// Conservative for rate-limited APIs
$results = $client->pool($requests, maxConcurrent: 2);

// Aggressive for internal services
$results = $client->pool($requests, maxConcurrent: 20);
```

### Memory Usage

Large numbers of concurrent requests consume more memory:

```php
// Process large batches in chunks
$allUserIds = range(1, 10000);
$chunkSize = 100;

foreach (array_chunk($allUserIds, $chunkSize) as $chunk) {
    $requestArray = array_map(
        fn($id) => new HttpRequest("https://api.example.com/users/{$id}", 'GET'),
        $chunk
    );

    $requests = HttpRequestList::fromArray($requestArray);
    $results = $client->pool($requests, maxConcurrent: 10);

    // Process this batch
    processResults($results);
}
```

### Connection Pooling

HTTP drivers may reuse connections for better performance:

```php
// Guzzle and Symfony drivers automatically pool connections
$client = HttpClient::using('guzzle');

// Making multiple pool calls reuses connections
$results1 = $client->pool($firstBatch);
$results2 = $client->pool($secondBatch); // Reuses connections
```

### Error Handling Strategy

Failed requests don't stop pool execution:

```php
$results = $client->pool($requests);

// Identify retriable errors
$retriableRequests = HttpRequestList::empty();

foreach ($results->all() as $result) {
    if ($result->isFailure()) {
        $error = $result->error();

        if ($error instanceof HttpRequestException && $error->isRetriable()) {
            // Collect failed request for retry
            $retriableRequests = $retriableRequests->withAppended(
                $error->getRequest()
            );
        }
    }
}

// Retry failed requests
if (!$retriableRequests->isEmpty()) {
    $retryResults = $client->pool($retriableRequests, maxConcurrent: 2);
}
```

## Best Practices

1. **Use Typed Collections**: Always use `HttpRequestList` for type safety and compile-time checks
2. **Check Result Status**: Pool responses are Result monads - check `isSuccess()` before unwrapping
3. **Set Appropriate Concurrency**: Match concurrency to API rate limits and system resources
4. **Handle Partial Failures**: Pool execution continues even if some requests fail
5. **Process Results Incrementally**: For large batches, process results in chunks to manage memory
6. **Use Collection Methods**: Leverage `successful()`, `failed()`, `filter()`, `map()` for cleaner code
7. **Monitor Performance**: Track `successCount()` and `failureCount()` for observability
8. **Retry Strategically**: Use `isRetriable()` on exceptions to identify retry candidates

## Migration from Array-Based API

If you're upgrading from a version that used raw arrays:

```php
// Old (pre-collections):
$results = $http->pool([$request1, $request2]);
$first = $results[0];

// New (with collections):
use Cognesy\Http\Collections\HttpRequestList;

$requests = HttpRequestList::of($request1, $request2);
$results = $http->pool($requests);
$first = $results->all()[0]; // Or iterate directly

// Better: use collection methods
foreach ($results as $result) {
    if ($result->isSuccess()) {
        $response = $result->unwrap();
        // Process response
    }
}

// Or get only successful responses
$successful = $results->successful();
foreach ($successful as $response) {
    // Process response
}
```

## Examples

### Complete LLM Pool Example

```php
use Cognesy\Http\HttpClient;
use Cognesy\Http\Collections\HttpRequestList;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Exceptions\TimeoutException;
use Cognesy\Http\Exceptions\ServerErrorException;

$client = HttpClient::using('guzzle');

// Prepare prompts for different LLM providers
$requests = HttpRequestList::of(
    new HttpRequest(
        'https://api.openai.com/v1/chat/completions',
        'POST',
        ['Authorization' => 'Bearer ' . getenv('OPENAI_API_KEY')],
        json_encode(['model' => 'gpt-4', 'messages' => $messages]),
        ['timeout' => 30]
    ),
    new HttpRequest(
        'https://api.anthropic.com/v1/messages',
        'POST',
        ['x-api-key' => getenv('ANTHROPIC_API_KEY')],
        json_encode(['model' => 'claude-3-opus-20240229', 'messages' => $messages]),
        ['timeout' => 30]
    ),
);

// Execute pool
$results = $client->pool($requests, maxConcurrent: 2);

// Process results
$responses = [];
foreach ($results->all() as $index => $result) {
    if ($result->isSuccess()) {
        $response = $result->unwrap();
        $data = json_decode($response->body(), true);
        $responses[] = [
            'provider' => $index === 0 ? 'OpenAI' : 'Anthropic',
            'content' => $data['choices'][0]['message']['content'] ?? $data['content'][0]['text'] ?? '',
            'duration' => $response->headers()['X-Request-Duration'] ?? 'unknown',
        ];
    } else {
        $error = $result->error();
        error_log("LLM request failed: " . $error->getMessage());

        if ($error instanceof TimeoutException) {
            // Handle timeout
            error_log("Request timed out after {$error->getDuration()}s");
        } elseif ($error instanceof ServerErrorException && $error->isRetriable()) {
            // Consider retry
            error_log("Server error (retriable): {$error->getStatusCode()}");
        }
    }
}

// Output results
foreach ($responses as $response) {
    echo "{$response['provider']}: {$response['content']}\n";
}
```
