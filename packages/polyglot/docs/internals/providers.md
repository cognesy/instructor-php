---
title: "Provider Abstraction Layer"
description: 'Learn about the provider abstraction layer in Polyglot.'
---

The provider abstraction layer is where Polyglot handles the differences between LLM providers. This layer includes:

1. **Drivers**: Classes that implement provider-specific logic
2. **Adapters**: Classes that convert between unified and provider-specific formats
3. **Formatters**: Classes that handle specific aspects of request/response formatting



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



## Key Interface for Embeddings

The embeddings functionality uses a simpler interface:

```php
namespace Cognesy\Polyglot\Embeddings\Contracts;

interface CanVectorize {
    public function vectorize(array $input, array $options = []): EmbeddingsResponse;
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



## InferenceDriverFactory

The `InferenceDriverFactory` creates the appropriate driver for each provider:

```php
namespace Cognesy\Polyglot\Inference\Drivers;

class InferenceDriverFactory {
    public function make(
        LLMConfig $config,
        CanHandleHttpRequest $httpClient,
        EventDispatcher $events
    ): CanHandleInference { ... }

    // Provider-specific factory methods
    public function openAI(...): CanHandleInference { ... }
    public function anthropic(...): CanHandleInference { ... }
    public function mistral(...): CanHandleInference { ... }
    // Other providers...
}
```
