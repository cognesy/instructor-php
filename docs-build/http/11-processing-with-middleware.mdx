---
title: Custom Processing with Middleware
description: 'Learn how to use middleware to process HTTP requests and responses using the Instructor HTTP client API.'
---

While the Instructor HTTP client API provides several built-in middleware components, you'll often need to create custom middleware to handle specific requirements for your application. This chapter explores how to create custom middleware components and use response decoration for advanced processing.

## Creating Custom Middleware

There are three main approaches to creating custom middleware:

1. Implementing the `HttpMiddleware` interface directly
2. Extending the `BaseMiddleware` abstract class
3. Using anonymous classes for simple middleware

### Approach 1: Implementing HttpMiddleware Interface

The most direct approach is to implement the `HttpMiddleware` interface:

```php
<?php

namespace YourNamespace\Http\Middleware;

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\HttpMiddleware;
use Cognesy\Http\Contracts\HttpClientResponse;
use Cognesy\Http\Data\HttpClientRequest;

class LoggingMiddleware implements HttpMiddleware
{
    private $logger;

    public function __construct($logger)
    {
        $this->logger = $logger;
    }

    public function handle(HttpClientRequest $request, CanHandleHttpRequest $next): HttpClientResponse
    {
        // Pre-request logging
        $this->logger->info('Sending request', [
            'url' => $request->url(),
            'method' => $request->method(),
            'headers' => $request->headers(),
            'body' => $request->body()->toString(),
        ]);

        $startTime = microtime(true);

        // Call the next handler in the chain
        $response = $next->handle($request);

        $endTime = microtime(true);
        $duration = round(($endTime - $startTime) * 1000, 2); // in milliseconds

        // Post-response logging
        $this->logger->info('Received response', [
            'status' => $response->statusCode(),
            'headers' => $response->headers(),
            'body' => $response->body(),
            'duration_ms' => $duration,
        ]);

        // Return the response
        return $response;
    }
}
```

This approach gives you complete control over the middleware behavior, but it requires you to implement the entire logic from scratch.

### Approach 2: Extending BaseMiddleware

For most cases, extending the `BaseMiddleware` abstract class is more convenient:

```php
<?php

namespace YourNamespace\Http\Middleware;

use Cognesy\Http\BaseMiddleware;
use Cognesy\Http\Contracts\HttpClientResponse;
use Cognesy\Http\Data\HttpClientRequest;

class AuthenticationMiddleware extends BaseMiddleware
{
    private $apiKey;

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
    }

    protected function beforeRequest(HttpClientRequest $request): void
    {
        // Add authorization header to the request
        $headers = $request->headers();
        $headers['Authorization'] = 'Bearer ' . $this->apiKey;

        // Note: In a real implementation, you would need to create a new request
        // with the updated headers, as HttpClientRequest is immutable
    }

    protected function afterRequest(
        HttpClientRequest $request,
        HttpClientResponse $response
    ): HttpClientResponse {
        // Check if the response indicates an authentication error
        if ($response->statusCode() === 401) {
            // Log or handle authentication failures
            error_log('Authentication failed: ' . $response->body());
        }

        return $response;
    }
}
```

With `BaseMiddleware`, you only need to override the methods that matter for your middleware:

- `beforeRequest(HttpClientRequest $request): void` - Called before the request is sent
- `afterRequest(HttpClientRequest $request, HttpClientResponse $response): HttpClientResponse` - Called after the response is received
- `shouldDecorateResponse(HttpClientRequest $request, HttpClientResponse $response): bool` - Determines if the response should be decorated
- `toResponse(HttpClientRequest $request, HttpClientResponse $response): HttpClientResponse` - Creates a decorated response

### Approach 3: Using Anonymous Classes

For simple middleware that you only need to use once, you can use anonymous classes:

```php
$client = new HttpClient();

// Add a simple timing middleware
$client->withMiddleware(new class implements HttpMiddleware {
    public function handle(HttpClientRequest $request, CanHandleHttpRequest $next): HttpClientResponse
    {
        $startTime = microtime(true);

        $response = $next->handle($request);

        $endTime = microtime(true);
        $duration = round(($endTime - $startTime) * 1000, 2);

        echo "Request to {$request->url()} took {$duration}ms\n";

        return $response;
    }
});
```

