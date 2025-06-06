---
title: Customizing Client Configuration
description: 'Learn how to configure different HTTP clients using the Instructor HTTP client API.'
---

The Instructor HTTP client API offers extensive configuration options to customize client behavior for different scenarios. This chapter explores how to configure clients through configuration files and at runtime.

## Configuration Files

The primary configuration files for the HTTP client are:

### Main Configuration: config/http.php

This file defines the available client types and their settings:

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

### Debug Configuration: config/debug.php

This file controls debugging options for HTTP requests and responses:

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

### Loading Configuration Files

The library uses a settings management system to load these configurations. The system looks for these files in the base directory of your project. If you're using a framework like Laravel or Symfony, you can integrate with their configuration systems instead.

For Laravel, you might publish these configurations as Laravel config files:

```bash
php artisan vendor:publish --tag=polyglot-config
```

For Symfony, you might define these as service parameters in your service configuration.

## Configuration Options

The `HttpClientConfig` class encapsulates the configuration options for HTTP clients. Here's a detailed breakdown of the available options:

### Basic Connection Options

| Option | Type | Description | Default   |
|--------|------|-------------|-----------|
| `httpClientType` | string | Type of HTTP client to use | `'guzzle'` |
| `connectTimeout` | int | Connection timeout in seconds | 3         |
| `requestTimeout` | int | Request timeout in seconds | 30        |
| `idleTimeout` | int | Idle timeout in seconds (-1 for no timeout) | -1        |

### Request Pool Options

| Option | Type | Description | Default |
|--------|------|-------------|---------|
| `maxConcurrent` | int | Maximum number of concurrent requests in a pool | 5 |
| `poolTimeout` | int | Timeout for the entire request pool in seconds | 60 |

### Error Handling Options

| Option | Type | Description | Default |
|--------|------|-------------|---------|
| `failOnError` | bool | Whether to throw exceptions on HTTP errors | true |

### Debug Options

| Option | Type | Description | Default |
|--------|------|-------------|---------|
| `enabled` | bool | Enable or disable HTTP debugging | false |
| `trace` | bool | Dump HTTP trace information | false |
| `requestUrl` | bool | Log the request URL | true |
| `requestHeaders` | bool | Log request headers | true |
| `requestBody` | bool | Log request body | true |
| `responseHeaders` | bool | Log response headers | true |
| `responseBody` | bool | Log response body | true |
| `responseStream` | bool | Log streaming response data | true |
| `responseStreamByLine` | bool | Log stream as complete lines (true) or raw chunks (false) | true |

### Understanding Timeout Options

Timeout settings are crucial for controlling how your application handles slow or unresponsive servers:

- **connectTimeout**: Maximum time to wait for establishing a connection to the server. If the server doesn't respond within this time, the request fails with a connection timeout error. Setting this too low might cause failures when connecting to slow servers, but setting it too high could leave your application waiting for unresponsive servers.

- **requestTimeout**: Maximum time to wait for the entire request to complete, from connection initiation to receiving the complete response. If the entire request-response cycle isn't completed within this time, the request fails with a timeout error.

- **idleTimeout**: Maximum time to wait between receiving data packets. If the server stops sending data for longer than this period, the connection is considered idle and is terminated. Setting this to -1 disables the idle timeout, which is useful for long-running streaming connections.

- **poolTimeout**: Maximum time to wait for all requests in a pool to complete. If any requests in the pool haven't completed within this time, they're terminated.

## Runtime Configuration

While configuration files provide a static way to configure clients, you often need to change configuration at runtime based on the specific requirements of a request or operation.

### Using withClient

The simplest way to switch configurations at runtime is to use the `withClient` method to select a different pre-configured client:

```php
// Start with default client
$client = new HttpClient();

// Switch to a client with longer timeouts
$client->withClient('guzzle-long-timeout');

// Switch to a client optimized for streaming
$client->withClient('guzzle-streaming');
```

### Using withConfig

For more dynamic configuration, you can create a custom `HttpClientConfig` object and apply it using the `withConfig` method:

```php
use Cognesy\Http\Config\HttpClientConfig;

// Create a custom configuration
$config = new HttpClientConfig(
    driver: 'guzzle',
    connectTimeout: 5,
    requestTimeout: 60,
    idleTimeout: 30,
    maxConcurrent: 10,
    poolTimeout: 120,
    failOnError: false
);

// Use the custom configuration
$client->withConfig($config);
```

This method gives you complete control over the configuration at runtime.

### Creating Configuration from an Array

You can also create a configuration from an associative array:

```php
$configArray = [
    'httpClientType' => 'symfony',
    'connectTimeout' => 2,
    'requestTimeout' => 45,
    'idleTimeout' => -1,
    'maxConcurrent' => 8,
    'poolTimeout' => 90,
    'failOnError' => true,
];

$config = HttpClientConfig::fromArray($configArray);
$client->withConfig($config);
```

