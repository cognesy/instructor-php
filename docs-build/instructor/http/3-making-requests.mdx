---
title: Making HTTP Requests
description: 'Learn how to create and send HTTP requests using the Instructor HTTP client API.'
---

The Instructor HTTP client API provides a flexible and consistent way to create and send HTTP requests across different client implementations. This chapter covers the details of building and customizing HTTP requests.

## Creating Requests

All HTTP requests are created using the `HttpClientRequest` class, which encapsulates the various components of an HTTP request.

### Basic Request Creation

The constructor for `HttpClientRequest` takes several parameters:

```php
$request = new HttpClientRequest(
    url: 'https://api.example.com/endpoint',
    method: 'GET',
    headers: ['Accept' => 'application/json'],
    body: [],
    options: []
);
```

The parameters are:

- `url`: The URL to send the request to (string)
- `method`: The HTTP method to use (string)
- `headers`: An associative array of HTTP headers (array)
- `body`: The request body, which can be a string or an array (mixed)
- `options`: Additional options for the request (array)

### Request Methods

Once you've created a request, you can access its properties using the following methods:

```php
// Get the request URL
$url = $request->url();

// Get the HTTP method
$method = $request->method();

// Get the request headers
$headers = $request->headers();

// Get the request body
$body = $request->body();

// Get the request options
$options = $request->options();

// Check if the request is configured for streaming
$isStreaming = $request->isStreamed();
```

### Modifying Requests

You can also modify a request after it's been created:

```php
// Enable streaming for this request
$streamingRequest = $request->withStreaming(true);
```

Note that the `with*` methods return a new request instance rather than modifying the original one.

## HTTP Methods

The HTTP method is specified as a string in the `HttpClientRequest` constructor. The library supports all standard HTTP methods:

### GET Requests

GET requests are used to retrieve data from a server:

```php
$getRequest = new HttpClientRequest(
    url: 'https://api.example.com/users',
    method: 'GET',
    headers: ['Accept' => 'application/json'],
    body: [],
    options: []
);
```

For GET requests with query parameters, include them in the URL:

```php
$getRequestWithParams = new HttpClientRequest(
    url: 'https://api.example.com/users?page=1&limit=10&sort=name',
    method: 'GET',
    headers: ['Accept' => 'application/json'],
    body: [],
    options: []
);
```

### POST Requests

POST requests are used to create new resources or submit data:

```php
$postRequest = new HttpClientRequest(
    url: 'https://api.example.com/users',
    method: 'POST',
    headers: [
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
    ],
    body: [
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ],
    options: []
);
```

### PUT Requests

PUT requests are used to update existing resources:

```php
$putRequest = new HttpClientRequest(
    url: 'https://api.example.com/users/123',
    method: 'PUT',
    headers: [
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
    ],
    body: [
        'name' => 'John Updated',
        'email' => 'john.updated@example.com',
    ],
    options: []
);
```

### PATCH Requests

PATCH requests are used to partially update resources:

```php
$patchRequest = new HttpClientRequest(
    url: 'https://api.example.com/users/123',
    method: 'PATCH',
    headers: [
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
    ],
    body: [
        'email' => 'new.email@example.com',
    ],
    options: []
);
```

### DELETE Requests

DELETE requests are used to remove resources:

```php
$deleteRequest = new HttpClientRequest(
    url: 'https://api.example.com/users/123',
    method: 'DELETE',
    headers: ['Accept' => 'application/json'],
    body: [],
    options: []
);
```

### Other Methods

The library also supports other HTTP methods like HEAD, OPTIONS, etc. Just specify the method name as a string:

```php
$headRequest = new HttpClientRequest(
    url: 'https://api.example.com/users',
    method: 'HEAD',
    headers: [],
    body: [],
    options: []
);

$optionsRequest = new HttpClientRequest(
    url: 'https://api.example.com/users',
    method: 'OPTIONS',
    headers: [],
    body: [],
    options: []
);
```

## Setting Headers