This approach is concise but less reusable than defining a named class.

### Practical Middleware Examples

#### Retry Middleware

This middleware automatically retries failed requests:

```php
<?php

namespace YourNamespace\Http\Middleware;

use Cognesy\Http\BaseMiddleware;
use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\HttpClientResponse;
use Cognesy\Http\Data\HttpClientRequest;
use Cognesy\Http\Exceptions\RequestException;

class RetryMiddleware extends BaseMiddleware
{
    private int $maxRetries;
    private int $retryDelay;
    private array $retryStatusCodes;

    public function __construct(
        int $maxRetries = 3,
        int $retryDelay = 1,
        array $retryStatusCodes = [429, 500, 502, 503, 504]
    ) {
        $this->maxRetries = $maxRetries;
        $this->retryDelay = $retryDelay;
        $this->retryStatusCodes = $retryStatusCodes;
    }

    public function handle(HttpClientRequest $request, CanHandleHttpRequest $next): HttpClientResponse
    {
        $attempts = 0;

        while (true) {
            try {
                $attempts++;
                $response = $next->handle($request);

                // If we got a response with a status code we should retry on
                if (in_array($response->statusCode(), $this->retryStatusCodes) && $attempts <= $this->maxRetries) {
                    $this->delay($attempts);
                    continue;
                }

                return $response;

            } catch (RequestException $e) {
                // If we've exceeded our retry limit, rethrow the exception
                if ($attempts >= $this->maxRetries) {
                    throw $e;
                }

                // Otherwise, wait and try again
                $this->delay($attempts);
            }
        }
    }

    private function delay(int $attempt): void
    {
        // Exponential backoff: 1s, 2s, 4s, 8s, etc.
        $sleepTime = $this->retryDelay * (2 ** ($attempt - 1));
        sleep($sleepTime);
    }
}
```

#### Rate Limiting Middleware

This middleware throttles requests to respect API rate limits:

```php
<?php

namespace YourNamespace\Http\Middleware;

use Cognesy\Http\BaseMiddleware;
use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\HttpClientResponse;
use Cognesy\Http\Data\HttpClientRequest;

class RateLimitingMiddleware extends BaseMiddleware
{
    private int $maxRequests;
    private int $perSeconds;
    private array $requestTimes = [];

    public function __construct(int $maxRequests = 60, int $perSeconds = 60)
    {
        $this->maxRequests = $maxRequests;
        $this->perSeconds = $perSeconds;
    }

    public function handle(HttpClientRequest $request, CanHandleHttpRequest $next): HttpClientResponse
    {
        // Clean up old request times
        $this->removeOldRequestTimes();

        // If we've hit our limit, wait until we can make another request
        if (count($this->requestTimes) >= $this->maxRequests) {
            $oldestRequest = $this->requestTimes[0];
            $timeToWait = $oldestRequest + $this->perSeconds - time();

            if ($timeToWait > 0) {
                sleep($timeToWait);
            }

            // Clean up again after waiting
            $this->removeOldRequestTimes();
        }

        // Record this request time
        $this->requestTimes[] = time();

        // Make the request
        return $next->handle($request);
    }

    private function removeOldRequestTimes(): void
    {
        $cutoff = time() - $this->perSeconds;

        // Remove request times older than our window
        $this->requestTimes = array_filter(
            $this->requestTimes,
            fn($time) => $time > $cutoff
        );

        // Reindex the array
        $this->requestTimes = array_values($this->requestTimes);
    }
}
```

#### Caching Middleware

This middleware caches responses for GET requests:

