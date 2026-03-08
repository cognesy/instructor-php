---
title: Public API Layer
description: 'Explore the public API layer of Polyglot, including the Inference and Embeddings classes.'
---


## The Inference Class

The `Inference` class is the main entry point for LLM interactions. It encapsulates the complexities of different providers behind a unified interface.

```php
namespace Cognesy\Polyglot\Inference;

use Cognesy\Polyglot\Inference\Contracts\CanCreateInference;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\LLMProvider;

class Inference {
    public function __construct(?CanCreateInference $runtime = null) { ... }

    // Runtime selection
    public static function using(string $preset): self { ... }
    public static function fromConfig(LLMConfig $config): self { ... }
    public static function fromProvider(LLMProvider $provider): self { ... }
    public static function fromRuntime(CanCreateInference $runtime): self { ... }
    public function withRuntime(CanCreateInference $runtime): self { ... }

    // Request building and execution
    public function with(...): self { ... }
    public function withRequest(InferenceRequest $request): self { ... }
    public function create(?InferenceRequest $request = null): PendingInference { ... }

    // Convenience response accessors
    public function get(): string { ... }
    public function response(): InferenceResponse { ... }
    public function stream(): InferenceStream { ... }
}
```

The `Inference` class follows a fluent interface pattern for request building; infrastructure is assembled in `InferenceRuntime`.




## The Embeddings Class

Similarly, the `Embeddings` class provides a unified interface for generating embeddings:

```php
namespace Cognesy\Polyglot\Embeddings;

use Cognesy\Polyglot\Embeddings\Contracts\CanCreateEmbeddings;
use Cognesy\Polyglot\Embeddings\Config\EmbeddingsConfig;
use Cognesy\Polyglot\Embeddings\Data\EmbeddingsRequest;
use Cognesy\Polyglot\Embeddings\EmbeddingsProvider;

class Embeddings {
    public function __construct(?CanCreateEmbeddings $runtime = null) { ... }

    // Runtime selection
    public static function using(string $preset): self { ... }
    public static function fromConfig(EmbeddingsConfig $config): self { ... }
    public static function fromProvider(EmbeddingsProvider $provider): self { ... }
    public static function fromRuntime(CanCreateEmbeddings $runtime): self { ... }
    public function withRuntime(CanCreateEmbeddings $runtime): self { ... }

    // Request building and execution
    public function with(...): self { ... }
    public function withRequest(EmbeddingsRequest $request): self { ... }
    public function create(?EmbeddingsRequest $request = null): PendingEmbeddings { ... }

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
    embeddings: EmbeddingsRuntime::fromConfig(EmbeddingsConfig::fromPreset('openai')),
    query: $query,
    documents: $documents,
    topK: 5,
);
```
