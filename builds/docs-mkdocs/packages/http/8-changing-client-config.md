---
title: 'Changing Client Config'
description: 'Configure timeouts, error handling, stream settings, and debug output.'
---

The `HttpClientConfig` class provides typed, immutable configuration for the HTTP client. Every setting has a sensible default, so you only need to specify what you want to change.

## Configuration Options

The constructor accepts the following parameters:

```php
use Cognesy\Http\Config\HttpClientConfig;

$config = new HttpClientConfig(
    driver: 'curl',
    connectTimeout: 3,
    requestTimeout: 30,
    idleTimeout: -1,
    streamChunkSize: 256,
    streamHeaderTimeout: 5,
    failOnError: false,
);
// @doctest id="e450"
```

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `driver` | `string` | `'curl'` | Which driver to use (`curl`, `guzzle`, `symfony`) |
| `connectTimeout` | `int` | `3` | Maximum seconds to wait for connection establishment |
| `requestTimeout` | `int` | `30` | Maximum seconds for the entire request-response cycle |
| `idleTimeout` | `int` | `-1` | Maximum seconds between data packets (`-1` disables) |
| `streamChunkSize` | `int` | `256` | Bytes per chunk when streaming responses |
| `streamHeaderTimeout` | `int` | `5` | Seconds to wait for the initial response headers during streaming |
| `failOnError` | `bool` | `false` | Throw exceptions on 4xx/5xx responses |

### Understanding Timeouts

Getting timeouts right is critical for production reliability:

- **connectTimeout** controls how long the client waits to establish a TCP connection. Set this low (1-3 seconds) for services that should respond quickly. Set it higher (5-10 seconds) for services behind slow DNS or distant networks.

- **requestTimeout** is the maximum total time for the request, from connection initiation to receiving the complete response. For quick API calls, 10-30 seconds is typical. For LLM inference or large file downloads, you may need 60-300 seconds.

- **idleTimeout** applies to the gap between data packets. Setting this to `-1` disables it, which is appropriate for long-lived streaming connections. For non-streaming requests, a value like `30` seconds catches stalled connections.

- **streamHeaderTimeout** is specific to streaming: it controls how long to wait for the first response headers before giving up. This is separate from `connectTimeout` because some APIs take time to start generating content.

## Using Config with the Builder

Pass your config to the builder to create a fully configured client:

```php
use Cognesy\Http\Creation\HttpClientBuilder;

$client = (new HttpClientBuilder())
    ->withConfig(new HttpClientConfig(
        driver: 'guzzle',
        connectTimeout: 5,
        requestTimeout: 60,
        failOnError: true,
    ))
    ->create();
// @doctest id="6880"
```

Or create the client directly:

```php
use Cognesy\Http\HttpClient;

$client = HttpClient::fromConfig(new HttpClientConfig(
    driver: 'symfony',
    requestTimeout: 120,
));
// @doctest id="955f"
```

## Presets

`HttpClientConfig` ships with YAML preset files for common driver configurations. Use `fromPreset()` to load one by name:

```php
use Cognesy\Http\Config\HttpClientConfig;

$config = HttpClientConfig::fromPreset('guzzle');
// @doctest id="0cf5"
```

Available presets: `curl`, `guzzle`, `symfony`, `http-ollama`.

The `HttpClient` facade offers a shorthand:

```php
use Cognesy\Http\HttpClient;

$client = HttpClient::using('guzzle');
// @doctest id="ea94"
```

You can override individual fields after loading a preset:

```php
$config = HttpClientConfig::fromPreset('symfony')
    ->withOverrides(['requestTimeout' => 120, 'failOnError' => true]);
// @doctest id="f37f"
```

## DSN Strings

For environments where configuration comes from environment variables or strings, you can use DSN format:

```php
$client = (new HttpClientBuilder())
    ->withDsn('driver=symfony,connectTimeout=2,requestTimeout=20,streamHeaderTimeout=5,failOnError=true')
    ->create();
// @doctest id="68b9"
```

DSN values are automatically coerced to the correct types -- integers for timeout fields, booleans for `failOnError`, and strings for `driver`.

## Overriding an Existing Config

The `withOverrides()` method creates a new config from an existing one with selective changes:

```php
$base = new HttpClientConfig(driver: 'guzzle', requestTimeout: 30);
$strict = $base->withOverrides(['failOnError' => true, 'requestTimeout' => 60]);

$client = HttpClient::fromConfig($strict);
// @doctest id="8a00"
```

Only the fields you specify in the override array are changed; everything else carries forward from the base config.

## Creating Config from Arrays

When loading configuration from external sources (files, environment, etc.), use the `fromArray()` factory:

```php
$config = HttpClientConfig::fromArray([
    'driver' => 'symfony',
    'connectTimeout' => 2,
    'requestTimeout' => 45,
    'failOnError' => true,
]);
// @doctest id="07a4"
```

## Debug Configuration

The `DebugConfig` class controls what gets logged during HTTP interactions. It is separate from `HttpClientConfig` and is passed to the builder independently:

```php
use Cognesy\Http\Config\DebugConfig;
use Cognesy\Http\Creation\HttpClientBuilder;

$client = (new HttpClientBuilder())
    ->withConfig(new HttpClientConfig(driver: 'guzzle'))
    ->withDebugConfig(new DebugConfig(
        httpEnabled: true,
        httpRequestUrl: true,
        httpRequestHeaders: true,
        httpRequestBody: true,
        httpResponseHeaders: true,
        httpResponseBody: true,
        httpResponseStream: true,
        httpResponseStreamByLine: true,
    ))
    ->create();
// @doctest id="384e"
```

| Option | Default | Description |
|--------|---------|-------------|
| `httpEnabled` | `false` | Master switch for debug output |
| `httpTrace` | `false` | Dump HTTP trace information |
| `httpRequestUrl` | `true` | Log the request URL |
| `httpRequestHeaders` | `true` | Log request headers |
| `httpRequestBody` | `true` | Log the request body |
| `httpResponseHeaders` | `true` | Log response headers |
| `httpResponseBody` | `true` | Log the response body |
| `httpResponseStream` | `true` | Log streaming response data |
| `httpResponseStreamByLine` | `true` | Log stream as complete lines vs. raw chunks |

When debug is enabled, the builder automatically prepends an `EventSourceMiddleware` with console and event listeners. You can also load presets from YAML files using `DebugConfig::fromPreset('on')`.

## Configuration Patterns

### Different Profiles for Different Use Cases

Create distinct configs for different scenarios:

```php
// Quick API calls
$quickConfig = new HttpClientConfig(
    connectTimeout: 1,
    requestTimeout: 5,
    failOnError: true,
);

// LLM inference (long-running)
$llmConfig = new HttpClientConfig(
    connectTimeout: 3,
    requestTimeout: 120,
    idleTimeout: 60,
    streamChunkSize: 512,
);

// File downloads
$downloadConfig = new HttpClientConfig(
    connectTimeout: 5,
    requestTimeout: 300,
    idleTimeout: 30,
);
// @doctest id="6a71"
```

### Environment-Based Configuration

Adjust settings based on the runtime environment:

```php
$timeout = match (getenv('APP_ENV')) {
    'testing' => 1,
    'development' => 10,
    default => 30,
};

$config = new HttpClientConfig(
    requestTimeout: $timeout,
    failOnError: getenv('APP_ENV') !== 'production',
);
// @doctest id="41a9"
```

## See Also

- [Changing Client](7-changing-client.md) -- switch between drivers.
- [Making Requests](3-making-requests.md) -- construct and send requests.
- [Handling Responses](4-handling-responses.md) -- read response data.