```php
<?php

namespace YourNamespace\Http\Middleware;

use Cognesy\Http\BaseMiddleware;
use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\HttpClientResponse;
use Cognesy\Http\Data\HttpClientRequest;
use Cognesy\Http\Adapters\MockHttpResponse;
use Psr\SimpleCache\CacheInterface;

class CachingMiddleware extends BaseMiddleware
{
    private CacheInterface $cache;
    private int $ttl;

    public function __construct(CacheInterface $cache, int $ttl = 3600)
    {
        $this->cache = $cache;
        $this->ttl = $ttl;
    }

    public function handle(HttpClientRequest $request, CanHandleHttpRequest $next): HttpClientResponse
    {
        // Only cache GET requests
        if ($request->method() !== 'GET') {
            return $next->handle($request);
        }

        // Generate a cache key for this request
        $cacheKey = $this->generateCacheKey($request);

        // Check if we have a cached response
        if ($this->cache->has($cacheKey)) {
            $cachedData = $this->cache->get($cacheKey);

            // Create a response from the cached data
            return new MockHttpResponse(
                statusCode: $cachedData['status_code'],
                headers: $cachedData['headers'],
                body: $cachedData['body']
            );
        }

        // If not in cache, make the actual request
        $response = $next->handle($request);

        // Cache the response if it was successful
        if ($response->statusCode() >= 200 && $response->statusCode() < 300) {
            $this->cache->set(
                $cacheKey,
                [
                    'status_code' => $response->statusCode(),
                    'headers' => $response->headers(),
                    'body' => $response->body(),
                ],
                $this->ttl
            );
        }

        return $response;
    }

    private function generateCacheKey(HttpClientRequest $request): string
    {
        return md5($request->method() . $request->url() . $request->body()->toString());
    }
}
```

## Response Decoration

Response decoration is a powerful technique for wrapping HTTP responses to add functionality or transform data. It's particularly useful for streaming responses, where you need to process each chunk as it arrives.

### Creating a Response Decorator

All response decorators should implement the `HttpClientResponse` interface. The library provides a `BaseResponseDecorator` class that makes this easier:

```php
<?php

namespace YourNamespace\Http\Middleware;

use Cognesy\Http\BaseResponseDecorator;
use Cognesy\Http\Contracts\HttpClientResponse;
use Cognesy\Http\Data\HttpClientRequest;
use Generator;

class JsonStreamDecorator extends BaseResponseDecorator
{
    private string $buffer = '';

    public function __construct(
        HttpClientRequest $request,
        HttpClientResponse $response
    ) {
        parent::__construct($request, $response);
    }

    public function stream(int $chunkSize = 1): Generator
    {
        foreach ($this->response->stream($chunkSize) as $chunk) {
            // Add the chunk to our buffer
            $this->buffer .= $chunk;

            // Process complete JSON objects
            $result = $this->processBuffer();

            // Yield the original chunk (or modified if needed)
            yield $chunk;
        }
    }

    private function processBuffer(): void
    {
        // Keep processing until we can't find any more complete JSON objects
        while (($jsonEnd = strpos($this->buffer, '}')) !== false) {
            // Try to find the start of the JSON object
            $jsonStart = strpos($this->buffer, '{');

            if ($jsonStart === false || $jsonStart > $jsonEnd) {
                // Invalid JSON, discard up to the end
                $this->buffer = substr($this->buffer, $jsonEnd + 1);
                continue;
            }

            // Extract the potential JSON string
            $jsonString = substr($this->buffer, $jsonStart, $jsonEnd - $jsonStart + 1);

            // Try to decode it
            $data = json_decode($jsonString, true);

            if ($data !== null) {
                // We found a valid JSON object!
                // You could process it here or dispatch an event

                // Remove the processed part from the buffer
                $this->buffer = substr($this->buffer, $jsonEnd + 1);
            } else {
                // Invalid JSON, it might be incomplete
                // Keep waiting for more data
                break;
            }
        }
    }
}
```

### Using Response Decorators in Middleware

To use a response decorator, you need to create a middleware that wraps the response:

```php
<?php

namespace YourNamespace\Http\Middleware;

use Cognesy\Http\BaseMiddleware;
use Cognesy\Http\Contracts\HttpClientResponse;
use Cognesy\Http\Data\HttpClientRequest;

class JsonStreamMiddleware extends BaseMiddleware
{
    protected function shouldDecorateResponse(
        HttpClientRequest $request,
        HttpClientResponse $response
    ): bool {
        // Only decorate streaming JSON responses
        return $request->isStreamed() &&
               isset($response->headers()['Content-Type']) &&
               strpos($response->headers()['Content-Type'][0], 'application/json') !== false;
    }

    protected function toResponse(
        HttpClientRequest $request,
        HttpClientResponse $response
    ): HttpClientResponse {
        return new JsonStreamDecorator($request, $response);
    }
}
```

