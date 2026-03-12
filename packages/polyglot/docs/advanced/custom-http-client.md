---
title: Custom HTTP Client
description: Inject your own HTTP transport into a runtime.
---

Polyglot creates an HTTP client for you by default using the `HttpClientBuilder`. In most
cases this is all you need. However, if your application already owns the HTTP transport
concern -- for example, you need custom timeouts, middleware, or a shared client instance --
you can build your own HTTP client and inject it into the runtime.


## Injecting an HTTP Client

The `InferenceRuntime::fromConfig()` method accepts an optional `httpClient` parameter. Build
an HTTP client with `HttpClientBuilder`, configure it to your needs, and pass it in:

```php
<?php

use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\InferenceRuntime;

$httpClient = (new HttpClientBuilder())
    ->withConfig(new HttpClientConfig(
        connectTimeout: 5,
        requestTimeout: 60,
        idleTimeout: 120,
        failOnError: true,
    ))
    ->create();

$runtime = InferenceRuntime::fromConfig(
    config: new LLMConfig(
        driver: 'openai',
        apiUrl: 'https://api.openai.com/v1',
        apiKey: (string) getenv('OPENAI_API_KEY'),
        endpoint: '/chat/completions',
        model: 'gpt-4.1-nano',
    ),
    httpClient: $httpClient,
);

$text = Inference::fromRuntime($runtime)
    ->withMessages(Messages::fromString('Say hello.'))
    ->get();
```

When no HTTP client is provided, Polyglot creates a default one with sensible timeouts. The
custom client you inject will be used for all requests made through that runtime.


## HTTP Client Configuration Options

The `HttpClientConfig` class accepts these parameters:

| Parameter | Default | Description |
|---|---|---|
| `driver` | `'curl'` | The underlying HTTP driver (`curl`, `guzzle`, `symfony`) |
| `connectTimeout` | `3` | Maximum time to establish a connection (seconds) |
| `requestTimeout` | `30` | Maximum total request execution time (seconds) |
| `idleTimeout` | `-1` | Idle timeout for streaming connections (seconds, -1 = unlimited) |
| `streamChunkSize` | `256` | Size of chunks when reading streaming responses (bytes) |
| `streamHeaderTimeout` | `5` | Timeout for receiving stream headers (seconds) |
| `failOnError` | `false` | Whether to throw exceptions on HTTP error status codes |


## Choosing an HTTP Driver

Polyglot supports multiple HTTP drivers. The default `curl` driver works without additional
dependencies. If your project already uses Guzzle or Symfony HttpClient, you can reuse them:

```php
<?php

use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Creation\HttpClientBuilder;

// Use Guzzle as the HTTP transport
$http = (new HttpClientBuilder())
    ->withConfig(new HttpClientConfig(driver: 'guzzle'))
    ->create();
```

You can also inject a pre-configured client instance from your application's service container:

```php
<?php

use Cognesy\Http\Creation\HttpClientBuilder;

// Use an existing GuzzleHttp\Client instance
$guzzleClient = new \GuzzleHttp\Client([
    'proxy' => 'http://proxy.example.com:8080',
]);

$http = (new HttpClientBuilder())
    ->withClientInstance('guzzle', $guzzleClient)
    ->create();
```

This is particularly useful when your application requires proxy configuration, custom SSL
certificates, or other transport-level settings.


## Adding Middleware

The `HttpClientBuilder` supports a middleware stack for cross-cutting concerns like retries,
circuit breaking, and request logging. Middleware is applied in the order it is added.

### Retry Policy

Automatically retry failed requests with exponential backoff:

```php
<?php

use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Extras\Support\RetryPolicy;

$httpClient = (new HttpClientBuilder())
    ->withRetryPolicy(new RetryPolicy(
        maxRetries: 3,
        baseDelayMs: 500,
        maxDelayMs: 8000,
    ))
    ->create();
```

### Circuit Breaker

Protect your application from cascading failures by stopping requests to a failing provider:

```php
<?php

use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Extras\Support\CircuitBreakerPolicy;

$httpClient = (new HttpClientBuilder())
    ->withCircuitBreakerPolicy(new CircuitBreakerPolicy(
        failureThreshold: 5,
        openForSec: 30,
    ))
    ->create();
```

### Combining Multiple Middleware

Stack retry and circuit breaker policies together for robust error handling:

```php
<?php

use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Extras\Support\CircuitBreakerPolicy;
use Cognesy\Http\Extras\Support\RetryPolicy;

$httpClient = (new HttpClientBuilder())
    ->withRetryPolicy(new RetryPolicy(maxRetries: 3))
    ->withCircuitBreakerPolicy(new CircuitBreakerPolicy(
        failureThreshold: 5,
        openForSec: 30,
    ))
    ->create();
```

### Custom Middleware

You can also add your own middleware for logging, metrics, or request transformation:

```php
<?php

use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Contracts\HttpMiddleware;

$httpClient = (new HttpClientBuilder())
    ->withMiddleware(new MyLoggingMiddleware(), new MyMetricsMiddleware())
    ->create();
```


## Using with Embeddings

The same pattern works for the embeddings runtime. Pass your custom HTTP client when building
an `EmbeddingsRuntime`:

```php
<?php

use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\Embeddings;
use Cognesy\Polyglot\Embeddings\EmbeddingsRuntime;

$httpClient = (new HttpClientBuilder())->create();

$runtime = EmbeddingsRuntime::fromConfig(
    config: new EmbeddingsConfig(
        driver: 'openai',
        apiUrl: 'https://api.openai.com/v1',
        apiKey: (string) getenv('OPENAI_API_KEY'),
        endpoint: '/embeddings',
        model: 'text-embedding-3-small',
        dimensions: 1536,
    ),
    httpClient: $httpClient,
);

$embeddings = Embeddings::fromRuntime($runtime);
```


## Sharing an HTTP Client Across Runtimes

If your application uses both inference and embeddings, you can share a single HTTP client
between them to reuse connection pools and middleware configuration:

```php
<?php

use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Extras\Support\RetryPolicy;
use Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\Embeddings;
use Cognesy\Polyglot\Embeddings\EmbeddingsRuntime;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\InferenceRuntime;

$http = (new HttpClientBuilder())
    ->withRetryPolicy(new RetryPolicy(maxRetries: 3))
    ->create();

$inference = Inference::fromRuntime(
    InferenceRuntime::fromConfig(LLMConfig::fromPreset('openai'), httpClient: $http)
);

$embeddings = Embeddings::fromRuntime(
    EmbeddingsRuntime::fromConfig(EmbeddingsConfig::fromPreset('openai'), httpClient: $http)
);
```

This ensures both services share the same retry policy, circuit breaker state, and
connection pool configuration.