HTTP headers are specified as an associative array where keys are header names and values are header values:

```php
$request = new HttpClientRequest(
    url: 'https://api.example.com/data',
    method: 'GET',
    headers: [
        'Accept' => 'application/json',
        'Authorization' => 'Bearer ' . $apiToken,
        'User-Agent' => 'MyApp/1.0',
        'X-Custom-Header' => 'Custom Value',
    ],
    body: [],
    options: []
);
```

### Common Headers

Some commonly used HTTP headers include:

- **Content-Type**: Specifies the format of the request body
```php
'Content-Type' => 'application/json'
  ```

- **Accept**: Indicates what response format the client can understand
```php
'Accept' => 'application/json'
  ```

- **Authorization**: Provides authentication credentials
```php
'Authorization' => 'Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...'
  ```

- **User-Agent**: Identifies the client application
```php
'User-Agent' => 'MyApp/1.0 (https://example.com)'
  ```

- **Cache-Control**: Directives for caching mechanisms
```php
'Cache-Control' => 'no-cache'
  ```

- **Accept-Language**: Indicates the preferred language
```php
'Accept-Language' => 'en-US,en;q=0.9'
  ```

## Request Body

The request body can be provided in two ways:

### Array Body (JSON)

If you provide an array as the request body, it will automatically be converted to a JSON string:

```php
$request = new HttpClientRequest(
    url: 'https://api.example.com/users',
    method: 'POST',
    headers: ['Content-Type' => 'application/json'],
    body: [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'age' => 30,
        'address' => [
            'street' => '123 Main St',
            'city' => 'Anytown',
            'zipcode' => '12345',
        ],
        'tags' => ['developer', 'php'],
    ],
    options: []
);
```

When using an array for the body, you should set the `Content-Type` header to `application/json`.

### String Body

You can also provide the body as a raw string:

```php
// JSON string
$jsonBody = json_encode([
    'name' => 'John Doe',
    'email' => 'john@example.com',
]);

$request = new HttpClientRequest(
    url: 'https://api.example.com/users',
    method: 'POST',
    headers: ['Content-Type' => 'application/json'],
    body: $jsonBody,
    options: []
);
```

This approach is useful for other content types:

```php
// Form URL-encoded data
$formBody = http_build_query([
    'name' => 'John Doe',
    'email' => 'john@example.com',
]);

$request = new HttpClientRequest(
    url: 'https://api.example.com/users',
    method: 'POST',
    headers: ['Content-Type' => 'application/x-www-form-urlencoded'],
    body: $formBody,
    options: []
);
```

### Working with Request Body

The body is managed by the `HttpRequestBody` class, which provides methods to access the body in different formats:

```php
// Get the body as a string
$bodyString = $request->body()->toString();

// Get the body as an array (for JSON bodies)
$bodyArray = $request->body()->toArray();
```

## Request Options

The `options` parameter allows you to specify additional options for the request:

```php
$request = new HttpClientRequest(
    url: 'https://api.example.com/data',
    method: 'GET',
    headers: [],
    body: [],
    options: [
        'stream' => true,  // Enable streaming response
    ]
);
```

### Available Options

Currently, the main supported option is:

- `stream`: When set to `true`, enables streaming response handling

You can check if a request is configured for streaming:

```php
if ($request->isStreamed()) {
    // Handle streaming response
}
```

### Example: Streaming Request

Here's how to create a request for a streaming API:

```php
$streamingRequest = new HttpClientRequest(
    url: 'https://api.openai.com/v1/completions',
    method: 'POST',
    headers: [
        'Content-Type' => 'application/json',
        'Authorization' => 'Bearer ' . $apiKey,
    ],
    body: [
        'model' => 'text-davinci-003',
        'prompt' => 'Once upon a time',
        'max_tokens' => 100,
        'stream' => true,
    ],
    options: [
        'stream' => true, // Enable streaming in the client
    ]
);
```

In the following chapters, we'll explore how to handle responses, including streaming responses, and how to use more advanced features like request pools and middleware.