---
title: Troubleshooting
description: 'Learn how to troubleshoot issues with the Instructor HTTP client API.'
---

Even with a well-designed API, you may encounter issues when working with HTTP requests and responses. This chapter covers common problems, debugging techniques, and error handling strategies for the Instructor HTTP client API.

## Common Issues

Here are some common issues you might encounter when using the Instructor HTTP client API, along with their solutions:

### Connection Issues

**Symptom**: Requests fail with connection errors or timeouts.

**Possible Causes and Solutions**:

1. **Network Connectivity Issues**:
- Verify that your server has internet connectivity
- Check if the target API is accessible from your server (try ping or telnet)
- Ensure any required VPN connections are active

2. **DNS Issues**:
- Verify that DNS resolution is working correctly
- Try using an IP address instead of a hostname to bypass DNS

3. **Firewall Blocking**:
- Check if a firewall is blocking outgoing connections
- Verify that the required ports (usually 80 and 443) are open

4. **Proxy Configuration**:
- If you're using a proxy, ensure it's correctly configured
- Check proxy credentials if authentication is required

5. **SSL/TLS Issues**:
- Verify that the server's SSL certificate is valid
- Check if your server trusts the certificate authority
- Update your CA certificates if needed

### Timeout Issues

**Symptom**: Requests time out before completing.

**Possible Causes and Solutions**:

1. **Connection Timeout Too Short**:
- Increase the `connectTimeout` setting in your client configuration
```php
$config = new HttpClientConfig(
    connectTimeout: 10, // Increase from default
    // Other settings...
);
$client->withConfig($config);
   ```

2. **Request Timeout Too Short**:
- Increase the `requestTimeout` setting for long-running operations
```php
$config = new HttpClientConfig(
    requestTimeout: 60, // Increase from default
    // Other settings...
);
$client->withConfig($config);
   ```

3. **Idle Timeout Issues with Streaming**:
- For streaming APIs, increase or disable the `idleTimeout`
```php
$config = new HttpClientConfig(
    idleTimeout: -1, // Disable idle timeout
    // Other settings...
);
$client->withConfig($config);
   ```

4. **Server is Slow to Respond**:
- If the target server is known to be slow, adjust your timeouts accordingly
- Consider implementing a retry mechanism for intermittent issues

### Authentication Issues

**Symptom**: Requests fail with 401 Unauthorized or 403 Forbidden responses.

**Possible Causes and Solutions**:

1. **Invalid Credentials**:
- Verify that your API keys or tokens are correct
- Check if the credentials have expired or been revoked
- Ensure you're using the correct authentication method

2. **Missing Authorization Headers**:
- Check that you're adding the correct Authorization header
```php
$request = new HttpClientRequest(
    // ...
    headers: [
        'Authorization' => 'Bearer ' . $apiToken,
        // Other headers...
    ],
    // ...
);
   ```

3. **Incorrect Authentication Format**:
- Verify the format required by the API (Bearer, Basic, etc.)
- For Basic Auth, ensure credentials are properly base64-encoded

4. **Rate Limiting or IP Restrictions**:
- Check if you've exceeded rate limits
- Verify that your server's IP is allowed to access the API

### Request Format Issues

**Symptom**: Requests fail with 400 Bad Request or 422 Unprocessable Entity responses.

**Possible Causes and Solutions**:

1. **Incorrect Content-Type**:
- Ensure you're setting the correct Content-Type header
```php
$request = new HttpClientRequest(
    // ...
    headers: [
        'Content-Type' => 'application/json',
        // Other headers...
    ],
    // ...
);
   ```

2. **Malformed Request Body**:
- Validate your request body against the API's schema
- Check for typos in field names or incorrect data types
- Use a tool like Postman to test the request format

3. **Missing Required Fields**:
- Ensure all required fields are included in the request
- Check the API documentation for required vs. optional fields

4. **Validation Errors**:
- Read the error messages in the response for specific validation issues
- Fix each validation error according to the API's requirements

### Middleware Issues

**Symptom**: Unexpected behavior when using middleware.

**Possible Causes and Solutions**:

1. **Middleware Order Issues**:
- Remember that middleware is executed in the order it's added
- Rearrange middleware to ensure proper execution order
```php
$client->middleware()->clear(); // Clear existing middleware
$client->withMiddleware(
    new AuthenticationMiddleware($apiKey), // First
    new LoggingMiddleware(), // Second
    new RetryMiddleware() // Third
);
   ```

2. **Middleware Not Executing**:
- Verify that the middleware is actually added to the stack
- Check for conditional logic in your middleware that might be skipping execution

