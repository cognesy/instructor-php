---
title: Troubleshooting
description: 'Learn how to troubleshoot issues with the Instructor HTTP client API.'
doctest_case_dir: 'codeblocks/D03_Docs_HTTP'
doctest_case_prefix: 'Troubleshooting_'
doctest_included_types: ['php']
doctest_min_lines: 10
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
$request = new HttpRequest(
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
$request = new HttpRequest(
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

    $response = $client->withRequest($request)->get();

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

```php include='D03_Docs_HTTP/Logging/code.php'
```

### Distributed Tracing

For production environments, consider implementing distributed tracing with systems like Jaeger, Zipkin, or OpenTelemetry:

```php include='D03_Docs_HTTP/DistributedTracing/code.php'
```

## Error Handling Strategies

Proper error handling is crucial for building robust applications. Here are some strategies for handling HTTP errors effectively.

### Basic Error Handling

The simplest approach is to catch the `RequestException`:

```php
use Cognesy\Http\Exceptions\HttpRequestException;

try {
    $response = $client->withRequest($request)->get();
    // Process successful response
} catch (HttpRequestException $e) {
    // Handle error
    echo "Request failed: {$e->getMessage()}\n";
}
```

### Categorizing Errors

You can categorize errors based on the underlying exception or status code:

```php include='D03_Docs_HTTP/ErrorCategorization/code.php'
```

### Implementing Retry Logic

For transient errors, implement retry logic:

```php include='D03_Docs_HTTP/RetryLogic/code.php'
```

### Circuit Breaker Pattern

For critical services, implement a circuit breaker to prevent cascading failures:

```php include='D03_Docs_HTTP/CircuitBreaker/code.php'
```

### Graceful Degradation

When a service is unavailable, implement graceful degradation by providing fallback functionality:

```php include='D03_Docs_HTTP/GracefulDegradation/code.php'
```

### Comprehensive Error Handling Example

Here's a comprehensive example that combines multiple error handling strategies:

```php include='D03_Docs_HTTP/ErrorHandling/code.php'
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
$request = new HttpRequest(
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
$request = new HttpRequest(
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

```php include='D03_Docs_HTTP/EventDispatching/code.php'
```

### Manual Debugging

You can implement your own debugging by adding logging statements:

```php include='D03_Docs_HTTP/ManualDebugging/code.php'
```

### Record/Replay Middleware for Debugging

The `RecordReplayMiddleware` can be useful for debugging by recording HTTP interactions and replaying them later:

```php include='D03_Docs_HTTP/RecordReplay/code.php'
```

## Logging and Tracing

Implementing proper logging and tracing is essential for troubleshooting HTTP issues, especially in production environments.

### Request/Response Logging

Create a custom logging middleware:

```php include='D03_Docs_HTTP/Logging/code.php'
```
