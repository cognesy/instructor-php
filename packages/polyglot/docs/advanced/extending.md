---
title: Extending Polyglot
description: 'How to add custom inference/embeddings drivers and HTTP middleware.'
---

Use these extension points:

1. Extend the inference driver registry for one runtime
2. Register a custom embeddings driver
3. Inject a custom HTTP client / middleware

## Extend the Inference Driver Registry

Register by class-string:

```php
<?php
use Cognesy\Polyglot\Inference\Creation\BundledInferenceDrivers;
use Acme\Polyglot\Drivers\AcmeInferenceDriver;

$drivers = BundledInferenceDrivers::registry()
    ->withDriver('acme', AcmeInferenceDriver::class);
```

Or register by factory callback:

```php
<?php
use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanProcessInferenceRequest;
use Cognesy\Polyglot\Inference\Creation\BundledInferenceDrivers;
use Cognesy\Polyglot\Inference\Drivers\OpenAI\OpenAIDriver;
use Psr\EventDispatcher\EventDispatcherInterface;

$drivers = BundledInferenceDrivers::registry()->withDriver(
    'openai-custom',
    function (
        LLMConfig $config,
        HttpClient $httpClient,
        EventDispatcherInterface $events
    ): CanProcessInferenceRequest {
        return new OpenAIDriver($config, $httpClient, $events);
    },
);
```

Use it by passing the registry to the runtime:

```php
use Cognesy\Polyglot\Inference\InferenceRuntime;

$runtime = InferenceRuntime::fromConfig(
    config: LLMConfig::fromArray(['driver' => 'openai-custom']),
    drivers: $drivers,
);
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
    ->withConfig(EmbeddingsConfig::fromArray(['driver' => 'guzzle']))
    ->create()
    ->withMiddleware(new YourCustomMiddleware());

$inference = Inference::fromRuntime(
    InferenceRuntime::fromConfig(
        config: LLMConfig::fromArray(['driver' => 'openai']),
        httpClient: $httpClient,
    )
);
```

`HttpClient` is immutable. Always keep the returned instance from `withMiddleware(...)`.