3. **Middleware Changing Request/Response**:
- Be aware that middleware can modify requests and responses
- Debug by logging the request/response before and after each middleware

4. **Middleware Exceptions**:
- Exceptions in middleware can disrupt the entire chain
- Add proper error handling in your middleware

## Debugging Tools

The Instructor HTTP client API provides several tools to help you debug HTTP requests and responses.

### Debug Middleware

The `DebugMiddleware` is the primary tool for debugging HTTP interactions:

```php
use Cognesy\Http\HttpClient;

// Method 1: Using the withDebug convenience method
$client = new HttpClient();
$client->withDebugPreset('on');

// Method 2: Enable debug in configuration
$config = [
    'http' => [
        'enabled' => true,
        // Other debug settings...
    ],
];
```

You can configure which aspects of HTTP interactions to log in the `config/debug.php` file:

```php
return [
    'http' => [
        'enabled' => true,           // Master switch
        'trace' => false,            // Dump HTTP trace information
        'requestUrl' => true,        // Dump request URL
        'requestHeaders' => true,    // Dump request headers
        'requestBody' => true,       // Dump request body
        'responseHeaders' => true,   // Dump response headers
        'responseBody' => true,      // Dump response body
        'responseStream' => true,    // Dump streaming data
        'responseStreamByLine' => true, // Format stream as lines
    ],
];
```

### Event Dispatching

The HTTP client dispatches events at key points in the request lifecycle:

```php
use Cognesy\Events\Dispatchers\EventDispatcher;use Cognesy\Http\Events\HttpRequestFailed;use Cognesy\Http\Events\HttpRequestSent;use Cognesy\Http\Events\HttpResponseReceived;

// Create an event dispatcher with custom listeners
$events = new EventDispatcher();

// Listen for outgoing requests
$events->listen(HttpRequestSent::class, function ($event) {
    echo "Sending {$event->method} request to {$event->url}\n";
    echo "Headers: " . json_encode($event->headers) . "\n";
    echo "Body: " . json_encode($event->body) . "\n";
});

// Listen for incoming responses
$events->listen(HttpResponseReceived::class, function ($event) {
    echo "Received response with status code: {$event->statusCode}\n";
});

// Listen for request failures
$events->listen(HttpRequestFailed::class, function ($event) {
    echo "Request failed: {$event->errors}\n";
    echo "URL: {$event->url}, Method: {$event->method}\n";
});

// Create a client with this event dispatcher
$client = new HttpClient('', $events);
```

### Manual Debugging

You can implement your own debugging by adding logging statements:

```php
try {
    echo "Sending request to: {$request->url()}\n";
    echo "Headers: " . json_encode($request->headers()) . "\n";
    echo "Body: " . $request->body()->toString() . "\n";

    $response = $client->handle($request);

    echo "Response status: {$response->statusCode()}\n";
    echo "Response headers: " . json_encode($response->headers()) . "\n";
    echo "Response body: {$response->body()}\n";
} catch (RequestException $e) {
    echo "Error: {$e->getMessage()}\n";
    if ($e->getPrevious()) {
        echo "Original error: {$e->getPrevious()->getMessage()}\n";
    }
}
```

### Record/Replay Middleware for Debugging

The `RecordReplayMiddleware` can be useful for debugging by recording HTTP interactions and replaying them later:

```php
use Cognesy\Http\Middleware\RecordReplay\RecordReplayMiddleware;

// Record all HTTP interactions to a directory
$recordReplayMiddleware = new RecordReplayMiddleware(
    mode: RecordReplayMiddleware::MODE_RECORD,
    storageDir: __DIR__ . '/debug_recordings',
    fallbackToRealRequests: true
);

$client->withMiddleware($recordReplayMiddleware);

// Make your requests...

// Later, you can inspect the recorded files to see what was sent/received
```

## Logging and Tracing

Implementing proper logging and tracing is essential for troubleshooting HTTP issues, especially in production environments.

### Request/Response Logging

Create a custom logging middleware:

```php
<?php

namespace YourNamespace\Http\Middleware;

use Cognesy\Http\Contracts\HttpResponse;use Cognesy\Http\Data\HttpRequest;use Cognesy\Http\Middleware\Base\BaseMiddleware;use Psr\Log\LoggerInterface;

class DetailedLoggingMiddleware extends BaseMiddleware
{
    private LoggerInterface $logger;
    private array $startTimes = [];

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    protected function beforeRequest(HttpRequest $request): void
    {
        $requestId = bin2hex(random_bytes(8));
        $this->startTimes[$requestId] = microtime(true);

        $context = [
            'request_id' => $requestId,
            'url' => $request->url(),
            'method' => $request->method(),
            'headers' => $request->headers(),
        ];

        // Only log the body for non-binary content
        $contentType = $request->headers()['Content-Type'] ?? '';
        $contentType = is_array($contentType) ? ($contentType[0] ?? '') : $contentType;

        if (strpos($contentType, 'application/json') !== false ||
            strpos($contentType, 'text/') === 0) {
            $context['body'] = $request->body()->toString();
        }

        $this->logger->info("HTTP Request: {$request->method()} {$request->url()}", $context);

        // Store the request ID for use in afterRequest
        $request->{__CLASS__} = $requestId;
    }

    protected function afterRequest(
        HttpRequest $request,
        HttpResponse $response
    ): HttpResponse {
        $requestId = $request->{__CLASS__} ?? 'unknown';
        $duration = 0;

        if (isset($this->startTimes[$requestId])) {
            $duration = round((microtime(true) - $this->startTimes[$requestId]) * 1000, 2);
            unset($this->startTimes[$requestId]);
        }

        $context = [
            'request_id' => $requestId,
            'status_code' => $response->statusCode(),
            'headers' => $response->headers(),
            'duration_ms' => $duration,
        ];

        // Only log the body for non-binary content and reasonable sizes
        $contentType = $response->headers()['Content-Type'] ?? '';
        $contentType = is_array($contentType) ? ($contentType[0] ?? '') : $contentType;

        if ((strpos($contentType, 'application/json') !== false ||
             strpos($contentType, 'text/') === 0) &&
            strlen($response->body()) < 10000) {
            $context['body'] = $response->body();
        }

        $logLevel = $response->statusCode() >= 400 ? 'error' : 'info';
        $this->logger->log(
            $logLevel,
            "HTTP Response: {$response->statusCode()} from {$request->method()} {$request->url()} ({$duration}ms)",
            $context
        );

        return $response;
    }
}
```

### Distributed Tracing

For production environments, consider implementing distributed tracing with systems like Jaeger, Zipkin, or OpenTelemetry:

```php
<?php

namespace YourNamespace\Http\Middleware;

use Cognesy\Http\Contracts\HttpResponse;use Cognesy\Http\Data\HttpRequest;use Cognesy\Http\Middleware\Base\BaseMiddleware;use OpenTelemetry\API\Trace\SpanKind;use OpenTelemetry\API\Trace\TracerInterface;

class OpenTelemetryMiddleware extends BaseMiddleware
{
    private TracerInterface $tracer;

    public function __construct(TracerInterface $tracer)
    {
        $this->tracer = $tracer;
    }

    public function handle(HttpRequest $request, CanHandleHttpRequest $next): HttpResponse
    {
        $url = parse_url($request->url());
        $path = $url['path'] ?? '/';
        $host = $url['host'] ?? 'unknown';

        // Create a span for this operation
        $span = $this->tracer->spanBuilder($request->method() . ' ' . $path)
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->startSpan();

        $scope = $span->activate();

        try {
            // Add attributes to the span
            $span->setAttribute('http.method', $request->method());
            $span->setAttribute('http.url', $request->url());
            $span->setAttribute('http.host', $host);
            $span->setAttribute('http.path', $path);

            // Make the actual request
            $response = $next->handle($request);

            // Record response information
            $span->setAttribute('http.status_code', $response->statusCode());

            // Set status based on response
            if ($response->statusCode() >= 400) {
                $span->setStatus(\OpenTelemetry\API\Trace\StatusCode::ERROR, "HTTP {$response->statusCode()}");
            }

            return $response;
        } catch (\Exception $e) {
            // Record the exception
            $span->recordException($e);
            $span->setStatus(\OpenTelemetry\API\Trace\StatusCode::ERROR, $e->getMessage());

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

## Error Handling Strategies

Proper error handling is crucial for building robust applications. Here are some strategies for handling HTTP errors effectively.

### Basic Error Handling

The simplest approach is to catch the `RequestException`:

```php
use Cognesy\Http\Exceptions\HttpRequestException;