Then add the middleware to your client:

```php
$client = new HttpClient();
$client->withMiddleware(new JsonStreamMiddleware());
```

### Response Decoration for Transforming Content

You can use response decoration to transform response content on-the-fly:

```php
<?php

namespace YourNamespace\Http\Middleware;

use Cognesy\Http\BaseResponseDecorator;
use Cognesy\Http\Contracts\HttpClientResponse;
use Cognesy\Http\Data\HttpClientRequest;

class XmlToJsonDecorator extends BaseResponseDecorator
{
    public function body(): string
    {
        // Get the original XML body
        $xmlBody = $this->response->body();

        // Convert XML to JSON
        $xml = simplexml_load_string($xmlBody);
        $jsonBody = json_encode($xml);

        return $jsonBody;
    }

    public function headers(): array
    {
        $headers = $this->response->headers();

        // Update the Content-Type header
        $headers['Content-Type'] = ['application/json'];

        return $headers;
    }
}
```

And the corresponding middleware:

```php
<?php

namespace YourNamespace\Http\Middleware;

use Cognesy\Http\BaseMiddleware;
use Cognesy\Http\Contracts\HttpClientResponse;
use Cognesy\Http\Data\HttpClientRequest;

class XmlToJsonMiddleware extends BaseMiddleware
{
    protected function shouldDecorateResponse(
        HttpClientRequest $request,
        HttpClientResponse $response
    ): bool {
        // Only transform XML responses
        return isset($response->headers()['Content-Type']) &&
               strpos($response->headers()['Content-Type'][0], 'application/xml') !== false;
    }

    protected function toResponse(
        HttpClientRequest $request,
        HttpClientResponse $response
    ): HttpClientResponse {
        return new XmlToJsonDecorator($request, $response);
    }
}
```

## Advanced Middleware Examples

Here are some more advanced middleware examples that demonstrate the power and flexibility of the middleware system.

### Analytics Middleware

This middleware collects analytics data about HTTP requests:

```php
<?php

namespace YourNamespace\Http\Middleware;

use Cognesy\Http\BaseMiddleware;
use Cognesy\Http\Contracts\HttpClientResponse;
use Cognesy\Http\Data\HttpClientRequest;

class AnalyticsMiddleware extends BaseMiddleware
{
    private $analytics;

    public function __construct($analyticsService)
    {
        $this->analytics = $analyticsService;
    }

    protected function beforeRequest(HttpClientRequest $request): void
    {
        // Record the start time
        $this->startTime = microtime(true);
    }

    protected function afterRequest(
        HttpClientRequest $request,
        HttpClientResponse $response
    ): HttpClientResponse {
        $endTime = microtime(true);
        $duration = round(($endTime - $this->startTime) * 1000, 2);

        // Extract API endpoint from URL
        $url = parse_url($request->url());
        $endpoint = $url['path'] ?? '/';

        // Record analytics data
        $this->analytics->recordApiCall([
            'endpoint' => $endpoint,
            'method' => $request->method(),
            'status_code' => $response->statusCode(),
            'duration_ms' => $duration,
            'request_size' => strlen($request->body()->toString()),
            'response_size' => strlen($response->body()),
            'timestamp' => time(),
        ]);

        return $response;
    }
}
```

### Circuit Breaker Middleware

This middleware implements the circuit breaker pattern to prevent repeated calls to failing services:

```php
<?php

namespace YourNamespace\Http\Middleware;

use Cognesy\Http\BaseMiddleware;
use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\HttpClientResponse;
use Cognesy\Http\Data\HttpClientRequest;
use Cognesy\Http\Exceptions\RequestException;
use Cognesy\Http\Adapters\MockHttpResponse;

class CircuitBreakerMiddleware extends BaseMiddleware
{
    private array $circuits = [];
    private int $failureThreshold;
    private int $resetTimeout;

    public function __construct(int $failureThreshold = 3, int $resetTimeout = 60)
    {
        $this->failureThreshold = $failureThreshold;
        $this->resetTimeout = $resetTimeout;
    }

    public function handle(HttpClientRequest $request, CanHandleHttpRequest $next): HttpClientResponse
    {
        $hostname = parse_url($request->url(), PHP_URL_HOST);

        // Initialize circuit state if it doesn't exist
        if (!isset($this->circuits[$hostname])) {
            $this->circuits[$hostname] = [
                'state' => 'CLOSED',
                'failures' => 0,
                'last_failure_time' => 0,
            ];
        }

        $circuit = &$this->circuits[$hostname];

        // Check if circuit is open (service is considered down)
        if ($circuit['state'] === 'OPEN') {
            // Check if we should try resetting the circuit
            $timeSinceLastFailure = time() - $circuit['last_failure_time'];

            if ($timeSinceLastFailure >= $this->resetTimeout) {
                // Move to half-open state to test the service
                $circuit['state'] = 'HALF_OPEN';
            } else {
                // Circuit is still open, return error response
                return new MockHttpResponse(
                    statusCode: 503,
                    headers: ['Content-Type' => 'application/json'],
                    body: json_encode([
                        'error' => 'Service Unavailable',
                        'message' => 'Circuit breaker is open',
                        'retry_after' => $this->resetTimeout - $timeSinceLastFailure,
                    ])
                );
            }
        }

        try {
            // Attempt the request
            $response = $next->handle($request);

            // If successful and in half-open state, reset the circuit
            if ($circuit['state'] === 'HALF_OPEN') {
                $circuit['state'] = 'CLOSED';
                $circuit['failures'] = 0;
            }

            return $response;

        } catch (RequestException $e) {
            // Record the failure
            $circuit['failures']++;
            $circuit['last_failure_time'] = time();

            // If failures exceed threshold, open the circuit
            if ($circuit['failures'] >= $this->failureThreshold || $circuit['state'] === 'HALF_OPEN') {
                $circuit['state'] = 'OPEN';
            }

            // Re-throw the exception
            throw $e;
        }
    }
}
```

### Conditional Middleware

This middleware only applies to certain requests based on a condition:

```php
<?php

namespace YourNamespace\Http\Middleware;

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\HttpMiddleware;
use Cognesy\Http\Contracts\HttpClientResponse;
use Cognesy\Polyglot\Http\Data\HttpClientRequest;

class ConditionalMiddleware implements HttpMiddleware
{
    private HttpMiddleware $middleware;
    private callable $condition;

    public function __construct(HttpMiddleware $middleware, callable $condition)
    {
        $this->middleware = $middleware;
        $this->condition = $condition;
    }

    public function handle(HttpClientRequest $request, CanHandleHttpRequest $next): HttpClientResponse
    {
        // Check if the condition is met
        if (($this->condition)($request)) {
            // Apply the wrapped middleware
            return $this->middleware->handle($request, $next);
        }

        // Skip the middleware if condition is not met
        return $next->handle($request);
    }
}
```

Usage example:

```php
// Only apply caching middleware to GET requests
$cachingMiddleware = new CachingMiddleware($cache);
$conditionalCaching = new ConditionalMiddleware(
    $cachingMiddleware,
    fn($request) => $request->method() === 'GET'
);

$client->withMiddleware($conditionalCaching);
```

### Request ID Middleware

This middleware adds a unique ID to each request and tracks it through the response:

```php
<?php

namespace YourNamespace\Http\Middleware;

use Cognesy\Polyglot\Http\BaseMiddleware;
use Cognesy\Polyglot\Http\Contracts\HttpClientResponse;
use Cognesy\Polyglot\Http\Data\HttpClientRequest;
use Ramsey\Uuid\Uuid;

class RequestIdMiddleware extends BaseMiddleware
{
    private array $requestIds = [];

    protected function beforeRequest(HttpClientRequest $request): void
    {
        // Generate a unique ID for this request
        $requestId = Uuid::uuid4()->toString();

        // Store the ID for this request
        $this->requestIds[spl_object_hash($request)] = $requestId;

        // Add a header to the outgoing request
        $headers = $request->headers();
        $headers['X-Request-ID'] = $requestId;

        // In a real implementation, you would need to create a new request
        // with the updated headers, as HttpClientRequest is immutable
    }

    protected function afterRequest(
        HttpClientRequest $request,
        HttpClientResponse $response
    ): HttpClientResponse {
        // Get the request ID
        $requestId = $this->requestIds[spl_object_hash($request)] ?? 'unknown';

        // Log the request completion
        error_log("Request $requestId completed with status: " . $response->statusCode());

        // Clean up
        unset($this->requestIds[spl_object_hash($request)]);

        return $response;
    }
}
```

