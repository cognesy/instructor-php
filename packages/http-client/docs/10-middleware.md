---
title: Middleware
description: 'Learn how to use middleware in the Instructor HTTP client API.'
doctest_case_dir: 'codeblocks/D03_Docs_HTTP'
doctest_case_prefix: 'Middleware_'
doctest_included_types: ['php']
doctest_min_lines: 10
---

Middleware is one of the most powerful features of the Instructor HTTP client API. It allows you to intercept and modify HTTP requests and responses, add functionality to the HTTP client, and create reusable components that can be applied across different applications.

## Middleware Concept

Middleware in the Instructor HTTP client API follows the pipeline pattern, where each middleware component gets a chance to process the request before it's sent and the response after it's received.

The middleware chain works like this:

1. Your application creates a request
2. The request passes through each middleware (in the order they were added)
3. The last middleware passes the request to the HTTP driver
4. The driver sends the request to the server and receives a response
5. The response passes back through each middleware (in reverse order)
6. Your application receives the final response

This bidirectional flow allows middleware to perform operations both before the request is sent and after the response is received.

### The HttpMiddleware Interface

All middleware components must implement the `HttpMiddleware` interface:

```php
interface HttpMiddleware
{
    public function handle(HttpClientRequest $request, CanHandleHttpRequest $next): HttpResponse;
}
```

The `handle` method takes two parameters:
- `$request`: The HTTP request to process
- `$next`: The next handler in the middleware chain

The middleware can:
- Modify the request before passing it to the next handler
- Short-circuit the chain by returning a response without calling the next handler
- Process the response from the next handler before returning it
- Wrap the response in a decorator for further processing (especially useful for streaming responses)

### The BaseMiddleware Abstract Class

While you can implement the `HttpMiddleware` interface directly, the library provides a convenient `BaseMiddleware` abstract class that makes it easier to create middleware:

```php
abstract class BaseMiddleware implements HttpMiddleware
{
    public function handle(HttpClientRequest $request, CanHandleHttpRequest $next): HttpResponse {
        // 1) Pre-request logic
        $this->beforeRequest($request);

        // 2) Get the response from the next handler
        $response = $next->withRequest($request)->get();

        // 3) Post-request logic, e.g. logging or rewriting
        $response = $this->afterRequest($request, $response);

        // 4) Optionally wrap the response if we want to intercept streaming
        if ($this->shouldDecorateResponse($request, $response)) {
            $response = $this->toResponse($request, $response);
        }

        // 5) Return the (possibly wrapped) response
        return $response;
    }

    // Override these methods in your subclass
    protected function beforeRequest(HttpClientRequest $request): void {}
    protected function afterRequest(HttpClientRequest $request, HttpResponse $response): HttpResponse {
        return $response;
    }
    protected function shouldDecorateResponse(HttpClientRequest $request, HttpResponse $response): bool {
        return false;
    }
    protected function toResponse(HttpClientRequest $request, HttpResponse $response): HttpResponse {
        return $response;
    }
}
```

By extending `BaseMiddleware`, you only need to override the methods relevant to your middleware's functionality, making the code more focused and maintainable.

## Middleware Stack

The `MiddlewareStack` class manages the collection of middleware components. It provides methods to add, remove, and arrange middleware in the stack.

### Adding Middleware

There are several ways to add middleware to the stack:

```php
// Create a client
$client = new HttpClient();

// Add a single middleware to the end of the stack
$client->middleware()->append(new LoggingMiddleware());

// Add a single middleware with a name
$client->middleware()->append(new CachingMiddleware(), 'cache');

// Add a single middleware to the beginning of the stack
$client->middleware()->prepend(new AuthenticationMiddleware());

// Add a single middleware to the beginning with a name
$client->middleware()->prepend(new RateLimitingMiddleware(), 'rate-limit');

// Add multiple middleware at once
$client->withMiddleware(
    new LoggingMiddleware(),
    new RetryMiddleware(),
    new TimeoutMiddleware()
);
```

Named middleware are useful when you need to reference them later, for example, to remove or replace them.

### Removing Middleware

You can remove middleware from the stack by name:

```php
// Remove a middleware by name
$client->middleware()->remove('cache');
```

### Replacing Middleware

You can replace a middleware with another one:

```php
// Replace a middleware with a new one
$client->middleware()->replace('cache', new ImprovedCachingMiddleware());
```

### Clearing Middleware

You can remove all middleware from the stack:

```php
// Clear all middleware
$client->middleware()->clear();
```

### Checking Middleware

You can check if a middleware exists in the stack:

```php
// Check if a middleware exists
if ($client->middleware()->has('rate-limit')) {
    // The 'rate-limit' middleware exists
}
```

### Getting Middleware

You can get a middleware from the stack by name or index:

```php
// Get a middleware by name
$rateLimitMiddleware = $client->middleware()->get('rate-limit');

// Get a middleware by index
$firstMiddleware = $client->middleware()->get(0);
```

### Middleware Order

The order of middleware in the stack is important because:

1. Requests pass through middleware in the order they were added to the stack
2. Responses pass through middleware in reverse order

For example, if you add middleware in this order:
1. Authentication middleware
2. Logging middleware
3. Retry middleware

The execution flow will be:
- Request: Authentication → Logging → Retry → HTTP Driver
- Response: Retry → Logging → Authentication → Your Application

This allows you to nest functionality appropriately. For instance, the authentication middleware might add headers to the request and then verify the authentication status of the response before your application receives it.

### Middleware Application Example

Here's an example of how middleware is applied in a request-response cycle:

```php
// Create a client with middleware
$client = new HttpClient();
$client->withMiddleware(
    new LoggingMiddleware(),  // 1. Log the request and response
    new RetryMiddleware(),    // 2. Retry failed requests
    new TimeoutMiddleware()   // 3. Custom timeout handling
);

// Create a request
$request = new HttpRequest(
    url: 'https://api.example.com/data',
    method: 'GET',
    headers: ['Accept' => 'application/json'],
    body: [],
    options: []
);

// Handle the request (middleware execution flow):
// 1. LoggingMiddleware processes the request (logs outgoing request)
// 2. RetryMiddleware processes the request
// 3. TimeoutMiddleware processes the request
// 4. HTTP driver sends the request
// 5. TimeoutMiddleware processes the response
// 6. RetryMiddleware processes the response (may retry on certain status codes)
// 7. LoggingMiddleware processes the response (logs incoming response)
$response = $client->withRequest($request)->get();
```

## Built-in Middleware

The Instructor HTTP client API includes several built-in middleware components for common tasks:

### Debug Middleware

The `DebugMiddleware` logs detailed information about HTTP requests and responses:

```php
use Cognesy\Http\Middleware\Debug\DebugMiddleware;

// Enable debug middleware
$client->withMiddleware(new DebugMiddleware());

// Or use the convenience method
$client->withDebugPreset('on');
```

The debug middleware logs:
- Request URLs
- Request headers
- Request bodies
- Response headers
- Response bodies
- Streaming response data

You can configure which aspects to log in the `config/debug.php` file:

```php
return [
    'http' => [
        'enabled' => true,           // Enable/disable debug
        'trace' => false,            // Dump HTTP trace information
        'requestUrl' => true,        // Dump request URL to console
        'requestHeaders' => true,    // Dump request headers to console
        'requestBody' => true,       // Dump request body to console
        'responseHeaders' => true,   // Dump response headers to console
        'responseBody' => true,      // Dump response body to console
        'responseStream' => true,    // Dump stream data to console
        'responseStreamByLine' => true, // Dump stream as full lines or raw chunks
    ],
];
```

### StreamByLine Middleware

The `StreamByLineMiddleware` processes streaming responses line by line:

```php
use Cognesy\Http\Middleware\ServerSideEvents\StreamSSEsMiddleware;

// Add stream by line middleware
$client->withMiddleware(new StreamSSEsMiddleware());
```

You can customize how lines are processed by providing a parser function:

```php
$lineParser = function (string $line) {
    $trimmedLine = trim($line);
    if (empty($trimmedLine)) {
        return null; // Skip empty lines
    }
    return json_decode($trimmedLine, true);
};

$client->withMiddleware(new StreamByLineMiddleware($lineParser));
```


### Example Middleware Combinations

Here are some common middleware combinations for different scenarios:

#### Debugging Setup

```php
$client = new HttpClient();
$client->withMiddleware(
    new BufferResponseMiddleware(),  // Buffer responses for reuse
    new DebugMiddleware()            // Log requests and responses
);
```

#### API Client Setup

```php
$client = new HttpClient();
$client->withMiddleware(
    new RetryMiddleware(maxRetries: 3, retryDelay: 1), // Retry failed requests
    new AuthenticationMiddleware($apiKey),             // Handle authentication
    new RateLimitingMiddleware(maxRequests: 100),      // Respect rate limits
    new LoggingMiddleware()                            // Log API interactions
);
```

#### Testing Setup

```php
$client = new HttpClient();
$client->withMiddleware(
    new RecordReplayMiddleware(RecordReplayMiddleware::MODE_REPLAY) // Replay recorded responses
);
```

#### Streaming Setup

```php
$client = new HttpClient();
$client->withMiddleware(
    new StreamByLineMiddleware(), // Process streaming responses line by line
    new BufferResponseMiddleware() // Buffer responses for reuse
);
```

By combining middleware components, you can create a highly customized HTTP client that handles complex requirements while keeping your application code clean and focused.

In the next chapter, we'll explore how to create custom middleware components to handle specific requirements.