try {
    $response = $client->handle($request);
    // Process successful response
} catch (HttpRequestException $e) {
    // Handle error
    echo "Request failed: {$e->getMessage()}\n";
}
```

### Categorizing Errors

You can categorize errors based on the underlying exception or status code:

```php
try {
    $response = $client->handle($request);

    // Check for error responses
    if ($response->statusCode() >= 400) {
        $this->handleErrorResponse($response);
        return;
    }

    // Process successful response
    $this->processResponse($response);

} catch (RequestException $e) {
    $previous = $e->getPrevious();

    if ($previous instanceof \GuzzleHttp\Exception\ConnectException ||
        $previous instanceof \Symfony\Component\HttpClient\Exception\TransportException) {
        // Handle connection errors
        $this->handleConnectionError($e);
    } elseif ($previous instanceof \GuzzleHttp\Exception\RequestException ||
              $previous instanceof \Symfony\Component\HttpClient\Exception\HttpExceptionInterface) {
        // Handle HTTP protocol errors
        $this->handleHttpError($e);
    } else {
        // Handle other exceptions
        $this->handleUnexpectedError($e);
    }
}
```

### Implementing Retry Logic

For transient errors, implement retry logic:

```php
function retryRequest($client, $request, $maxRetries = 3): HttpClientResponse {
    $attempts = 0;
    $lastException = null;

    $shouldRetry = function ($exception) {
        // Only retry on connection issues and certain status codes
        $retryStatusCodes = [429, 500, 502, 503, 504];

        if ($exception instanceof RequestException) {
            $previous = $exception->getPrevious();

            // Retry on connection errors
            if ($previous instanceof \GuzzleHttp\Exception\ConnectException ||
                $previous instanceof \Symfony\Component\HttpClient\Exception\TransportException) {
                return true;
            }

            // Check for specific HTTP status codes
            $response = $exception->getResponse();
            if ($response && in_array($response->getStatusCode(), $retryStatusCodes)) {
                return true;
            }
        }

        return false;
    };

    while ($attempts < $maxRetries) {
        try {
            return $client->handle($request);
        } catch (RequestException $e) {
            $lastException = $e;
            $attempts++;

            if (!$shouldRetry($e) || $attempts >= $maxRetries) {
                throw $e;
            }

            // Exponential backoff with jitter
            $sleepTime = (2 ** $attempts) + rand(0, 1000) / 1000;
            sleep($sleepTime);

            error_log("Retry attempt $attempts after error: {$e->getMessage()}");
        }
    }

    throw $lastException; // Should never reach here, but just in case
}
```

### Circuit Breaker Pattern

For critical services, implement a circuit breaker to prevent cascading failures:

```php
class CircuitBreaker {
    private $state = 'CLOSED';
    private $failures = 0;
    private $threshold = 5;
    private $timeout = 60;
    private $lastFailureTime = 0;

    public function execute(callable $operation) {
        if ($this->state === 'OPEN') {
            // Check if timeout has elapsed and we should try again
            if (time() - $this->lastFailureTime >= $this->timeout) {
                $this->state = 'HALF_OPEN';
            } else {
                throw new \RuntimeException('Circuit is open');
            }
        }

        try {
            $result = $operation();

            // Reset on success
            if ($this->state === 'HALF_OPEN') {
                $this->reset();
            }

            return $result;

        } catch (\Exception $e) {
            $this->failures++;
            $this->lastFailureTime = time();

            // Open the circuit if we hit the threshold
            if ($this->failures >= $this->threshold || $this->state === 'HALF_OPEN') {
                $this->state = 'OPEN';
            }

            throw $e;
        }
    }

    public function reset() {
        $this->state = 'CLOSED';
        $this->failures = 0;
    }
}

// Usage
$circuitBreaker = new CircuitBreaker();

try {
    $response = $circuitBreaker->execute(function() use ($client, $request) {
        return $client->handle($request);
    });

    // Process response
} catch (\RuntimeException $e) {
    if ($e->getMessage() === 'Circuit is open') {
        // Handle circuit open
        return $fallbackResponse;
    }

    // Handle other exceptions
    throw $e;
}
```

### Graceful Degradation

When a service is unavailable, implement graceful degradation by providing fallback functionality:

```php
function getUserData($userId, $client) {
    $request = new HttpClientRequest(
        url: "https://api.example.com/users/{$userId}",
        method: 'GET',
        headers: ['Accept' => 'application/json'],
        body: [],
        options: []
    );

    try {
        $response = $client->handle($request);
        return json_decode($response->body(), true);
    } catch (RequestException $e) {
        // Log the error
        error_log("Failed to get user data: {$e->getMessage()}");

        // Return cached data if available
        $cachedData = $this->cache->get("user_{$userId}");
        if ($cachedData) {
            return $cachedData;
        }

        // Return minimal user data
        return [
            'id' => $userId,
            'name' => 'Unknown User',
            'is_fallback' => true,
        ];
    }
}
```

### Comprehensive Error Handling Example

Here's a comprehensive example that combines multiple error handling strategies:

```php
<?php

