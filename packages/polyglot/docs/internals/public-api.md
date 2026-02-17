---
title: Public API Layer
description: 'Explore the public API layer of Polyglot, including the Inference and Embeddings classes.'
---


## The Inference Class

The `Inference` class is the main entry point for LLM interactions. It encapsulates the complexities of different providers behind a unified interface.

```php
namespace Cognesy\Polyglot\Inference;

use Cognesy\Config\Contracts\CanProvideConfig;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Contracts\CanProcessInferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\InferenceRuntime;
use Psr\EventDispatcher\EventDispatcherInterface;

class Inference {
    public function __construct(
        null|CanHandleEvents|EventDispatcherInterface $events = null,
        ?CanProvideConfig $configProvider = null
    ) { ... }

    // Infrastructure/provider configuration
    public function using(string $preset): self { ... }
    public function withDsn(string $dsn): self { ... }
    public function withLLMConfig(LLMConfig $config): self { ... }
    public function withLLMConfigOverrides(array $overrides): self { ... }
    public function withConfigProvider(CanProvideConfig $configProvider): self { ... }
    public function withHttpClient(HttpClient $httpClient): self { ... }
    public function withDriver(CanProcessInferenceRequest $driver): self { ... }
    public function withHttpClientPreset(string $preset): self { ... }
    public function withHttpDebugPreset(?string $preset): self { ... }
    public function withEventHandler(CanHandleEvents|EventDispatcherInterface $events): self { ... }

    // Request building and execution
    public function with(...): self { ... }
    public function withRequest(InferenceRequest $request): self { ... }
    public function create(?InferenceRequest $request = null): PendingInference { ... }
    public function toRuntime(): InferenceRuntime { ... }

    // Convenience response accessors
    public function get(): string { ... }
    public function response(): InferenceResponse { ... }
    public function stream(): InferenceStream { ... }
}
```

The `Inference` class follows a fluent interface pattern, allowing method chaining for configuration.




## The Embeddings Class

Similarly, the `Embeddings` class provides a unified interface for generating embeddings:

```php
namespace Cognesy\Polyglot\Embeddings;

use Cognesy\Config\Contracts\CanProvideConfig;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Http\HttpClient;
use Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\Contracts\CanHandleVectorization;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsRequest;
use Cognesy\Polyglot\Embeddings\EmbeddingsRuntime;
use Psr\EventDispatcher\EventDispatcherInterface;

class Embeddings {
    public function __construct(
        null|CanHandleEvents|EventDispatcherInterface $events = null,
        ?CanProvideConfig $configProvider = null
    ) { ... }

    // Infrastructure/provider configuration
    public function using(string $preset): self { ... }
    public function withDsn(string $dsn): self { ... }
    public function withConfig(EmbeddingsConfig $config): self { ... }
    public function withConfigProvider(CanProvideConfig $configProvider): self { ... }
    public function withHttpClient(HttpClient $httpClient): self { ... }
    public function withDriver(CanHandleVectorization $driver): self { ... }
    public function withHttpDebugPreset(?string $preset): self { ... }

    // Request building and execution
    public function with(...): self { ... }
    public function withRequest(EmbeddingsRequest $request): self { ... }
    public function create(?EmbeddingsRequest $request = null): PendingEmbeddings { ... }
    public function toRuntime(): EmbeddingsRuntime { ... }

    // Convenience response accessors
    public function get(): EmbeddingsResponse { ... }
    public function first(): ?Vector { ... }
    public function vectors(): array { ... }
}
```

Similarity-search helper methods are provided by `EmbedUtils`, not by the `Embeddings` facade:

```php
use Cognesy\Polyglot\Embeddings\Utils\EmbedUtils;

$matches = EmbedUtils::findSimilar(
    embeddings: (new Embeddings())->using('openai')->toRuntime(),
    query: $query,
    documents: $documents,
    topK: 5,
);
```
