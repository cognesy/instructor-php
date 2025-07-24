---
title: Custom Processing with Middleware
description: 'Learn how to use middleware to process HTTP requests and responses using the Instructor HTTP client API.'
doctest_case_dir: 'codeblocks/D03_Docs_HTTP'
doctest_case_prefix: 'MiddlewareProcessing_'
doctest_included_types: ['php']
doctest_min_lines: 10
---

While the Instructor HTTP client API provides several built-in middleware components, you'll often need to create custom middleware to handle specific requirements for your application. This chapter explores how to create custom middleware components and use response decoration for advanced processing.

## Creating Custom Middleware

There are three main approaches to creating custom middleware:

1. Implementing the `HttpMiddleware` interface directly
2. Extending the `BaseMiddleware` abstract class
3. Using anonymous classes for simple middleware

### Approach 1: Implementing HttpMiddleware Interface

The most direct approach is to implement the `HttpMiddleware` interface:

```php include="codeblocks/D03_Docs_HTTP/BasicHttpMiddleware/code.php"
```

This approach gives you complete control over the middleware behavior, but it requires you to implement the entire logic from scratch.

### Approach 2: Extending BaseMiddleware

For most cases, extending the `BaseMiddleware` abstract class is more convenient:

```php include="codeblocks/D03_Docs_HTTP/AuthenticationMiddleware/code.php"
```

With `BaseMiddleware`, you only need to override the methods that matter for your middleware:

- `beforeRequest(HttpClientRequest $request): void` - Called before the request is sent
- `afterRequest(HttpClientRequest $request, HttpResponse $response): HttpResponse` - Called after the response is received
- `shouldDecorateResponse(HttpClientRequest $request, HttpResponse $response): bool` - Determines if the response should be decorated
- `toResponse(HttpClientRequest $request, HttpResponse $response): HttpResponse` - Creates a decorated response

### Approach 3: Using Anonymous Classes

For simple middleware that you only need to use once, you can use anonymous classes:

```php
use Cognesy\Http\Contracts\HttpMiddleware;

$client = new HttpClient();

// Add a simple timing middleware
$client->withMiddleware(new class implements HttpMiddleware {
    public function handle(HttpClientRequest $request, CanHandleHttpRequest $next): HttpResponse
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

```php include="codeblocks/D03_Docs_HTTP/RetryMiddleware/code.php"
```

#### Rate Limiting Middleware

This middleware throttles requests to respect API rate limits:

```php include="codeblocks/D03_Docs_HTTP/RateLimitingMiddleware/code.php"
```

#### Caching Middleware

This middleware caches responses for GET requests:

```php include="codeblocks/D03_Docs_HTTP/CachingMiddleware/code.php"
```

## Response Decoration

Response decoration is a powerful technique for wrapping HTTP responses to add functionality or transform data. It's particularly useful for streaming responses, where you need to process each chunk as it arrives.

### Creating a Response Decorator

All response decorators should implement the `HttpResponse` interface. The library provides a `BaseResponseDecorator` class that makes this easier:

```php include="codeblocks/D03_Docs_HTTP/MiddleResponseDecorator/code.php"
```

### Using Response Decorators in Middleware

To use a response decorator, you need to create a middleware that wraps the response:

```php include="codeblocks/D03_Docs_HTTP/MiddlewareStreamDecorator/code.php"
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

use Cognesy\Http\Middleware\Base\BaseResponseDecorator;

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

use Cognesy\Http\Contracts\HttpResponse;use Cognesy\Http\Data\HttpRequest;use Cognesy\Http\Middleware\Base\BaseMiddleware;

class XmlToJsonMiddleware extends BaseMiddleware
{
    protected function shouldDecorateResponse(
        HttpRequest $request,
        HttpResponse $response
    ): bool {
        // Only transform XML responses
        return isset($response->headers()['Content-Type']) &&
               strpos($response->headers()['Content-Type'][0], 'application/xml') !== false;
    }

    protected function toResponse(
        HttpRequest $request,
        HttpResponse $response
    ): HttpResponse {
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

use Cognesy\Http\Contracts\HttpResponse;use Cognesy\Http\Data\HttpRequest;use Cognesy\Http\Middleware\Base\BaseMiddleware;

class AnalyticsMiddleware extends BaseMiddleware
{
    private $analytics;

    public function __construct($analyticsService)
    {
        $this->analytics = $analyticsService;
    }

    protected function beforeRequest(HttpRequest $request): void
    {
        // Record the start time
        $this->startTime = microtime(true);
    }

    protected function afterRequest(
        HttpRequest $request,
        HttpResponse $response
    ): HttpResponse {
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

use Cognesy\Http\Contracts\CanHandleHttpRequest;use Cognesy\Http\Contracts\HttpResponse;use Cognesy\Http\Data\HttpRequest;use Cognesy\Http\Drivers\Mock\MockHttpResponse;use Cognesy\Http\Exceptions\HttpRequestException;use Cognesy\Http\Middleware\Base\BaseMiddleware;

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

    public function handle(HttpRequest $request, CanHandleHttpRequest $next): HttpResponse
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

        } catch (HttpRequestException $e) {
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
use Cognesy\Http\Contracts\HttpResponse;
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

    public function handle(HttpClientRequest $request, CanHandleHttpRequest $next): HttpResponse
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
use Cognesy\Polyglot\Http\Contracts\HttpResponse;
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
        // with the updated headers, as HttpRequest is immutable
    }

    protected function afterRequest(
        HttpRequest $request,
        HttpResponse $response
    ): HttpResponse {
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
use Cognesy\Polyglot\Http\Contracts\HttpResponse;
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

    public function handle(HttpClientRequest $request, CanHandleHttpRequest $next): HttpResponse
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
use Cognesy\Polyglot\Http\Contracts\HttpResponse;
use Cognesy\Polyglot\Http\Data\HttpClientRequest;

class LlmStreamingMiddleware extends BaseMiddleware
{
    protected function shouldDecorateResponse(
        HttpRequest $request,
        HttpResponse $response
    ): bool {
        // Only decorate streaming responses to LLM APIs
        return $request->isStreamed() &&
               strpos($request->url(), 'api.openai.com') !== false;
    }

    protected function toResponse(
        HttpRequest $request,
        HttpResponse $response
    ): HttpResponse {
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
$response = $client->withRequest($request)->get();
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
