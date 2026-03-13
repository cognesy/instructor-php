---
title: 'Handling Responses'
description: 'Read status codes, headers, and bodies from buffered and streamed responses.'
---

When you call `HttpClient::send()`, it returns a `PendingHttpResponse`. This object is lazy -- no HTTP call is made until you explicitly request the response data. This design lets you decide whether to consume the response as a buffered body or as a stream of chunks.

## The Pending Response

`PendingHttpResponse` provides several methods for reading the response:

```php
$pending = $client->send($request);
// @doctest id="6c07"
```

| Method | Returns | Description |
|--------|---------|-------------|
| `get()` | `HttpResponse` | Execute the request and return the full response |
| `statusCode()` | `int` | Execute (if needed) and return the HTTP status code |
| `headers()` | `array` | Execute (if needed) and return the response headers |
| `content()` | `string` | Execute and return the response body as a string |
| `stream()` | `Generator<string>` | Execute in streaming mode and yield chunks |

The pending response caches its result internally. Calling `get()` multiple times will not send the request again. Streaming and synchronous execution are cached independently to avoid mode collisions -- you can safely call both `content()` and `stream()` on the same pending response.

## Buffered Responses

For a standard request, call `get()` to receive the full `HttpResponse`:

```php
$response = $client->send($request)->get();

$status  = $response->statusCode();  // 200
$headers = $response->headers();     // ['Content-Type' => 'application/json', ...]
$body    = $response->body();        // '{"id":1,"name":"John"}'
// @doctest id="835c"
```

### Decoding JSON

Most APIs return JSON. Decode the body with PHP's built-in function:

```php
$data = json_decode($response->body(), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    throw new \RuntimeException('Invalid JSON: ' . json_last_error_msg());
}

echo $data['name']; // John
// @doctest id="a274"
```

### Checking Status Codes

You can inspect the status code to branch on success or failure:

```php
$response = $client->send($request)->get();

if ($response->statusCode() >= 200 && $response->statusCode() < 300) {
    $data = json_decode($response->body(), true);
    // Process successful response
} elseif ($response->statusCode() === 404) {
    // Resource not found
} elseif ($response->statusCode() >= 500) {
    // Server error -- maybe retry
}
// @doctest id="1d41"
```

## Streamed Responses

For streamed requests, call `stream()` on the pending response. This returns a PHP Generator that yields chunks as they arrive from the server:

```php
foreach ($client->send($request)->stream() as $chunk) {
    echo $chunk;
}
// @doctest id="9d28"
```

The `stream()` method always forces streaming mode regardless of the request's `isStreamed()` flag. Similarly, `get()` and `content()` always force synchronous mode.

> **Important:** Calling `body()` on a streamed `HttpResponse` throws a `LogicException`. Use `stream()` instead. This prevents accidentally buffering a large response that was intended to be consumed incrementally.

## Creating Responses Programmatically

The `HttpResponse` class provides factory methods for creating responses in tests or middleware:

```php
use Cognesy\Http\Data\HttpResponse;

// Synchronous response
$sync = HttpResponse::sync(
    statusCode: 200,
    headers: ['Content-Type' => 'application/json'],
    body: '{"ok":true}',
);

// Streaming response
$streamed = HttpResponse::streaming(
    statusCode: 200,
    headers: ['Content-Type' => 'text/event-stream'],
    stream: $someStreamInterface,
);

// Empty response
$empty = HttpResponse::empty();
// @doctest id="f29d"
```

## Error Handling with failOnError

By default, `failOnError` is `false` and 4xx/5xx responses are returned normally. When you set it to `true` in the config, the client throws typed exceptions:

```php
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Exceptions\HttpClientErrorException;
use Cognesy\Http\Exceptions\ServerErrorException;

$client = HttpClient::fromConfig(new HttpClientConfig(failOnError: true));

try {
    $response = $client->send($request)->get();
} catch (HttpClientErrorException $e) {
    // 4xx error
    echo "Client error {$e->getStatusCode()}: {$e->getMessage()}\n";
    echo "Response body: {$e->getResponse()->body()}\n";
} catch (ServerErrorException $e) {
    // 5xx error
    echo "Server error {$e->getStatusCode()}: {$e->getMessage()}\n";
}
// @doctest id="e9ac"
```

Each exception carries the original request, the response (if available), and the duration of the call. Use `$e->getRequest()`, `$e->getResponse()`, and `$e->getDuration()` to inspect them.

## Response Metadata

The `HttpResponse` object also exposes metadata about the response:

```php
$response->isStreamed();   // true if the response was created in streaming mode
$response->isStreaming();  // true if the stream has not yet completed
$response->rawStream();    // access the underlying StreamInterface
// @doctest id="e2c3"
```

You can create a new response with a replaced stream using `withStream()`:

```php
$decorated = $response->withStream($transformedStream);
// @doctest id="7156"
```

This is the primary mechanism used by middleware to intercept and transform streamed data.
