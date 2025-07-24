---
title: Changing the Underlying Client
description: 'Learn how to switch between different HTTP client implementations using the Instructor HTTP client API.'
doctest_case_dir: 'codeblocks/D03_Docs_HTTP'
doctest_case_prefix: 'ChangingClient_'
doctest_included_types: ['php']
doctest_min_lines: 10
---

One of the core features of the Instructor HTTP client API is its ability to seamlessly switch between different HTTP client implementations. This flexibility allows you to use the same code across different environments or to choose the most appropriate client for specific use cases.

## Available Client Drivers

The library includes several built-in drivers that adapt various HTTP client libraries to the unified interface used by Instructor:

### GuzzleDriver

The `GuzzleDriver` provides integration with the popular [Guzzle HTTP client](https://docs.guzzlephp.org/).

**Key Features**:
- Robust feature set
- Excellent performance
- Extensive middleware ecosystem
- Support for HTTP/2 (via cURL)
- Stream and promise-based API

**Best For**:
- General-purpose HTTP requests
- Applications that need advanced features
- Projects without framework constraints

**Requirements**:
- Requires the `guzzlehttp/guzzle` package (`composer require guzzlehttp/guzzle`)

### SymfonyDriver

The `SymfonyDriver` integrates with the [Symfony HTTP Client](https://symfony.com/doc/current/http_client.html).

**Key Features**:
- Native HTTP/2 support
- Automatic content-type detection
- Built-in profiling and logging
- No dependency on cURL
- Support for various transports (native PHP, cURL, amphp)

**Best For**:
- Symfony applications
- Projects requiring HTTP/2 support
- Low-dependency environments

**Requirements**:
- Requires the `symfony/http-client` package (`composer require symfony/http-client`)

### LaravelDriver

The `LaravelDriver` integrates with the [Laravel HTTP Client](https://laravel.com/docs/http-client).

**Key Features**:
- Elegant, fluent syntax
- Integration with Laravel ecosystem
- Built-in macros and testing utilities
- Automatic JSON handling
- Rate limiting and retry capabilities

**Best For**:
- Laravel applications
- Projects already using the Laravel framework

**Requirements**:
- Included with the Laravel framework

### MockHttpDriver

The `MockHttpDriver` is a test double that doesn't make actual HTTP requests but returns predefined responses.

**Key Features**:
- No actual network requests
- Predefined responses for testing
- Response matching based on URL, method, and body
- Support for custom response generation

**Best For**:
- Unit testing
- Offline development
- CI/CD environments

## Switching Between Clients

You can switch between the available client implementations in several ways:

### When Creating the Client

The simplest approach is to specify the client when creating the `HttpClient` instance:

```php
// Use Guzzle (assuming it's configured in config/http.php)
$guzzleClient = HttpClient::using('guzzle');

// Use Symfony
$symfonyClient = HttpClient::using('symfony');

// Use Laravel
$laravelClient = HttpClient::using('laravel');
```

The client name must correspond to a configuration entry in your `config/http.php` file.

### Using the Default Client

If you don't specify a client, the default one from your configuration will be used:

```php
// Uses the default client specified in config/http.php
$client = HttpClient::default();
```

The default client is specified in the `config/http.php` file:

```php
return [
    'defaultClient' => 'guzzle',
    'clients' => [
        // Client configurations...
    ],
];
```

### Switching at Runtime

You can create different clients for different requirements within the same application:

```php
// Create clients with different configurations
$defaultClient = HttpClient::default();
$symfonyClient = HttpClient::using('symfony');
$laravelClient = HttpClient::using('laravel');
$customClient = HttpClient::using('http-ollama');
```

Note: HttpClient instances are immutable, so you create new instances rather than switching configurations at runtime.

### Using the Static Make Method

The `HttpClient` class provides a static `make` method as an alternative to the constructor:

```php
use Cognesy\Http\HttpClientBuilder;

// Create with specific client using builder
$client = (new HttpClientBuilder())->withPreset('guzzle')->create();
// Equivalent to:
$client = HttpClient::using('guzzle');

// Create with default client and custom event dispatcher
$events = new EventDispatcher();
$client = (new HttpClientBuilder())->withEventBus($events)->create();
```

## Client-Specific Configuration

Each client type can have its own configuration in the `config/http.php` file:

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

### Multiple Configurations for the Same Client Type

You can define multiple configurations for the same client type, each with different settings:

```php
'clients' => [
    'guzzle' => [
        'httpClientType' => 'guzzle',
        'connectTimeout' => 3,
        'requestTimeout' => 30,
        // Default settings for Guzzle
    ],
    'guzzle-short-timeout' => [
        'httpClientType' => 'guzzle',
        'connectTimeout' => 1,
        'requestTimeout' => 5,
        // Short timeouts for quick operations
    ],
    'guzzle-long-timeout' => [
        'httpClientType' => 'guzzle',
        'connectTimeout' => 5,
        'requestTimeout' => 120,
        // Long timeouts for operations that take time
    ],
    'guzzle-streaming' => [
        'httpClientType' => 'guzzle',
        'connectTimeout' => 3,
        'requestTimeout' => 300,
        'idleTimeout' => 60,
        // Optimized for streaming responses
    ],
    'http-ollama' => [
        'httpClientType' => 'guzzle',
        'connectTimeout' => 1,
        'requestTimeout' => 90, // Longer timeout for AI model inference
        'idleTimeout' => -1,
        'maxConcurrent' => 5,
        'poolTimeout' => 60,
        'failOnError' => true,
    ],
],
```

Then you can select the appropriate configuration based on your needs:

```php
// For quick API calls
$quickClient = HttpClient::using('guzzle-short-timeout');

// For long-running operations
$longClient = HttpClient::using('guzzle-long-timeout');

// For streaming responses
$streamingClient = HttpClient::using('guzzle-streaming');

// For AI model requests
$aiClient = HttpClient::using('http-ollama');
```

### Common Configuration Parameters

All client types support these common configuration parameters:

| Parameter | Type | Description |
|-----------|------|-------------|
| `httpClientType` | string | The type of HTTP client (Guzzle, Symfony, Laravel) |
| `connectTimeout` | int | Maximum time to wait for connection establishment (seconds) |
| `requestTimeout` | int | Maximum time to wait for the entire request (seconds) |
| `idleTimeout` | int | Maximum time to wait between data packets (seconds, -1 for no timeout) |
| `maxConcurrent` | int | Maximum number of concurrent requests in a pool |
| `poolTimeout` | int | Maximum time to wait for all pooled requests (seconds) |
| `failOnError` | bool | Whether to throw exceptions for HTTP error responses |

### Client-Specific Parameters

Some parameters might only be relevant to specific client implementations. For example, Guzzle supports additional options like `verify` (for SSL verification) or `proxy` settings that can be passed through the underlying client.

## Example: Choosing the Right Client for Different Scenarios

Here's an example of selecting different client configurations based on the task:

```php
<?php

use Cognesy\Http\HttpClient;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Exceptions\HttpRequestException;

function fetchApiData($url, $apiKey) {
    // Use a client with short timeouts for quick API calls
    $client = new HttpClient('guzzle-short-timeout');

    $request = new HttpRequest(
        url: $url,
        method: 'GET',
        headers: [
            'Authorization' => 'Bearer ' . $apiKey,
            'Accept' => 'application/json',
        ],
        body: [],
        options: []
    );

    try {
        return $client->withRequest($request)->get();
    } catch (HttpRequestException $e) {
        // Handle error
        throw $e;
    }
}

function downloadLargeFile($url, $outputPath) {
    // Use a client with long timeouts for downloading large files
    $client = new HttpClient('guzzle-long-timeout');

    $request = new HttpRequest(
        url: $url,
        method: 'GET',
        headers: [],
        body: [],
        options: ['stream' => true]
    );

    try {
        $response = $client->withRequest($request)->get();

        $fileHandle = fopen($outputPath, 'wb');
        foreach ($response->stream(8192) as $chunk) {
            fwrite($fileHandle, $chunk);
        }
        fclose($fileHandle);

        return true;
    } catch (HttpRequestException $e) {
        // Handle error
        if (file_exists($outputPath)) {
            unlink($outputPath); // Remove partial file
        }
        throw $e;
    }
}

function generateAiResponse($prompt) {
    // Use a specialized client for AI API requests
    $client = new HttpClient('http-ollama');

    $request = new HttpRequest(
        url: 'https://api.example.com/ai/generate',
        method: 'POST',
        headers: [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ],
        body: [
            'prompt' => $prompt,
            'max_tokens' => 500,
        ],
        options: ['stream' => true]
    );

    try {
        $response = $client->withRequest($request)->get();

        $result = '';
        foreach ($response->stream() as $chunk) {
            $result .= $chunk;
        }

        return json_decode($result, true);
    } catch (HttpRequestException $e) {
        // Handle error
        throw $e;
    }
}
```

## Considerations for Switching Clients

When switching between different HTTP client implementations, keep these considerations in mind:

1. **Configuration Consistency**: Ensure that all client configurations have the appropriate settings for your application's needs.

2. **Feature Availability**: Some advanced features might be available only in specific clients. For example, HTTP/2 support might be better in one client than another.

3. **Error Handling**: Different clients might have slightly different error behavior. Instructor HTTP client API normalizes much of this, but edge cases can still occur.

4. **Middleware Compatibility**: If you're using middleware, ensure it's compatible with all client types you plan to use.

5. **Performance Characteristics**: Different clients may have different performance profiles for specific scenarios. Test with your actual workload if performance is critical.

In the next chapter, we'll explore how to customize client configurations in more detail, including runtime configuration and advanced options.