This approach is useful when loading configuration from external sources like environment variables or configuration files.

### Enabling Debug Mode

You can enable debug mode to see detailed information about requests and responses:

```php
// Enable debug mode
$client->withDebugPreset('on');

// Make a request
$response = $client->handle($request);

// Disable debug mode when done
$client->withDebugPreset('off');
```

When debug mode is enabled, detailed information about requests and responses is output to the console or log.

### Example: Dynamic Configuration Based on Request Type

Here's an example of dynamically adjusting configuration based on the type of request:

```php
function configureClientForRequest(HttpClient $client, HttpClientRequest $request): HttpClient {
    // Get the current configuration
    $config = HttpClientConfig::load($client->getClientName());

    // Adjust timeouts based on the request URL
    if (strpos($request->url(), 'large-file') !== false) {
        // For large file downloads, use longer timeouts
        $config = new HttpClientConfig(
            httpClientType: $config->httpClientType,
            connectTimeout: $config->connectTimeout,
            requestTimeout: 300, // 5 minutes
            idleTimeout: 60,     // 1 minute
            maxConcurrent: $config->maxConcurrent,
            poolTimeout: $config->poolTimeout,
            failOnError: $config->failOnError
        );

        $client->withConfig($config);
    }

    // Enable streaming for specific endpoints
    if (strpos($request->url(), '/stream') !== false || strpos($request->url(), '/events') !== false) {
        // Make sure the request is set to stream
        $request = $request->withStreaming(true);
    }

    // Enable debug for development environment
    if (getenv('APP_ENV') === 'development') {
        $client->withDebugPreset('on');
    }

    return $client;
}

// Usage
$client = new HttpClient();
$request = new HttpClientRequest(...);

// Configure the client based on the request
$client = configureClientForRequest($client, $request);

// Send the request
$response = $client->handle($request);
```

This approach allows for highly dynamic and contextual configuration adjustments.

### Configuration Best Practices

1. **Define Base Configurations in Files**: Keep your common configurations in the `config/http.php` file for easy reference and maintenance.

2. **Use Named Configurations**: Create named configurations for different scenarios (e.g., `'guzzle-short-timeout'`, `'guzzle-streaming'`) to make your code more readable and maintainable.

3. **Adjust Timeouts Appropriately**: Set timeouts based on the expected response time of the API or service you're calling. Shorter for quick operations, longer for file uploads/downloads or streaming.

4. **Consider Error Handling Strategy**: Set `failOnError` based on how you want to handle errors. For critical operations, set it to `true` to catch errors immediately. For bulk operations or request pools, set it to `false` to handle errors individually.

5. **Use Debug Mode Judiciously**: Enable debug mode only when needed, as it can generate a lot of output and potentially impact performance.

6. **Test Different Configurations**: Experiment with different settings to find the optimal configuration for your specific use cases.

## Adapting to Different Environments

Different environments often require different configurations. Here's how you might handle this:

```php
// In your application bootstrap or service provider
function configureHttpClient() {
    $env = getenv('APP_ENV') ?: 'production';

    // Load base configuration
    $config = HttpClientConfig::load('guzzle');

    // Adjust based on environment
    switch ($env) {
        case 'development':
            // Shorter timeouts for faster feedback during development
            $config = new HttpClientConfig(
                httpClientType: $config->httpClientType,
                connectTimeout: 1,
                requestTimeout: 10,
                idleTimeout: $config->idleTimeout,
                maxConcurrent: $config->maxConcurrent,
                poolTimeout: $config->poolTimeout,
                failOnError: true // Throw errors for immediate feedback
            );
            break;

        case 'testing':
            // Use mock driver for tests
            $config = new HttpClientConfig(
                httpClientType: 'custom',
                connectTimeout: 1,
                requestTimeout: 1,
                idleTimeout: 1,
                maxConcurrent: 1,
                poolTimeout: 5,
                failOnError: true
            );

            // Create a mock client
            $mockDriver = new MockHttpDriver();
            // Configure mock responses...

            return (new HttpClient())->withConfig($config)->withDriver($mockDriver);

        case 'production':
            // More conservative timeouts for production
            $config = new HttpClientConfig(
                httpClientType: $config->httpClientType,
                connectTimeout: 5,
                requestTimeout: 60,
                idleTimeout: $config->idleTimeout,
                maxConcurrent: 10,
                poolTimeout: 120,
                failOnError: false // Handle errors gracefully in production
            );
            break;
    }

    return (new HttpClient())->withConfig($config);
}

// Get a properly configured client
$client = configureHttpClient();
```

This approach allows you to adapt your HTTP client configuration to different environments while maintaining a consistent API.

In the next chapter, we'll explore how to create and use custom HTTP client implementations for specialized needs.