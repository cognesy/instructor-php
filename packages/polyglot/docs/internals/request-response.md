---
title: Request and Response Objects
description: 'Learn about the request and response objects used in Polyglot.'
---


## InferenceRequest

The `InferenceRequest` class encapsulates all the parameters needed for an LLM request:

```php
namespace Cognesy\Polyglot\LLM;

class InferenceRequest {
    public array $messages = [];
    public string $model = '';
    public array $tools = [];
    public string|array $toolChoice = [];
    public array $responseFormat = [];
    public array $options = [];
    public Mode $mode = OutputMode::Text;
    public ?CachedContext $cachedContext;

    public function __construct(...) { ... }

    // Getters
    public function messages(): array { ... }
    public function model(): string { ... }
    public function isStreamed(): bool { ... }
    public function tools(): array { ... }
    public function toolChoice(): string|array { ... }
    public function responseFormat(): array { ... }
    public function options(): array { ... }
    public function mode(): Mode { ... }
    public function cachedContext(): ?CachedContext { ... }

    // Fluent setters
    public function withMessages(string|array $messages): self { ... }
    public function withModel(string $model): self { ... }
    public function withStreaming(bool $streaming): self { ... }
    public function withTools(array $tools): self { ... }
    public function withToolChoice(string|array $toolChoice): self { ... }
    public function withResponseFormat(array $responseFormat): self { ... }
    public function withOptions(array $options): self { ... }
    public function withMode(Mode $mode): self { ... }
    public function withCachedContext(?CachedContext $cachedContext): self { ... }

    // Utility methods
    public function toArray(): array { ... }
    public function withCacheApplied(): self { ... }
}
```



## PendingInference

The `PendingInference` class handles the response from an LLM API:

```php
namespace Cognesy\Polyglot\LLM;

use Cognesy\Polyglot\Inference\Data\InferenceRequest;class PendingInference {
    public function __construct(
        InferenceRequest $request,
        CanHandleInference $driver,
        EventDispatcherInterface $events,
    ) { ... }

    // Access methods
    public function isStreamed(): bool { ... }
    public function toText(): string { ... }
    public function toArray(): array { ... }
    public function stream(): InferenceStream { ... }
    public function response(): InferenceResponse { ... }
}
```

For streaming responses, the `InferenceStream` class provides methods to process the stream:

```php
namespace Cognesy\Polyglot\LLM;

class InferenceStream {
    public function __construct(
        HttpResponse        $httpResponse,
        CanHandleInference        $driver,
        EventDispatcherInterface  $events,
    ) { ... }

    // Stream processing methods
    public function responses(): Generator { ... }
    public function all(): array { ... }
    public function final(): ?InferenceResponse { ... }
    public function onPartialResponse(callable $callback): self { ... }
}
```



## EmbeddingsResponse

The `EmbeddingsResponse` class encapsulates the result of an embeddings request:

```php
namespace Cognesy\Polyglot\Embeddings;

class EmbeddingsResponse {
    public function __construct(
        public array $vectors,
        public ?Usage $usage
    ) { ... }

    // Access methods
    public function first(): Vector { ... }
    public function last(): Vector { ... }
    public function all(): array { ... }
    public function usage(): Usage { ... }
    public function toValuesArray(): array { ... }
    public function totalTokens(): int { ... }
    public function split(int $index): array { ... }
}
```
