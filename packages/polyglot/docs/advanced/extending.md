---
title: Extending Polyglot
description: 'How to add custom inference/embeddings drivers and HTTP middleware.'
---

Use these extension points:

1. Register a custom inference driver
2. Register a custom embeddings driver
3. Inject a custom HTTP client / middleware

## Register a Custom Inference Driver

Register by class-string:

```php
<?php
use Cognesy\Polyglot\Inference\Inference;
use Acme\Polyglot\Drivers\AcmeInferenceDriver;

Inference::registerDriver('acme', AcmeInferenceDriver::class);
```

Or register by factory callback:

```php
<?php
use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanProcessInferenceRequest;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIDriver;
use Cognesy\Polyglot\Inference\Inference;
use Psr\EventDispatcher\EventDispatcherInterface;

Inference::registerDriver(
    'openai-custom',
    function (
        LLMConfig $config,
        HttpClient $httpClient,
        EventDispatcherInterface $events
    ): CanProcessInferenceRequest {
        return new OpenAIDriver($config, $httpClient, $events);
    }
);
```

Use it by setting `driver` in `LLMConfig` or by preset config.

Cleanup helpers (important in tests/workers):

```php
Inference::unregisterDriver('openai-custom');
Inference::resetDrivers();
```

## Register a Custom Embeddings Driver

```php
<?php
use Cognesy\Polyglot\Embeddings\Embeddings;
use Acme\Polyglot\Drivers\AcmeEmbeddingsDriver;

Embeddings::registerDriver('acme-embed', AcmeEmbeddingsDriver::class);
```

Embeddings driver constructors are expected to follow factory wiring:

- `(EmbeddingsConfig $config, HttpClient $httpClient, EventDispatcherInterface $events)`

## Implementing Driver Contracts

Inference drivers implement:

- `CanProcessInferenceRequest`
- typically by extending `BaseInferenceRequestDriver`

Embeddings drivers implement:

- `CanHandleVectorization`
- typically by extending `BaseEmbedDriver`

## Inject Custom HTTP Middleware

Polyglot uses `Cognesy\Http\HttpClient`. Add middleware there, then inject into runtime.

```php
<?php
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\InferenceRuntime;

$httpClient = (new HttpClientBuilder())
    ->withPreset('guzzle')
    ->create()
    ->withMiddleware(new YourCustomMiddleware());

$inference = Inference::fromRuntime(
    InferenceRuntime::using(
        preset: 'openai',
        httpClient: $httpClient,
    )
);
```

`HttpClient` is immutable. Always keep the returned instance from `withMiddleware(...)`.