namespace YourNamespace;

use Cognesy\Http\HttpClient;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Exceptions\HttpRequestException;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;

class ApiService {
    private HttpClient $client;
    private LoggerInterface $logger;
    private CacheInterface $cache;
    private array $circuitBreakers = [];

    public function __construct(
        HttpClient $client,
        LoggerInterface $logger,
        CacheInterface $cache
    ) {
        $this->client = $client;
        $this->logger = $logger;
        $this->cache = $cache;
    }

    public function fetchData(string $endpoint, array $params = []): array {
        $url = "https://api.example.com/{$endpoint}";

        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $request = new HttpRequest(
            url: $url,
            method: 'GET',
            headers: [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $this->getApiToken(),
            ],
            body: [],
            options: []
        );

        // Get or create a circuit breaker for this host
        $host = parse_url($url, PHP_URL_HOST);
        if (!isset($this->circuitBreakers[$host])) {
            $this->circuitBreakers[$host] = [
                'state' => 'CLOSED',
                'failures' => 0,
                'lastFailure' => 0,
                'threshold' => 5,
                'timeout' => 60,
            ];
        }

        $circuitBreaker = &$this->circuitBreakers[$host];

        // Check circuit state
        if ($circuitBreaker['state'] === 'OPEN') {
            if (time() - $circuitBreaker['lastFailure'] > $circuitBreaker['timeout']) {
                // Try again after timeout
                $circuitBreaker['state'] = 'HALF_OPEN';
                $this->logger->info("Circuit for {$host} is now half-open");
            } else {
                $this->logger->warning("Circuit for {$host} is open, using fallback");
                return $this->getFallbackData($endpoint, $params);
            }
        }

        // Try to get from cache first if it's a GET request
        $cacheKey = 'api_' . md5($url);
        $cachedData = $this->cache->get($cacheKey);

        if ($cachedData) {
            $this->logger->info("Cache hit for {$url}");
            return $cachedData;
        }

        // Attempt the request with retries
        $attempts = 0;
        $maxAttempts = 3;

        while ($attempts < $maxAttempts) {
            try {
                $this->logger->info("Attempting request to {$url}", [
                    'attempt' => $attempts + 1,
                    'max_attempts' => $maxAttempts,
                ]);

                $response = $this->client->withRequest($request)->get();

                // If we got here in HALF_OPEN state, reset the circuit
                if ($circuitBreaker['state'] === 'HALF_OPEN') {
                    $circuitBreaker['state'] = 'CLOSED';
                    $circuitBreaker['failures'] = 0;
                    $this->logger->info("Circuit for {$host} is now closed");
                }

                // Process the response
                if ($response->statusCode() >= 200 && $response->statusCode() < 300) {
                    $data = json_decode($response->body(), true);

                    // Cache successful GET responses
                    if ($request->method() === 'GET') {
                        $this->cache->set($cacheKey, $data, 300); // 5 minutes
                    }

                    return $data;
                } else {
                    // Handle error responses
                    $error = json_decode($response->body(), true)['error'] ?? 'Unknown error';
                    $this->logger->error("API error: {$error}", [
                        'status_code' => $response->statusCode(),
                        'url' => $url,
                    ]);

                    // Record failure for circuit breaker
                    $this->recordFailure($circuitBreaker);

                    if ($response->statusCode() >= 500) {
                        // Retry server errors
                        $attempts++;
                        if ($attempts < $maxAttempts) {
                            $sleepTime = 2 ** $attempts;
                            $this->logger->info("Will retry in {$sleepTime} seconds");
                            sleep($sleepTime);
                            continue;
                        }
                    }

                    // Client errors or max retries reached
                    return $this->handleErrorResponse($response, $endpoint, $params);
                }
            } catch (HttpRequestException $e) {
                $this->logger->error("Request exception: {$e->getMessage()}", [
                    'url' => $url,
                    'attempt' => $attempts + 1,
                ]);

                // Record failure for circuit breaker
                $this->recordFailure($circuitBreaker);

                // Retry on connection errors
                $attempts++;
                if ($attempts < $maxAttempts) {
                    $sleepTime = 2 ** $attempts;
                    $this->logger->info("Will retry in {$sleepTime} seconds");
                    sleep($sleepTime);
                } else {
                    // Max retries reached, use fallback
                    return $this->getFallbackData($endpoint, $params);
                }
            }
        }

        // Should never reach here, but just in case
        return $this->getFallbackData($endpoint, $params);
    }

