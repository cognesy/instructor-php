---
title: Provider Abstraction Layer
description: 'How provider config, runtime assembly, and drivers fit together.'
---

Provider abstraction has four parts:

1. Provider builders (`LLMProvider`, `EmbeddingsProvider`)
2. Runtime assembly (`InferenceRuntime`, `EmbeddingsRuntime`)
3. Driver factories (`InferenceDriverFactory`, `EmbeddingsDriverFactory`)
4. Driver + adapter contracts

## Provider Builders

### LLMProvider

```php
<?php
use Cognesy\Polyglot\Inference\LLMProvider;

$provider = LLMProvider::using('openai')
    ->withConfigOverrides(['model' => 'gpt-4o']);

$config = $provider->resolveConfig();
```

Common methods:

- `using(...)`, `dsn(...)`, `new(...)`
- `withLLMPreset(...)`
- `withLLMConfig(...)`
- `withConfigOverrides(...)`
- `withDsn(...)`
- `withModel(...)`
- `withDriver(...)` (explicit inference driver instance)

### EmbeddingsProvider

```php
<?php
use Cognesy\Polyglot\Embeddings\EmbeddingsProvider;

$provider = EmbeddingsProvider::using('openai')
    ->withConfigProvider($configProvider);

$config = $provider->resolveConfig();
```

Common methods:

- `using(...)`, `dsn(...)`, `new(...)`
- `withPreset(...)`
- `withConfig(...)`
- `withDsn(...)`
- `withDriver(...)` (explicit embeddings driver instance)

## Runtime Assembly

```php
<?php
use Cognesy\Polyglot\Inference\InferenceRuntime;

$runtime = InferenceRuntime::fromProvider($provider);
```

Inference runtime factories:

- `fromProvider(...)`
- `fromResolver(...)`
- `fromConfig(...)`
- `using(...)`
- `fromDsn(...)`

Embeddings runtime provides the same entry points via `EmbeddingsRuntime`.

## Driver Contracts

Inference:

```php
interface CanProcessInferenceRequest {
    public function makeResponseFor(InferenceRequest $request): InferenceResponse;
    public function makeStreamResponsesFor(InferenceRequest $request): iterable;
    public function capabilities(?string $model = null): DriverCapabilities;
}
```

Embeddings:

```php
interface CanHandleVectorization {
    public function handle(EmbeddingsRequest $request): HttpResponse;
    public function fromData(array $data): ?EmbeddingsResponse;
}
```

## Adapter Contracts

Inference adapter boundary:

- `CanTranslateInferenceRequest::toHttpRequest(...)`
- `CanTranslateInferenceResponse::fromResponse(...)`
- `CanTranslateInferenceResponse::fromStreamResponses(...)`
- `CanTranslateInferenceResponse::toEventBody(...)`

Embeddings adapter boundary:

- `EmbedRequestAdapter::toHttpClientRequest(...)`
- `EmbedResponseAdapter::fromResponse(...)`

## Base Drivers

`BaseInferenceRequestDriver` and `BaseEmbedDriver` implement the transport pipeline:

1. map domain request to `HttpRequest`
2. execute through shared `HttpClient`
3. map raw response payload back to domain response
4. emit events on request/response/failure

## Driver Factories and Registry

Inference drivers are selected by `LLMConfig::$driver` in `InferenceDriverFactory`.
Custom drivers can be registered at runtime:

```php
<?php
use Cognesy\Polyglot\Inference\Inference;

Inference::registerDriver('my-driver', MyInferenceDriver::class);
Inference::unregisterDriver('my-driver');
Inference::resetDrivers();
```

Embeddings drivers are selected by `EmbeddingsConfig::$driver` in `EmbeddingsDriverFactory`.
Custom registration is available via:

```php
<?php
use Cognesy\Polyglot\Embeddings\Embeddings;

Embeddings::registerDriver('my-embed-driver', MyEmbeddingsDriver::class);
```