### OpenTelemetry Tracing Middleware

This middleware adds OpenTelemetry tracing to HTTP requests:

```php
<?php

namespace YourNamespace\Http\Middleware;

use Cognesy\Polyglot\Http\BaseMiddleware;
use Cognesy\Polyglot\Http\Contracts\HttpClientResponse;
use Cognesy\Polyglot\Http\Data\HttpClientRequest;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;

class TracingMiddleware extends BaseMiddleware
{
    private TracerInterface $tracer;

    public function __construct(TracerInterface $tracer)
    {
        $this->tracer = $tracer;
    }

    protected function beforeRequest(HttpClientRequest $request): void
    {
        // No actions needed in beforeRequest,
        // we'll create the span in the handle method
    }

    public function handle(HttpClientRequest $request, CanHandleHttpRequest $next): HttpClientResponse
    {
        // Extract the operation name from the URL
        $url = parse_url($request->url());
        $path = $url['path'] ?? '/';
        $operationName = $request->method() . ' ' . $path;

        // Create a span for this request
        $span = $this->tracer->spanBuilder($operationName)
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->startSpan();

        $scope = $span->activate();

        try {
            // Add request details to the span
            $span->setAttribute('http.method', $request->method());
            $span->setAttribute('http.url', $request->url());
            $span->setAttribute('http.request_content_length', strlen($request->body()->toString()));

            // Make the request
            $response = $next->handle($request);

            // Add response details to the span
            $span->setAttribute('http.status_code', $response->statusCode());
            $span->setAttribute('http.response_content_length', strlen($response->body()));

            // Set the appropriate status
            if ($response->statusCode() >= 400) {
                $span->setStatus(StatusCode::STATUS_ERROR, "HTTP error: {$response->statusCode()}");
            } else {
                $span->setStatus(StatusCode::STATUS_OK);
            }

            return $response;
        } catch (\Exception $e) {
            // Record the error
            $span->recordException($e);
            $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());

            // Re-throw the exception
            throw $e;
        } finally {
            // End the span
            $scope->detach();
            $span->end();
        }
    }
}
```

### Customizing Middleware for LLM APIs

When working with Large Language Model (LLM) APIs, you can create specialized middleware to handle their unique requirements:

```php
<?php

namespace YourNamespace\Http\Middleware;

use Cognesy\Polyglot\Http\BaseMiddleware;
use Cognesy\Polyglot\Http\Contracts\HttpClientResponse;
use Cognesy\Polyglot\Http\Data\HttpClientRequest;

class LlmStreamingMiddleware extends BaseMiddleware
{
    protected function shouldDecorateResponse(
        HttpClientRequest $request,
        HttpClientResponse $response
    ): bool {
        // Only decorate streaming responses to LLM APIs
        return $request->isStreamed() &&
               strpos($request->url(), 'api.openai.com') !== false;
    }

    protected function toResponse(
        HttpClientRequest $request,
        HttpClientResponse $response
    ): HttpClientResponse {
        return new class($request, $response) extends BaseResponseDecorator {
            private string $buffer = '';
            private array $chunks = [];

            public function stream(int $chunkSize = 1): Generator
            {
                foreach ($this->response->stream($chunkSize) as $chunk) {
                    $this->buffer .= $chunk;

                    // Process lines in the buffer
                    $lines = explode("\n", $this->buffer);

                    // Keep the last line (potentially incomplete) in the buffer
                    $this->buffer = array_pop($lines);

                    foreach ($lines as $line) {
                        $line = trim($line);

                        // Skip empty lines
                        if (empty($line)) {
                            continue;
                        }

                        // Skip data: prefix
                        if (strpos($line, 'data: ') === 0) {
                            $line = substr($line, 6);
                        }

                        // Skip [DONE] message
                        if ($line === '[DONE]') {
                            continue;
                        }

                        // Try to parse as JSON
                        $data = json_decode($line, true);

                        if ($data) {
                            // Extract content from different LLM formats
                            $content = null;

                            if (isset($data['choices'][0]['delta']['content'])) {
                                // OpenAI format
                                $content = $data['choices'][0]['delta']['content'];
                            } elseif (isset($data['choices'][0]['text'])) {
                                // Another format
                                $content = $data['choices'][0]['text'];
                            } elseif (isset($data['text'])) {
                                // Simple format
                                $content = $data['text'];
                            }

                            if ($content !== null) {
                                $this->chunks[] = $content;
                            }
                        }
                    }

                    // Yield the original chunk to maintain streaming behavior
                    yield $chunk;
                }
            }

            public function body(): string
            {
                // If we've processed chunks, join them together
                if (!empty($this->chunks)) {
                    return implode('', $this->chunks);
                }

                // Otherwise, fall back to the normal body
                return $this->response->body();
            }
        };
    }
}
```

