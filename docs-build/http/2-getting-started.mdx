---
title: Getting Started
description: 'Learn how to use the Instructor HTTP client API in your PHP project.'
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
2. Create an `HttpClientRequest` object
3. Use the client to handle the request
4. Process the response0

Here's a simple example:

```php
<?php

use Cognesy\Http\HttpClient;
use Cognesy\Http\Data\HttpClientRequest;

// Create a new HTTP client (uses the default client from configuration)
$client = new HttpClient();

// Create a request
$request = new HttpClientRequest(
    url: 'https://api.example.com/data',
    method: 'GET',
    headers: ['Accept' => 'application/json'],
    body: [],
    options: []
);

// Send the request and get the response
$response = $client->handle($request);

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
use Cognesy\Http\Exceptions\RequestException;

try {
    $response = $client->handle($request);
    // Process the response
} catch (RequestException $e) {
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
<?php
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
<?php
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
// Create client with specific configuration
$client = new HttpClient('guzzle');

// Or switch to a different configuration
$client->withClient('symfony');

// Enable debug mode
$client->withDebug(true);
```

## Simple Request Example

Let's put everything together with a practical example of making a POST request to create a new resource:

```php
<?php

use Cognesy\Http\HttpClient;
use Cognesy\Http\Data\HttpClientRequest;
use Cognesy\Http\Exceptions\RequestException;

// Create an HTTP client using the 'guzzle' configuration
$client = new HttpClient('guzzle');

// Create a POST request with JSON data
$request = new HttpClientRequest(
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
    $response = $client->handle($request);

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
} catch (RequestException $e) {
    echo "Request failed: {$e->getMessage()}\n";

    // You might want to log the error or retry the request
}
```

### Example: Fetching Data

Here's an example of making a GET request to fetch data:

```php
<?php

use Cognesy\Http\HttpClient;
use Cognesy\Http\Data\HttpClientRequest;
use Cognesy\Http\Exceptions\RequestException;

// Create an HTTP client
$client = new HttpClient();

// Create a GET request with query parameters
$request = new HttpClientRequest(
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
    $response = $client->handle($request);

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
} catch (RequestException $e) {
    echo "Request failed: {$e->getMessage()}\n";
}
```

These examples demonstrate the basic usage of the Instructor HTTP client API for common HTTP operations. In the following chapters, we'll explore more advanced features and customization options.