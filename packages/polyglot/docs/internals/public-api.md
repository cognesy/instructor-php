---
title: Public API Layer
description: 'Explore the public API layer of Polyglot, including the Inference and Embeddings classes.'
---


## The Inference Class

The `Inference` class is the main entry point for LLM interactions. It encapsulates the complexities of different providers behind a unified interface.

```php
namespace Cognesy\Polyglot\LLM;

class Inference {
    // Create and manage the LLM instance
    public function __construct(
        string $connection = '',
        LLMConfig $config = null,
        CanHandleHttpRequest $httpClient = null,
        CanHandleInference $driver = null,
        EventDispatcher $events = null
    ) { ... }

    // Configure the instance
    public function withConnection(string $connection): self { ... }
    public function withConfig(LLMConfig $config): self { ... }
    public function withHttpClient(CanHandleHttpRequest $httpClient): self { ... }
    public function withDriver(CanHandleInference $driver): self { ... }
    public function withDebug(bool $debug = true): self { ... }
    public function withCachedContext(...): self { ... }

    // Main method for creating inference requests
    public function create(
        string|array $messages = [],
        string $model = '',
        array $tools = [],
        string|array $toolChoice = [],
        array $responseFormat = [],
        array $options = [],
        Mode $mode = Mode::Text
    ): InferenceResponse { ... }

    // Static convenience method for simple text generation
    public static function text(
        string|array $messages,
        string $connection = '',
        string $model = '',
        array $options = []
    ): string { ... }
}
```

The `Inference` class follows a fluent interface pattern, allowing method chaining for configuration.




## The Embeddings Class

Similarly, the `Embeddings` class provides a unified interface for generating embeddings:

```php
namespace Cognesy\Polyglot\Embeddings;

class Embeddings {
    public function __construct(
        string $connection = '',
        EmbeddingsConfig $config = null,
        CanHandleHttpRequest $httpClient = null,
        CanVectorize $driver = null,
        EventDispatcher $events = null
    ) { ... }

    // Configuration methods
    public function withConnection(string $connection): self { ... }
    public function withConfig(EmbeddingsConfig $config): self { ... }
    public function withModel(string $model): self { ... }
    public function withHttpClient(CanHandleHttpRequest $httpClient): self { ... }
    public function withDriver(CanVectorize $driver): self { ... }

    // Main method for generating embeddings
    public function create(string|array $input, array $options = []): EmbeddingsResponse { ... }

    // Utility methods for finding similar content
    public function findSimilar(string $query, array $documents, int $topK = 5): array { ... }
}
```
