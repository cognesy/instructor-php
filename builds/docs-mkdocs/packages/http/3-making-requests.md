---
title: 'Making Requests'
description: 'Create requests with plain values, set headers, encode bodies, and configure streaming.'
---

Every HTTP request is represented by an `HttpRequest` value object. You construct it with explicit parameters -- URL, method, headers, body, and options -- and pass it to `HttpClient::send()`. There are no magic methods or implicit state; what you see is what gets sent.

## Request Structure

The `HttpRequest` constructor accepts five named parameters:

```php
use Cognesy\Http\Data\HttpRequest;

$request = new HttpRequest(
    url: 'https://api.example.com/users',
    method: 'GET',
    headers: ['Accept' => 'application/json'],
    body: '',
    options: [],
);
// @doctest id="e6ea"
```

| Parameter | Type | Description |
|-----------|------|-------------|
| `url` | `string` | The full URL including any query parameters |
| `method` | `string` | The HTTP method (`GET`, `POST`, `PUT`, `PATCH`, `DELETE`, etc.) |
| `headers` | `array` | Associative array of header name to value |
| `body` | `string\|array` | Request body -- arrays are JSON-encoded automatically; strings are sent verbatim |
| `options` | `array` | Driver-level options (e.g., `['stream' => true]`) |

Each request is also assigned a unique `id` and timestamped with `createdAt` and `updatedAt` properties automatically.

## GET Requests

GET requests are the simplest form. Pass query parameters directly in the URL:

```php
$request = new HttpRequest(
    url: 'https://api.example.com/users?page=1&limit=10',
    method: 'GET',
    headers: ['Accept' => 'application/json'],
    body: '',
    options: [],
);

$response = $client->send($request)->get();
// @doctest id="e739"
```

## POST with JSON Body

When you pass an array as the body, it is automatically JSON-encoded through the `HttpRequestBody` class:

```php
$request = new HttpRequest(
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
    options: [],
);

$response = $client->send($request)->get();
// @doctest id="748c"
```

You can also pass a pre-encoded JSON string if you need precise control over the encoding. String bodies are sent as-is; the drivers do not parse and reserialize them:

```php
$request = new HttpRequest(
    url: 'https://api.example.com/users',
    method: 'POST',
    headers: ['Content-Type' => 'application/json'],
    body: json_encode(['name' => 'John Doe']),
    options: [],
);
// @doctest id="5bdd"
```

## PUT, PATCH, and DELETE

All HTTP methods work the same way. Just change the method string:

```php
// Update an entire resource
$putRequest = new HttpRequest(
    url: 'https://api.example.com/users/123',
    method: 'PUT',
    headers: ['Content-Type' => 'application/json'],
    body: ['name' => 'Jane Doe', 'email' => 'jane@example.com'],
    options: [],
);

// Partially update a resource
$patchRequest = new HttpRequest(
    url: 'https://api.example.com/users/123',
    method: 'PATCH',
    headers: ['Content-Type' => 'application/json'],
    body: ['email' => 'new@example.com'],
    options: [],
);

// Delete a resource
$deleteRequest = new HttpRequest(
    url: 'https://api.example.com/users/123',
    method: 'DELETE',
    headers: [],
    body: '',
    options: [],
);
// @doctest id="3d2b"
```

## Setting Headers

Headers are passed as a flat associative array. Common headers include authentication tokens, content types, and custom application headers:

```php
$request = new HttpRequest(
    url: 'https://api.example.com/data',
    method: 'GET',
    headers: [
        'Accept' => 'application/json',
        'Authorization' => 'Bearer ' . $apiToken,
        'User-Agent' => 'MyApp/1.0',
        'X-Request-Source' => 'backend',
    ],
    body: '',
    options: [],
);
// @doctest id="3bf1"
```

## Modifying Requests

`HttpRequest` is immutable. The `with*()` methods return a new instance with the modification applied:

```php
$request = $request->withHeader('Authorization', 'Bearer ' . $token);
$request = $request->withStreaming(true);
// @doctest id="d9bf"
```

You can read request properties at any time through accessor methods:

```php
$request->url();        // The request URL
$request->method();     // The HTTP method
$request->headers();    // All headers as an array
$request->headers('Accept'); // A specific header value
$request->body();       // The HttpRequestBody instance
$request->options();    // The options array
$request->isStreamed(); // Whether streaming is enabled
// @doctest id="8ace"
```

The body is managed by `HttpRequestBody`, which provides `toString()` and `toArray()` conversions:

```php
$bodyString = $request->body()->toString(); // Exact outbound body string
$bodyArray  = $request->body()->toArray();  // Decoded array when the body contains valid JSON
// @doctest id="f760"
```

## Streaming Option

To enable streaming on a request, either set the `stream` option in the constructor or use `withStreaming()`:

```php
// Via constructor
$request = new HttpRequest(
    url: 'https://api.example.com/stream',
    method: 'POST',
    headers: ['Content-Type' => 'application/json'],
    body: ['prompt' => 'Hello', 'stream' => true],
    options: ['stream' => true],
);

// Or via the mutator
$request = $request->withStreaming(true);
// @doctest id="b137"
```

When `isStreamed()` returns `true`, calling `stream()` on the `PendingHttpResponse` will yield chunks as they arrive instead of buffering the entire response.

## Building Clients with Config

For production use, you will typically configure the client with typed options:

```php
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Creation\HttpClientBuilder;

$client = (new HttpClientBuilder())
    ->withConfig(new HttpClientConfig(
        driver: 'guzzle',
        connectTimeout: 5,
        requestTimeout: 30,
        failOnError: true,
    ))
    ->create();
// @doctest id="f4ca"
```

This gives you a client that uses Guzzle, connects within 5 seconds, times out after 30 seconds, and throws exceptions on 4xx/5xx responses. See [Changing Client Config](8-changing-client-config.md) for the full list of options.
