---
title: "Provider Abstraction Layer"
description: 'Learn about the provider abstraction layer in Polyglot.'
---

The provider abstraction layer is where Polyglot handles the differences between LLM and embedding providers. This layer includes:

1. **Provider Classes**: `LLMProvider` and `EmbeddingsProvider` - Builder classes for configuring and resolving driver configurations
2. **Drivers**: Classes that implement provider-specific logic for inference and embeddings
3. **Adapters**: Classes that convert between unified and provider-specific formats
4. **Factories**: Classes that create appropriate drivers based on configuration


## Provider Builder Classes

### LLMProvider

The `LLMProvider` class is a builder that configures inference settings and resolves configuration from various sources. It provides a fluent interface for setting up LLM configurations:

```php
<?php
use Cognesy\Polyglot\Inference\LLMProvider;

// Create with preset
$provider = LLMProvider::using('openai');

// Create with DSN
$provider = LLMProvider::dsn('openai://model=gpt-4&temperature=0.7');

// Fluent configuration
$provider = LLMProvider::new()
    ->withLLMPreset('openai')
    ->withLLMConfig($customConfig);

// Create the final driver using a factory and injected HTTP client
$httpClient = (new \Cognesy\Http\Creation\HttpClientBuilder())->create();
$config = $provider->resolveConfig();
$driver = (new \Cognesy\Polyglot\Inference\Creation\InferenceDriverFactory($events))
    ->makeDriver($config, $httpClient);
```

Key methods:
- `withLLMPreset(string $preset)`: Set configuration preset
- `withLLMConfig(LLMConfig $config)`: Set explicit configuration
- `withConfigOverrides(array $overrides)`: Override specific config values
- `withDsn(string $dsn)`: Configure via DSN string
- `withDriver(CanHandleInference $driver)`: Set explicit driver
- Use `resolveConfig()` + `InferenceDriverFactory::makeDriver()` to create drivers

### EmbeddingsProvider

The `EmbeddingsProvider` class builds and configures embeddings settings, resolving configuration from various sources:

```php
<?php
use Cognesy\Polyglot\Embeddings\EmbeddingsProvider;

// Create with preset
$provider = EmbeddingsProvider::using('openai');

// Create with DSN
$provider = EmbeddingsProvider::dsn('openai://model=text-embedding-3-large');

// Fluent configuration
$provider = EmbeddingsProvider::new()
    ->withPreset('openai')
    ->withConfig($customConfig);

// Create the final driver using a factory and injected HTTP client
$httpClient = (new \Cognesy\Http\Creation\HttpClientBuilder())->create();
$config = $provider->resolveConfig();
$driver = (new \Cognesy\Polyglot\Embeddings\Drivers\EmbeddingsDriverFactory($events))
    ->makeDriver($config, $httpClient);
```

Key methods:
- `withPreset(string $preset)`: Set configuration preset
- `withConfig(EmbeddingsConfig $config)`: Set explicit configuration
- `withDsn(string $dsn)`: Configure via DSN string
- `withDriver(CanHandleVectorization $driver)`: Set explicit driver
- Use `resolveConfig()` + `EmbeddingsDriverFactory::makeDriver()` to create drivers


## Key Interfaces for LLM

Several interfaces define the contract for LLM drivers and adapters:

```php
namespace Cognesy\Polyglot\Inference\Contracts;

interface CanHandleInference {
    public function makeResponseFor(InferenceRequest $request): InferenceResponse;
    /** @return iterable<PartialInferenceResponse> */
    public function makeStreamResponsesFor(InferenceRequest $request): iterable;
    public function capabilities(?string $model = null): DriverCapabilities;
}

interface CanTranslateInferenceRequest {
    public function toHttpRequest(InferenceRequest $request): HttpRequest;
}

interface CanTranslateInferenceResponse {
    public function fromResponse(HttpResponse $response): ?InferenceResponse;
    /** @return iterable<PartialInferenceResponse> */
    public function fromStreamResponses(iterable $eventBodies, ?HttpResponse $responseData = null): iterable;
    public function toEventBody(string $data): string|bool;
}

interface CanMapMessages {
    public function map(array $messages): array;
}

interface CanMapRequestBody {
    public function toRequestBody(InferenceRequest $request): array;
}

interface CanMapUsage {
    public function fromData(array $data): Usage;
}
```



## Key Interfaces for Embeddings

The embeddings functionality uses these key interfaces:

```php
namespace Cognesy\Polyglot\Embeddings\Contracts;

// Main driver interface
interface CanHandleVectorization {
    public function vectorize(EmbeddingsRequest $request): EmbeddingsResponse;
}

// Request and response mapping interfaces
interface CanMapRequestBody {
    public function map(EmbeddingsRequest $request): array;
}

interface EmbedRequestAdapter {
    public function toHttpRequest(EmbeddingsRequest $request): HttpRequest;
}

interface EmbedResponseAdapter {
    public function fromHttpResponse(HttpResponse $response): EmbeddingsResponse;
}

interface CanMapUsage {
    public function fromData(array $data): Usage;
}
```



## BaseInferenceDriver

The `BaseInferenceDriver` is the abstract base class that implements `CanHandleInference` using request/response translators:

```php
namespace Cognesy\Polyglot\Inference\Drivers;

abstract class BaseInferenceDriver implements CanHandleInference {
    protected LLMConfig $config;
    protected HttpClient $httpClient;
    protected EventDispatcherInterface $events;
    protected CanTranslateInferenceRequest $requestTranslator;
    protected CanTranslateInferenceResponse $responseTranslator;

    public function makeResponseFor(InferenceRequest $request): InferenceResponse { ... }
    /** @return iterable<PartialInferenceResponse> */
    public function makeStreamResponsesFor(InferenceRequest $request): iterable { ... }
    public function capabilities(?string $model = null): DriverCapabilities { ... }
}
```

Provider-specific drivers (e.g. `OpenAIDriver`, `AnthropicDriver`) extend `BaseInferenceDriver` and wire up their own request/response translators in the constructor.



## Driver Factories

### InferenceDriverFactory

The `InferenceDriverFactory` creates the appropriate driver for each LLM provider:

```php
namespace Cognesy\Polyglot\Inference\Drivers;

class InferenceDriverFactory {
    public function makeDriver(
        LLMConfig $config,
        HttpClient $httpClient
    ): CanHandleInference { ... }

    // Provider-specific factory methods
    public function openAI(...): CanHandleInference { ... }
    public function anthropic(...): CanHandleInference { ... }
    public function mistral(...): CanHandleInference { ... }
    // Other providers...
    
    // Driver registration
    public static function registerDriver(string $name, string|callable $driver): void { ... }
}
```

### EmbeddingsDriverFactory

The `EmbeddingsDriverFactory` creates embeddings drivers:

```php
namespace Cognesy\Polyglot\Embeddings\Drivers;

class EmbeddingsDriverFactory {
    public function makeDriver(
        EmbeddingsConfig $config,
        HttpClient $httpClient
    ): CanHandleVectorization { ... }

    // Provider-specific factory methods  
    public function openAI(...): CanHandleVectorization { ... }
    public function cohere(...): CanHandleVectorization { ... }
    public function gemini(...): CanHandleVectorization { ... }
    // Other providers...
    
    // Driver registration
    public static function registerDriver(string $name, string|callable $driver): void { ... }
}
```
