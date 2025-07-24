---
title: Getting Started
description: 'Learn how to use the Instructor HTTP client API in your PHP project.'
doctest_case_dir: 'codeblocks/D03_Docs_HTTP'
doctest_case_prefix: 'GettingStarted_'
doctest_included_types: ['php']
doctest_min_lines: 10
---

## Installation

The Instructor HTTP client API is part of the Instructor library (https://instructorphp.com) and is bundled with it.

You can install it separately via Composer:

```bash
composer require cognesy/instructor-http-client
```

### Dependencies

The Instructor HTTP client API requires at least one of the supported HTTP client libraries. Depending on which client you want to use, you'll need to install the corresponding package:

**For Guzzle:**
```bash
composer require guzzlehttp/guzzle
```

**For Symfony HTTP Client:**
```bash
composer require symfony/http-client
```

**For Laravel HTTP Client:**
The Laravel HTTP Client is included with the Laravel framework. If you're using Laravel, you don't need to install it separately.

### PHP Requirements

The library requires:
- PHP 8.1 or higher
- JSON extension
- cURL extension (recommended)

## Basic Usage

Using the Instructor HTTP client API involves a few key steps:

1. Create an `HttpClient` instance
2. Create an `HttpRequest` object
3. Use the client to handle the request
4. Process the response

Here's a simple example:

```php
use Cognesy\Http\HttpClient;
use Cognesy\Http\Data\HttpRequest;

// Create a new HTTP client (uses the default client from configuration)
$client = HttpClient::default();

// Create a request
$request = new HttpRequest(
    url: 'https://api.example.com/data',
    method: 'GET',
    headers: ['Accept' => 'application/json'],
    body: [],
    options: []
);

// Send the request and get the response
$response = $client->withRequest($request)->get();

// Access response data
$statusCode = $response->statusCode();
$headers = $response->headers();
$body = $response->body();

echo "Status: $statusCode\n";
echo "Body: $body\n";
```

### Error Handling

HTTP requests can fail for various reasons. You should always wrap request handling in a try-catch block:

```php
use Cognesy\Http\Exceptions\HttpRequestException;

try {
    $response = $client->withRequest($request)->get();
    // Process the response
} catch (HttpRequestException $e) {
    echo "Request failed: {$e->getMessage()}\n";
    // Handle the error
}
```

## Configuration

The Instructor HTTP client API can be configured via configuration files or at runtime.

### Configuration Files

Create the configuration files in your project:

**config/http.php:**
```php
// @doctest skip=true
return [
    'defaultClient' => 'guzzle',
    'clients' => [
        'guzzle' => [
            'httpClientType' => 'guzzle',
            'connectTimeout' => 3,
            'requestTimeout' => 30,
            'idleTimeout' => -1,
            'maxConcurrent' => 5,
            'poolTimeout' => 60,
            'failOnError' => true,
        ],
        'symfony' => [
            'httpClientType' => 'symfony',
            'connectTimeout' => 1,
            'requestTimeout' => 30,
            'idleTimeout' => -1,
            'maxConcurrent' => 5,
            'poolTimeout' => 60,
            'failOnError' => true,
        ],
        'laravel' => [
            'httpClientType' => 'laravel',
            'connectTimeout' => 1,
            'requestTimeout' => 30,
            'idleTimeout' => -1,
            'maxConcurrent' => 5,
            'poolTimeout' => 60,
            'failOnError' => true,
        ],
    ],
];
```

**config/debug.php:**
```php
return [
    'http' => [
        'enabled' => false, // enable/disable debug
        'trace' => false, // dump HTTP trace information
        'requestUrl' => true, // dump request URL to console
        'requestHeaders' => true, // dump request headers to console
        'requestBody' => true, // dump request body to console
        'responseHeaders' => true, // dump response headers to console
        'responseBody' => true, // dump response body to console
        'responseStream' => true, // dump stream data to console
        'responseStreamByLine' => true, // dump stream as full lines or raw chunks
    ],
];
```

### Runtime Configuration

You can also configure the client at runtime:

```php
<?php
use Cognesy\Http\HttpClient;

// Create client with specific configuration
$client = HttpClient::using('guzzle');

// Or create with debug enabled
$client = (new HttpClientBuilder())
    ->withPreset('guzzle')
    ->withDebugPreset('on')
    ->create();
```

## Simple Request Example

Let's put everything together with a practical example of making a POST request to create a new resource:

```php
use Cognesy\Http\HttpClient;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Exceptions\HttpRequestException;

// Create an HTTP client using the 'guzzle' configuration
$client = HttpClient::using('guzzle');

// Create a POST request with JSON data
$request = new HttpRequest(
    url: 'https://api.example.com/users',
    method: 'POST',
    headers: [
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
        'Authorization' => 'Bearer ' . $apiToken,
    ],
    body: [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'role' => 'user',
    ],
    options: []
);

try {
    // Send the request
    $response = $client->withRequest($request)->get();

    // Process the response
    if ($response->statusCode() === 201) {
        $user = json_decode($response->body(), true);
        echo "User created with ID: {$user['id']}\n";

        // Print user details
        echo "Name: {$user['name']}\n";
        echo "Email: {$user['email']}\n";
    } else {
        echo "Error: Unexpected status code {$response->statusCode()}\n";
        echo "Response: {$response->body()}\n";
    }
} catch (HttpRequestException $e) {
    echo "Request failed: {$e->getMessage()}\n";

    // You might want to log the error or retry the request
}
```

### Example: Fetching Data

Here's an example of making a GET request to fetch data:

```php
use Cognesy\Http\HttpClient;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Exceptions\HttpRequestException;

// Create a default HTTP client
$client = HttpClient::default();

// Create a GET request with query parameters
$request = new HttpRequest(
    url: 'https://api.example.com/users?page=1&limit=10',
    method: 'GET',
    headers: [
        'Accept' => 'application/json',
        'Authorization' => 'Bearer ' . $apiToken,
    ],
    body: [],
    options: []
);

try {
    // Send the request
    $response = $client->withRequest($request)->get();

    // Process the response
    if ($response->statusCode() === 200) {
        $data = json_decode($response->body(), true);
        $users = $data['users'] ?? [];

        echo "Retrieved " . count($users) . " users:\n";

        foreach ($users as $user) {
            echo "- {$user['name']} ({$user['email']})\n";
        }
    } else {
        echo "Error: Unexpected status code {$response->statusCode()}\n";
        echo "Response: {$response->body()}\n";
    }
} catch (HttpRequestException $e) {
    echo "Request failed: {$e->getMessage()}\n";
}
```

These examples demonstrate the basic usage of the Instructor HTTP client API for common HTTP operations. In the following chapters, we'll explore more advanced features and customization options.