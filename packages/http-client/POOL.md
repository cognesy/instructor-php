# HTTP Request Pool

The HTTP Client provides concurrent request pooling capabilities for executing multiple HTTP requests in parallel. This is particularly useful for LLM API calls, mixture-of-experts patterns, and any scenario requiring multiple simultaneous HTTP requests.

## Features

- **Concurrent Execution**: Execute multiple requests simultaneously with configurable concurrency limits
- **Driver Agnostic**: Works with all supported HTTP client drivers (Guzzle, Symfony, Laravel)
- **Deferred Execution**: Create pools that can be executed later with different parameters
- **Error Handling**: Graceful handling of failures with Result objects
- **Reusable**: Pool instances can be executed multiple times

## Usage

### Immediate Execution

Execute a pool of requests immediately:

```php
use Cognesy\Http\HttpClient;
use Cognesy\Http\Data\HttpRequest;

$client = HttpClient::default();

$requests = [
    new HttpRequest('https://api.openai.com/v1/chat/completions', 'POST', [], $prompt1, []),
    new HttpRequest('https://api.anthropic.com/v1/messages', 'POST', [], $prompt2, []),
    new HttpRequest('https://api.cohere.com/v1/generate', 'POST', [], $prompt3, []),
];

$results = $client->pool($requests, maxConcurrent: 3);
```

### Deferred Execution

Create a pool that can be executed later:

```php
$pool = $client->withPool($requests);

// Execute when ready
$results = $pool->all(maxConcurrent: 2);

// Can be executed multiple times
$moreResults = $pool->all(maxConcurrent: 1);
```

### Parameters

- **`$requests`**: Array of `HttpRequest` objects to execute
- **`$maxConcurrent`**: Maximum number of concurrent requests (optional, defaults to driver configuration)

## Use Cases

### Multiple LLM APIs

Query multiple LLM providers in parallel:

```php
$prompt = ['model' => 'gpt-4', 'messages' => [['role' => 'user', 'content' => 'Explain AI']]];

$requests = [
    new HttpRequest('https://api.openai.com/v1/chat/completions', 'POST', [], $prompt, []),
    new HttpRequest('https://api.anthropic.com/v1/messages', 'POST', [], $prompt, []),
    new HttpRequest('https://api.cohere.com/v1/generate', 'POST', [], $prompt, []),
];

$results = $client->pool($requests);
```

### Mixture of Experts

Send the same query to multiple models for comparison:

```php
$experts = [
    ['endpoint' => 'https://api.openai.com/v1/chat/completions', 'model' => 'gpt-4'],
    ['endpoint' => 'https://api.openai.com/v1/chat/completions', 'model' => 'gpt-3.5-turbo'],
    ['endpoint' => 'https://api.anthropic.com/v1/messages', 'model' => 'claude-3-opus'],
];

$requests = array_map(function($expert) use ($prompt) {
    return new HttpRequest($expert['endpoint'], 'POST', [], 
        array_merge($prompt, ['model' => $expert['model']]), []);
}, $experts);

$results = $client->pool($requests, maxConcurrent: 3);
```

### Batch Processing

Process multiple API calls efficiently:

```php
$userIds = [1, 2, 3, 4, 5];
$requests = array_map(function($id) {
    return new HttpRequest("https://api.example.com/users/{$id}", 'GET', [], [], []);
}, $userIds);

$results = $client->pool($requests, maxConcurrent: 3);
```

## Result Handling

Pool execution returns an array of `Result` objects:

```php
$results = $client->pool($requests);

foreach ($results as $i => $result) {
    if ($result->isSuccess()) {
        $response = $result->unwrap();
        echo "Request {$i} succeeded: " . $response->body();
    } else {
        $error = $result->error();
        echo "Request {$i} failed: " . $error->getMessage();
    }
}
```

## Driver Support

Pool functionality works with all HTTP client drivers:

```php
// Use specific driver
$guzzleClient = HttpClient::using('guzzle');
$results = $guzzleClient->pool($requests);

$symfonyClient = HttpClient::using('symfony');
$results = $symfonyClient->pool($requests);

$laravelClient = HttpClient::using('laravel');
$results = $laravelClient->pool($requests);
```

## Performance Considerations

- **Concurrency Limits**: Set appropriate `maxConcurrent` values based on target API rate limits
- **Connection Pooling**: HTTP client drivers may reuse connections for better performance
- **Error Handling**: Failed requests don't stop the entire pool execution
- **Memory Usage**: Large numbers of concurrent requests will consume more memory

## Future Extensions

The pool implementation is designed for evolution:

- **`race()`**: Execute until first successful response
- **`stream()`**: Stream results as they become available
- **Custom strategies**: Implement custom pool execution patterns

These extensions can be added without breaking existing code using the `all()` method.