### Combining Multiple Middleware Components

When building complex applications, you'll often need to combine multiple middleware components. Here's an example of how to set up a complete HTTP client pipeline:

```php
<?php

use Cognesy\Polyglot\Http\HttpClient;
use YourNamespace\Http\Middleware\AuthenticationMiddleware;
use YourNamespace\Http\Middleware\CircuitBreakerMiddleware;
use YourNamespace\Http\Middleware\RateLimitingMiddleware;
use YourNamespace\Http\Middleware\RetryMiddleware;
use YourNamespace\Http\Middleware\TracingMiddleware;
use YourNamespace\Http\Middleware\LoggingMiddleware;
use YourNamespace\Http\Middleware\CachingMiddleware;
use YourNamespace\Http\Middleware\AnalyticsMiddleware;

// Create services needed by middleware
$cache = new YourCacheService();
$logger = new YourLoggerService();
$tracer = YourTracerFactory::create();
$analytics = new YourAnalyticsService();

// Create the client
$client = new HttpClient('guzzle');

// Add middleware - the order is important!
$client->withMiddleware(
    // Outer middleware (processed first for requests, last for responses)
    new TracingMiddleware($tracer),
    new LoggingMiddleware($logger),
    new CircuitBreakerMiddleware(),

    // Caching should go before authentication
    new CachingMiddleware($cache),

    // Authentication adds credentials
    new AuthenticationMiddleware($apiKey),

    // These control how requests are sent
    new RetryMiddleware(maxRetries: 3),
    new RateLimitingMiddleware(maxRequests: 100),

    // Analytics should be innermost to measure actual API call stats
    new AnalyticsMiddleware($analytics)
);

// Now the client is ready to use with a complete middleware pipeline
$response = $client->handle($request);
```

With this setup, requests and responses flow through the middleware in the following order:

1. **Request Flow** (outside → inside):
- TracingMiddleware: Starts a trace
- LoggingMiddleware: Logs the outgoing request
- CircuitBreakerMiddleware: Checks if the service is available
- CachingMiddleware: Checks if response is cached
- AuthenticationMiddleware: Adds authentication headers
- RetryMiddleware: Prepares to retry on failure
- RateLimitingMiddleware: Enforces rate limits
- AnalyticsMiddleware: Starts timing
- HTTP Driver: Sends the actual request

2. **Response Flow** (inside → outside):
- HTTP Driver: Receives the response
- AnalyticsMiddleware: Records API stats
- RateLimitingMiddleware: Updates rate limit counters
- RetryMiddleware: Handles retries if needed
- AuthenticationMiddleware: Verifies authentication status
- CachingMiddleware: Caches the response
- CircuitBreakerMiddleware: Updates circuit state
- LoggingMiddleware: Logs the response
- TracingMiddleware: Completes the trace
- Your Application: Processes the final response

This bidirectional flow allows for powerful request/response processing capabilities.

By creating custom middleware and response decorators, you can extend the HTTP client's functionality to handle any specialized requirements your application might have.

In the next chapter, we'll cover troubleshooting techniques for the Instructor HTTP client API.
