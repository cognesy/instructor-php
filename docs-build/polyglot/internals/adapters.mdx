---
title: Adapters
description: 'Learn about the adapters used in Polyglot for LLM providers.'
---

Each provider has a set of adapters that handle its specific format requirements:


## Request Adapters

Request adapters convert Polyglot's unified request format to provider-specific HTTP requests:

```php
namespace Cognesy\Polyglot\LLM\Drivers\OpenAI;

class OpenAIRequestAdapter implements ProviderRequestAdapter {
    public function __construct(
        protected LLMConfig $config,
        protected CanMapRequestBody $bodyFormat
    ) { ... }

    public function toHttpClientRequest(
        array $messages,
        string $model,
        array $tools,
        array|string $toolChoice,
        array $responseFormat,
        array $options,
        Mode $mode
    ): HttpClientRequest { ... }

    protected function toHeaders(): array { ... }
    protected function toUrl(string $model = '', bool $stream = false): string { ... }
}
```

### Message Formatters

Message formatters handle the conversion of messages to provider-specific formats:

```php
namespace Cognesy\Polyglot\LLM\Drivers\OpenAI;

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
namespace Cognesy\Polyglot\LLM\Drivers\OpenAI;

class OpenAIBodyFormat implements CanMapRequestBody {
    public function __construct(
        protected LLMConfig $config,
        protected CanMapMessages $messageFormat
    ) { ... }

    public function map(
        array $messages,
        string $model,
        array $tools,
        array|string $toolChoice,
        array $responseFormat,
        array $options,
        Mode $mode
    ): array { ... }

    private function applyMode(
        array $request,
        Mode $mode,
        array $tools,
        string|array $toolChoice,
        array $responseFormat
    ): array { ... }
}
```


## Response Adapters

Response adapters convert provider-specific responses to Polyglot's unified format:

```php
namespace Cognesy\Polyglot\LLM\Drivers\OpenAI;

class OpenAIResponseAdapter implements ProviderResponseAdapter {
    public function __construct(
        protected CanMapUsage $usageFormat
    ) { ... }

    public function fromResponse(array $data): ?LLMResponse { ... }
    public function fromStreamResponse(array $data): ?PartialLLMResponse { ... }
    public function fromStreamData(string $data): string|bool { ... }

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
namespace Cognesy\Polyglot\LLM\Drivers\OpenAI;

class OpenAIUsageFormat implements CanMapUsage {
    public function fromData(array $data): Usage { ... }
}
```