    private function recordFailure(array &$circuitBreaker): void {
        $circuitBreaker['failures']++;
        $circuitBreaker['lastFailure'] = time();

        if ($circuitBreaker['failures'] >= $circuitBreaker['threshold'] ||
            $circuitBreaker['state'] === 'HALF_OPEN') {
            $circuitBreaker['state'] = 'OPEN';
            $this->logger->warning("Circuit is now open due to {$circuitBreaker['failures']} failures");
        }
    }

    private function getFallbackData(string $endpoint, array $params): array {
        $this->logger->info("Using fallback data for {$endpoint}");

        // Try to get stale cached data
        $url = "https://api.example.com/{$endpoint}";
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $cacheKey = 'api_' . md5($url);
        $cachedData = $this->cache->get($cacheKey . '_stale');

        if ($cachedData) {
            return array_merge($cachedData, ['_is_stale' => true]);
        }

        // Provide minimal fallback data
        return [
            'success' => false,
            'error' => 'Service unavailable',
            '_is_fallback' => true,
        ];
    }

    private function handleErrorResponse(HttpClientResponse $response, string $endpoint, array $params): array {
        $statusCode = $response->statusCode();
        $body = json_decode($response->body(), true);

        // Different handling based on status code
        switch ($statusCode) {
            case 400:
                return [
                    'success' => false,
                    'error' => 'Bad request',
                    'details' => $body['error'] ?? 'Invalid request parameters',
                ];

            case 401:
            case 403:
                // Authentication/authorization error
                $this->refreshToken(); // Try to refresh token for next request
                return [
                    'success' => false,
                    'error' => 'Authentication failed',
                    'details' => $body['error'] ?? 'Please log in again',
                ];

            case 404:
                return [
                    'success' => false,
                    'error' => 'Not found',
                    'details' => "The requested {$endpoint} could not be found",
                ];

            case 429:
                // Rate limit exceeded
                $retryAfter = $response->headers()['Retry-After'][0] ?? 60;
                return [
                    'success' => false,
                    'error' => 'Rate limit exceeded',
                    'retry_after' => $retryAfter,
                ];

            default:
                // Use fallback for other errors
                return $this->getFallbackData($endpoint, $params);
        }
    }

    private function getApiToken(): string {
        // Implementation to get or refresh API token
        return 'your-api-token';
    }

