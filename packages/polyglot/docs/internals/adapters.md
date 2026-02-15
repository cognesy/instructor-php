---
title: Adapters
description: 'Learn about the adapters used in Polyglot for LLM providers.'
---

Each provider has a set of adapters that handle its specific format requirements:


## Request Adapters

Request adapters convert Polyglot's unified request format to provider-specific HTTP requests:

```php
namespace Cognesy\Polyglot\Inference\Drivers\OpenAI;

class OpenAIRequestAdapter implements CanTranslateInferenceRequest {
    public function __construct(
        protected LLMConfig $config,
        protected CanMapRequestBody $bodyFormat,
    ) { ... }

    public function toHttpRequest(InferenceRequest $request): HttpRequest { ... }

    protected function toHeaders(InferenceRequest $request): array { ... }
    protected function toUrl(InferenceRequest $request): string { ... }
}
```

### Message Formatters

Message formatters handle the conversion of messages to provider-specific formats:

```php
namespace Cognesy\Polyglot\Inference\Drivers\OpenAI;

class OpenAIMessageFormat implements CanMapMessages {
    public function map(array $messages): array { ... }

    protected function mapMessage(array $message): array { ... }
    protected function toNativeToolCall(array $message): array { ... }
    protected function toNativeToolResult(array $message): array { ... }
}
```


### Body Formatters

Body formatters handle the conversion of request bodies to provider-specific formats:

```php
namespace Cognesy\Polyglot\Inference\Drivers\OpenAI;

class OpenAIBodyFormat implements CanMapRequestBody {
    public function __construct(
        protected LLMConfig $config,
        protected CanMapMessages $messageFormat,
    ) { ... }

    public function toRequestBody(InferenceRequest $request): array { ... }
}
```


## Response Adapters

Response adapters convert provider-specific responses to Polyglot's unified format:

```php
namespace Cognesy\Polyglot\Inference\Drivers\OpenAI;

class OpenAIResponseAdapter implements CanTranslateInferenceResponse {
    public function __construct(
        protected CanMapUsage $usageFormat,
    ) { ... }

    public function fromResponse(HttpResponse $response): ?InferenceResponse { ... }
    /** @return iterable<PartialInferenceResponse> */
    public function fromStreamResponses(iterable $eventBodies, ?HttpResponse $responseData = null): iterable { ... }
    public function toEventBody(string $data): string|bool { ... }

    protected function makeToolCalls(array $data): ToolCalls { ... }
    protected function makeContent(array $data): string { ... }
    protected function makeContentDelta(array $data): string { ... }
    protected function makeToolId(array $data): string { ... }
    protected function makeToolNameDelta(array $data): string { ... }
    protected function makeToolArgsDelta(array $data): string { ... }
}
```



### Usage Formatters

Usage formatters extract token usage information from provider responses:

```php
namespace Cognesy\Polyglot\Inference\Drivers\OpenAI;

class OpenAIUsageFormat implements CanMapUsage {
    public function fromData(array $data): Usage { ... }
}
```



