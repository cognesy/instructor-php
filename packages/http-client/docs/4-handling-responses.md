---
title: Handling Responses
description: 'Learn how to handle HTTP responses using the Instructor HTTP client API.'
---

After sending an HTTP request, you need to process the response received from the server. The Instructor HTTP client API provides a consistent interface for handling responses, regardless of the underlying HTTP client implementation.

## Response Interface

All responses implement the `HttpClientResponse` interface, which provides a uniform way to access response data:

```php
interface HttpClientResponse
{
    public function statusCode(): int;
    public function headers(): array;
    public function body(): string;
    public function stream(int $chunkSize = 1): Generator;
}
```

This interface ensures that the same code will work whether you're using Guzzle, Symfony, or Laravel HTTP clients.

### Getting the Response

When you send a request using the `HttpClient::handle()` method, it returns an implementation of `HttpClientResponse`:

```php
$response = $client->handle($request);
```

The specific implementation depends on the HTTP client driver being used:

- `PsrHttpResponse`: Used by the GuzzleDriver
- `SymfonyHttpResponse`: Used by the SymfonyDriver
- `LaravelHttpResponse`: Used by the LaravelDriver
- `MockHttpResponse`: Used by the MockHttpDriver for testing

However, since all these implementations provide the same interface, your code doesn't need to know which one it's working with.

## Status Codes

The status code indicates the result of the HTTP request. You can access it using the `statusCode()` method:

```php
$response = $client->handle($request);
$statusCode = $response->statusCode();

echo "Status code: $statusCode\n";
```

### Status Code Categories

Status codes are grouped into categories:

- **1xx (Informational)**: The request was received and understood
- **2xx (Success)**: The request was successfully received, understood, and accepted
- **3xx (Redirection)**: Further action needs to be taken to complete the request
- **4xx (Client Error)**: The request contains bad syntax or cannot be fulfilled
- **5xx (Server Error)**: The server failed to fulfill a valid request

### Checking Response Status

You can check if a response was successful:

```php
$response = $client->handle($request);

if ($response->statusCode() >= 200 && $response->statusCode() < 300) {
    // Success response
    echo "Request succeeded!\n";
} elseif ($response->statusCode() >= 400 && $response->statusCode() < 500) {
    // Client error
    echo "Client error: {$response->statusCode()}\n";
} elseif ($response->statusCode() >= 500) {
    // Server error
    echo "Server error: {$response->statusCode()}\n";
}
```

### Common Status Codes

Here are some common HTTP status codes you might encounter:

- **200 OK**: The request was successful
- **201 Created**: A new resource was successfully created
- **204 No Content**: The request was successful, but there's no response body
- **400 Bad Request**: The request was malformed or invalid
- **401 Unauthorized**: Authentication is required
- **403 Forbidden**: The client doesn't have permission to access the resource
- **404 Not Found**: The requested resource doesn't exist
- **405 Method Not Allowed**: The HTTP method is not supported for this resource
- **422 Unprocessable Entity**: The request was well-formed but contains semantic errors
- **429 Too Many Requests**: Rate limit exceeded
- **500 Internal Server Error**: A generic server error occurred
- **502 Bad Gateway**: The server received an invalid response from an upstream server
- **503 Service Unavailable**: The server is temporarily unavailable
- **504 Gateway Timeout**: The upstream server didn't respond in time
- **511 Network Authentication Required**: The client needs to authenticate to gain network access


## Headers

Response headers provide metadata about the response. You can access the headers using the `headers()` method:

```php
$response = $client->handle($request);
$headers = $response->headers();

// Print all headers
foreach ($headers as $name => $values) {
    echo "$name: " . implode(', ', $values) . "\n";
}

// Access specific headers
$contentType = $headers['Content-Type'] ?? 'unknown';
$contentLength = $headers['Content-Length'] ?? 'unknown';

echo "Content-Type: $contentType\n";
echo "Content-Length: $contentLength\n";
```

The header names are case-insensitive, but the exact format might vary slightly between client implementations. Some clients normalize header names to title case (e.g., `Content-Type`), while others might use lowercase (e.g., `content-type`).

### Common Response Headers

Here are some common response headers you might encounter:

- **Content-Type**: The MIME type of the response body
- **Content-Length**: The size of the response body in bytes
- **Cache-Control**: Directives for caching mechanisms
- **Set-Cookie**: Cookies to be stored by the client
- **Location**: Used for redirects
- **X-RateLimit-Limit**: The rate limit for the endpoint
- **X-RateLimit-Remaining**: The number of requests remaining in the current rate limit window
- **X-RateLimit-Reset**: When the rate limit will reset

## Body Content

For non-streaming responses, you can get the entire response body as a string using the `body()` method:

```php
$response = $client->handle($request);
$body = $response->body();

echo "Response body: $body\n";
```

### Processing JSON Responses

Many APIs return JSON responses. You can decode them using PHP's `json_decode()` function:

```php
$response = $client->handle($request);
$body = $response->body();

// Decode as associative array
$data = json_decode($body, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo "Error decoding JSON: " . json_last_error_msg() . "\n";
} else {
    // Process the data
    echo "User ID: {$data['id']}\n";
    echo "User Name: {$data['name']}\n";
}
```

### Processing XML Responses

For XML responses, you can use PHP's built-in XML functions:

```php
$response = $client->handle($request);
$body = $response->body();

// Load XML
$xml = simplexml_load_string($body);

if ($xml === false) {
    echo "Error loading XML\n";
} else {
    // Process the XML
    echo "Title: {$xml->title}\n";
    echo "Description: {$xml->description}\n";
}
```

### Processing Binary Responses

For binary responses (like file downloads), you can save the response body to a file:

```php
$response = $client->handle($request);
$body = $response->body();

// Save to file
file_put_contents('downloaded_file.pdf', $body);
echo "File downloaded successfully\n";
```

## Error Handling

When making HTTP requests, various errors can occur. The Instructor HTTP client API provides a consistent way to handle these errors through exceptions.

### RequestException

The main exception type is `RequestException`, which is thrown when a request fails:

```php
use Cognesy\Http\Exceptions\HttpRequestException;

try {
    $response = $client->handle($request);
    // Process the response
} catch (HttpRequestException $e) {
    echo "Request failed: {$e->getMessage()}\n";

    // You might want to log the error or take other actions
    if ($e->getPrevious() !== null) {
        echo "Original exception: " . $e->getPrevious()->getMessage() . "\n";
    }
}
```

The `RequestException` often wraps another exception from the underlying HTTP client, which you can access with `$e->getPrevious()`.

### Error Response Handling

By default, HTTP error responses (4xx, 5xx status codes) do not throw exceptions. You can control this behavior using the `failOnError` configuration option:

```php
// In config/http.php
'failOnError' => true, // Throw exceptions for 4xx/5xx responses
```

When `failOnError` is set to `true`, the client will throw a `RequestException` for error responses. When it's `false`, you need to check the status code yourself:

```php
$response = $client->handle($request);

if ($response->statusCode() >= 400) {
    // Handle error response
    echo "Error: HTTP {$response->statusCode()}\n";
    echo "Error details: {$response->body()}\n";
} else {
    // Process successful response
}
```

### Retrying Failed Requests

If a request fails, you might want to retry it. Here's a simple implementation of a retry mechanism:

```php
use Cognesy\Http\Exceptions\HttpRequestException;

function retryRequest($client, $request, $maxRetries = 3, $delay = 1): ?HttpClientResponse {
    $attempts = 0;

    while ($attempts < $maxRetries) {
        try {
            return $client->handle($request);
        } catch (HttpRequestException $e) {
            $attempts++;

            if ($attempts >= $maxRetries) {
                throw $e; // Rethrow after all retries failed
            }

            // Wait before retrying (with exponential backoff)
            $sleepTime = $delay * pow(2, $attempts - 1);
            echo "Request failed, retrying in {$sleepTime} seconds...\n";
            sleep($sleepTime);
        }
    }

    return null; // Should never reach here
}

// Usage
try {
    $response = retryRequest($client, $request);
    // Process the response
} catch (HttpRequestException $e) {
    echo "All retry attempts failed: {$e->getMessage()}\n";
}
```

This function will retry failed requests with exponential backoff, meaning it waits longer between each retry attempt.

In the next chapter, we'll explore streaming responses, which are particularly useful for handling large responses or real-time data streams.