    private function refreshToken(): void {
        // Implementation to refresh the token
    }
}
```

This comprehensive approach combines:
- Circuit breaker pattern
- Caching and fallbacks
- Retry logic with exponential backoff
- Status-code specific error handling
- Logging for troubleshooting

By implementing these patterns, your application can be more resilient to API failures and provide a better user experience even when external services are unavailable.
Client API.

## Common Issues

Here are some common issues you might encounter when using the Instructor HTTP client API, along with their solutions:

### Connection Issues

**Symptom**: Requests fail with connection errors or timeouts.

**Possible Causes and Solutions**:

1. **Network Connectivity Issues**:
- Verify that your server has internet connectivity
- Check if the target API is accessible from your server (try ping or telnet)
- Ensure any required VPN connections are active

2. **DNS Issues**:
- Verify that DNS resolution is working correctly
- Try using an IP address instead of a hostname to bypass DNS

3. **Firewall Blocking**:
- Check if a firewall is blocking outgoing connections
- Verify that the required ports (usually 80 and 443) are open

4. **Proxy Configuration**:
- If you're using a proxy, ensure it's correctly configured
- Check proxy credentials if authentication is required

5. **SSL/TLS Issues**:
- Verify that the server's SSL certificate is valid
- Check if your server trusts the certificate authority
- Update your CA certificates if needed

### Timeout Issues

**Symptom**: Requests time out before completing.

**Possible Causes and Solutions**:

1. **Connection Timeout Too Short**:
- Increase the `connectTimeout` setting in your client configuration
```php
$config = new HttpClientConfig(
    connectTimeout: 10, // Increase from default
    // Other settings...
);
$client->withConfig($config);
   ```

2. **Request Timeout Too Short**:
- Increase the `requestTimeout` setting for long-running operations
```php
$config = new HttpClientConfig(
    requestTimeout: 60, // Increase from default
    // Other settings...
);
$client->withConfig($config);
   ```

3. **Idle Timeout Issues with Streaming**:
- For streaming APIs, increase or disable the `idleTimeout`
```php
$config = new HttpClientConfig(
    idleTimeout: -1, // Disable idle timeout
    // Other settings...
);
$client->withConfig($config);
   ```

4. **Server is Slow to Respond**:
- If the target server is known to be slow, adjust your timeouts accordingly
- Consider implementing a retry mechanism for intermittent issues

### Authentication Issues

**Symptom**: Requests fail with 401 Unauthorized or 403 Forbidden responses.

**Possible Causes and Solutions**:

1. **Invalid Credentials**:
- Verify that your API keys or tokens are correct
- Check if the credentials have expired or been revoked
- Ensure you're using the correct authentication method

2. **Missing Authorization Headers**:
- Check that you're adding the correct Authorization header
```php
$request = new HttpClientRequest(
    // ...
    headers: [
        'Authorization' => 'Bearer ' . $apiToken,
        // Other headers...
    ],
    // ...
);
   ```

3. **Incorrect Authentication Format**:
- Verify the format required by the API (Bearer, Basic, etc.)
- For Basic Auth, ensure credentials are properly base64-encoded

4. **Rate Limiting or IP Restrictions**:
- Check if you've exceeded rate limits
- Verify that your server's IP is allowed to access the API

### Request Format Issues

**Symptom**: Requests fail with 400 Bad Request or 422 Unprocessable Entity responses.

**Possible Causes and Solutions**:

1. **Incorrect Content-Type**:
- Ensure you're setting the correct Content-Type header
```php
$request = new HttpClientRequest(
    // ...
    headers: [
        'Content-Type' => 'application/json',
        // Other headers...
    ],
    // ...
);
   ```

2. **Malformed Request Body**:
- Validate your request body against the API's schema
- Check for typos in field names or incorrect data types
- Use a tool like Postman to test the request format

3. **Missing Required Fields**:
- Ensure all required fields are included in the request
- Check the API documentation for required vs. optional fields

4. **Validation Errors**:
- Read the error messages in the response for specific validation issues
- Fix each validation error according to the API's requirements

### Middleware Issues

**Symptom**: Unexpected behavior when using middleware.

**Possible Causes and Solutions**:

1. **Middleware Order Issues**:
- Remember that middleware is executed in the order it's added
- Rearrange middleware to ensure proper execution order
```php
$client->middleware()->clear(); // Clear existing middleware
$client->withMiddleware(
    new AuthenticationMiddleware($apiKey), // First
    new LoggingMiddleware(), // Second
    new RetryMiddleware() // Third
);
   ```

2. **Middleware Not Executing**:
- Verify that the middleware is actually added to the stack
- Check for conditional logic in your middleware that might be skipping execution

3. **Middleware Changing Request/Response**:
- Be aware that middleware can modify requests and responses
- Debug by logging the request/response before and after each middleware

4. **Middleware Exceptions**:
- Exceptions in middleware can disrupt the entire chain
- Add proper error handling in your middleware

## Debugging Tools

The Instructor HTTP client API provides several tools to help you debug HTTP requests and responses.

### Debug Middleware

The `DebugMiddleware` is the primary tool for debugging HTTP interactions:

```php
use Cognesy\Http\HttpClient;

// Method 1: Using the withDebug convenience method
$client = new HttpClient();
$client->withDebugPreset('on');

// Method 2: Enable debug in configuration
$config = [
    'http' => [
        'enabled' => true,
        // Other debug settings...
    ],
];
```

You can configure which aspects of HTTP interactions to log in the `config/debug.php` file:

```php
return [
    'http' => [
        'enabled' => true,           // Master switch
        'trace' => false,            // Dump HTTP trace information
        'requestUrl' => true,        // Dump request URL
        'requestHeaders' => true,    // Dump request headers
        'requestBody' => true,       // Dump request body
        'responseHeaders' => true,   // Dump response headers
        'responseBody' => true,      // Dump response body
        'responseStream' => true,    // Dump streaming data
        'responseStreamByLine' => true, // Format stream as lines
    ],
];
```

### Event Dispatching

The HTTP client dispatches events at key points in the request lifecycle:

```php
use Cognesy\Events\Dispatchers\EventDispatcher;use Cognesy\Http\Events\HttpRequestFailed;use Cognesy\Http\Events\HttpRequestSent;use Cognesy\Http\Events\HttpResponseReceived;

// Create an event dispatcher with custom listeners
$events = new EventDispatcher();

// Listen for outgoing requests
$events->listen(HttpRequestSent::class, function ($event) {
    echo "Sending {$event->method} request to {$event->url}\n";
    echo "Headers: " . json_encode($event->headers) . "\n";
    echo "Body: " . json_encode($event->body) . "\n";
});

