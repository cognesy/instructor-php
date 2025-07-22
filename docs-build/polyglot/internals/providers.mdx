---
title: "Provider Abstraction Layer"
description: 'Learn about the provider abstraction layer in Polyglot.'
---

The provider abstraction layer is where Polyglot handles the differences between LLM and embedding providers. This layer includes:

1. **Provider Classes**: `LLMProvider` and `EmbeddingsProvider` - Builder classes for configuring and creating drivers
2. **Drivers**: Classes that implement provider-specific logic for inference and embeddings
3. **Adapters**: Classes that convert between unified and provider-specific formats
4. **Factories**: Classes that create appropriate drivers based on configuration


## Provider Builder Classes

### LLMProvider

The `LLMProvider` class is a builder that configures and creates inference drivers. It provides a fluent interface for setting up LLM configurations:

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
    ->withConfig($customConfig)
    ->withHttpClient($httpClient)
    ->withDebugPreset('verbose');

// Create the final driver
$driver = $provider->createDriver();
```

Key methods:
- `withLLMPreset(string $preset)`: Set configuration preset
- `withConfig(LLMConfig $config)`: Set explicit configuration
- `withConfigOverrides(array $overrides)`: Override specific config values
- `withDsn(string $dsn)`: Configure via DSN string
- `withHttpClient(HttpClient $client)`: Set custom HTTP client
- `withDriver(CanHandleInference $driver)`: Set explicit driver
- `createDriver()`: Build and return the configured driver

### EmbeddingsProvider

The `EmbeddingsProvider` class builds and configures embeddings drivers:

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
    ->withConfig($customConfig)
    ->withHttpClient($httpClient)
    ->withDebugPreset('verbose');

// Create the final driver
$driver = $provider->createDriver();
```

Key methods:
- `withPreset(string $preset)`: Set configuration preset
- `withConfig(EmbeddingsConfig $config)`: Set explicit configuration
- `withDsn(string $dsn)`: Configure via DSN string
- `withHttpClient(HttpClient $client)`: Set custom HTTP client
- `withDriver(CanHandleVectorization $driver)`: Set explicit driver
- `createDriver()`: Build and return the configured driver


## Key Interfaces for LLM

Several interfaces define the contract for LLM drivers and adapters:

```php
namespace Cognesy\Polyglot\Inference\Contracts;

interface CanHandleInference {
    public function handle(InferenceRequest $request): HttpResponse;
    public function fromResponse(HttpResponse $response): ?InferenceResponse;
    public function fromStreamResponse(string $eventBody): ?PartialInferenceResponse;
    public function toEventBody(string $data): string|bool;
}

interface ProviderRequestAdapter {
    public function toHttpClientRequest(
        array $messages,
        string $model,
        array $tools,
        string|array $toolChoice,
        array $responseFormat,
        array $options,
        Mode $mode
    ): HttpRequest;
}

interface ProviderResponseAdapter {
    public function fromResponse(HttpResponse $response): ?InferenceResponse;
    public function fromStreamResponse(string $eventBody): ?PartialInferenceResponse;
    public function toEventBody(string $data): string|bool;
}

interface CanMapMessages {
    public function map(array $messages): array;
}

interface CanMapRequestBody {
    public function map(
        array $messages,
        string $model,
        array $tools,
        array|string $toolChoice,
        array $responseFormat,
        array $options,
        Mode $mode
    ): array;
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



## ModularLLMDriver

The `ModularLLMDriver` is a central component that implements the `CanHandleInference` interface using adapters:

```php
namespace Cognesy\Polyglot\Inference\Drivers;

class ModularLLMDriver implements CanHandleInference {
    public function __construct(
        protected LLMConfig $config,
        protected ProviderRequestAdapter $requestAdapter,
        protected ProviderResponseAdapter $responseAdapter,
        protected ?CanHandleHttpRequest $httpClient = null,
        protected ?EventDispatcher $events = null
    ) { ... }

    public function handle(InferenceRequest $request): HttpResponse { ... }
    public function fromResponse(HttpResponse $response): ?InferenceResponse { ... }
    public function fromStreamResponse(string $eventBody): ?PartialInferenceResponse { ... }
    public function toEventBody(string $data): string|bool { ... }
}
```



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