// Listen for incoming responses
$events->listen(HttpResponseReceived::class, function ($event) {
    echo "Received response with status code: {$event->statusCode}\n";
});

// Listen for request failures
$events->listen(HttpRequestFailed::class, function ($event) {
    echo "Request failed: {$event->errors}\n";
    echo "URL: {$event->url}, Method: {$event->method}\n";
});

// Create a client with this event dispatcher
$client = new HttpClient('', $events);
```

### Manual Debugging

You can implement your own debugging by adding logging statements:

```php
try {
    echo "Sending request to: {$request->url()}\n";
    echo "Headers: " . json_encode($request->headers()) . "\n";
    echo "Body: " . $request->body()->toString() . "\n";

    $response = $client->handle($request);

    echo "Response status: {$response->statusCode()}\n";
    echo "Response headers: " . json_encode($response->headers()) . "\n";
    echo "Response body: {$response->body()}\n";
} catch (RequestException $e) {
    echo "Error: {$e->getMessage()}\n";
    if ($e->getPrevious()) {
        echo "Original error: {$e->getPrevious()->getMessage()}\n";
    }
}
```

### Record/Replay Middleware for Debugging

The `RecordReplayMiddleware` can be useful for debugging by recording HTTP interactions and replaying them later:

```php
use Cognesy\Http\Middleware\RecordReplay\RecordReplayMiddleware;

// Record all HTTP interactions to a directory
$recordReplayMiddleware = new RecordReplayMiddleware(
    mode: RecordReplayMiddleware::MODE_RECORD,
    storageDir: __DIR__ . '/debug_recordings',
    fallbackToRealRequests: true
);

$client->withMiddleware($recordReplayMiddleware);

// Make your requests...

// Later, you can inspect the recorded files to see what was sent/received
```

## Logging and Tracing

Implementing proper logging and tracing is essential for troubleshooting HTTP issues, especially in production environments.

### Request/Response Logging

Create a custom logging middleware:

```php
<?php

namespace YourNamespace\Http\Middleware;

use Cognesy\Http\Contracts\HttpResponse;use Cognesy\Http\Data\HttpRequest;use Cognesy\Http\Middleware\Base\BaseMiddleware;use Psr\Log\LoggerInterface;

class DetailedLoggingMiddleware extends BaseMiddleware
{
    private LoggerInterface $logger;
    private array $startTimes = [];

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    protected function beforeRequest(HttpRequest $request): void
    {
        $requestId = bin2hex(random_bytes(8));
        $this->startTimes[$requestId] = microtime(true);

        $context = [
            'request_id' => $requestId,
            'url' => $request->url(),
            'method' => $request->method(),
            'headers' => $request->headers(),
        ];

        // Only log the body for non-binary content
        $contentType = $request->headers()['Content-Type'] ?? '';
        $contentType = is_array($contentType) ? ($contentType[0] ?? '') : $contentType;

        if (strpos($contentType, 'application/json') !== false ||
            strpos($contentType, 'text/') === 0) {
            $context['body'] = $request->body()->toString();
        }

        $this->logger->info("HTTP Request: {$request->method()} {$request->url()}", $context);

        // Store the request ID for use in afterRequest
        $request->{__CLASS__} = $requestId;
    }

    protected function afterRequest(
        HttpRequest $request,
        HttpResponse $response
    ): HttpResponse {
        $requestId = $request->{__CLASS__} ?? 'unknown';
        $duration = 0;

        if (isset($this->startTimes[$requestId])) {
            $duration = round((microtime(true) - $this->startTimes[$requestId]) * 1000, 2);
            unset($this->startTimes[$requestId]);
        }

        $context = [
            'request_id' => $requestId,
            'status_code' => $response->statusCode(),
            'headers' => $response->headers(),
            'duration_ms' => $duration,
        ];

        // Only log the body for non-binary content and reasonable sizes
        $contentType = $response->headers()['Content-Type'] ?? '';
        $contentType = is_array($contentType) ? ($contentType[0] ?? '') : $contentType;

        if ((strpos($contentType, 'application/json') !== false ||
             strpos($contentType, 'text/') === 0) &&
            strlen($response->body()) < 10000) {
            $context['body'] = $response->body();
        }

        $logLevel = $response->statusCode() >= 400 ? 'error' : 'info';
        $this->logger->log(
            $logLevel,
            "HTTP Response: {$response->statusCode()} from {$request->method()} {$request->url()} ({$duration}ms)",
            $context
        );

        return $response;
    }
}
```

