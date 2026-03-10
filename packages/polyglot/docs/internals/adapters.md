---
title: Adapters
description: How drivers translate between Polyglot data and provider payloads.
---

Polyglot drivers are composed from small, focused adapter classes. Each adapter handles one aspect of the translation between Polyglot's unified data model and a provider's native HTTP format. This composition makes it straightforward to add new providers -- most of the logic is shared, and only the provider-specific differences need new code.


## Adapter Responsibilities

Every inference driver is built from two main translators, each of which may use additional formatters internally:

### Request Translation

The request adapter converts a Polyglot `InferenceRequest` into an `HttpRequest`. It is responsible for:

- **Message formatting** -- mapping Polyglot's message array (with roles, content parts, tool calls, and tool results) into the provider's expected structure
- **Body formatting** -- assembling the full request body including model, tools, response format, and mode-specific adjustments
- **HTTP request assembly** -- setting the URL, headers (including authentication), and body

These responsibilities are typically split across three classes:

| Class Pattern | Contract | Purpose |
|---|---|---|
| `*MessageFormat` | `CanMapMessages` | Maps message arrays to provider format |
| `*BodyFormat` | `CanMapRequestBody` | Assembles the full request body |
| `*RequestAdapter` | `CanTranslateInferenceRequest` | Builds the final `HttpRequest` |

### Response Translation

The response adapter converts raw HTTP responses back into Polyglot data objects:

| Class Pattern | Contract | Purpose |
|---|---|---|
| `*ResponseAdapter` | `CanTranslateInferenceResponse` | Parses responses and stream deltas |
| `*UsageFormat` | `CanMapUsage` | Extracts token usage from response data |


## How They Compose

Each driver wires its adapters together in its constructor. Here is the OpenAI driver as an example:

```php
class OpenAIDriver extends BaseInferenceRequestDriver
{
    public function __construct(
        LLMConfig $config,
        CanSendHttpRequests $httpClient,
        EventDispatcherInterface $events,
    ) {
        parent::__construct(
            config: $config,
            httpClient: $httpClient,
            events: $events,
            requestTranslator: new OpenAIRequestAdapter(
                $config,
                new OpenAIBodyFormat($config, new OpenAIMessageFormat()),
            ),
            responseTranslator: new OpenAIResponseAdapter(
                new OpenAIUsageFormat(),
            ),
        );
    }
}
```

The `BaseInferenceRequestDriver` handles the shared execution logic -- sending HTTP requests, reading responses, and parsing event streams. The adapters only need to handle format translation.


## The Contracts

### Request Side

The `CanTranslateInferenceRequest` contract defines a single method:

```php
interface CanTranslateInferenceRequest
{
    public function toHttpRequest(InferenceRequest $request): HttpRequest;
}
```

Request adapters typically delegate body construction to a `CanMapRequestBody` implementation:

```php
interface CanMapRequestBody
{
    public function toRequestBody(InferenceRequest $request): array;
}
```

Message formatting is handled by `CanMapMessages`:

```php
interface CanMapMessages
{
    public function map(array $messages): array;
}
```

A typical request adapter composes these together. For example, `OpenAIRequestAdapter` receives a `CanMapRequestBody` (which itself wraps a `CanMapMessages`), then builds the final HTTP request with URL, headers, and the formatted body:

```php
class OpenAIRequestAdapter implements CanTranslateInferenceRequest
{
    public function __construct(
        protected LLMConfig $config,
        protected CanMapRequestBody $bodyFormat,
    ) {}

    public function toHttpRequest(InferenceRequest $request): HttpRequest
    {
        return new HttpRequest(
            url: "{$this->config->apiUrl}{$this->config->endpoint}",
            method: 'POST',
            headers: [
                'Authorization' => "Bearer {$this->config->apiKey}",
                'Content-Type' => 'application/json; charset=utf-8',
            ],
            body: $this->bodyFormat->toRequestBody($request),
            options: ['stream' => $request->isStreamed()],
        );
    }
}
```

### Response Side

The `CanTranslateInferenceResponse` contract handles both synchronous and streaming responses:

```php
interface CanTranslateInferenceResponse
{
    public function fromResponse(HttpResponse $response): ?InferenceResponse;

    /** @return iterable<PartialInferenceDelta> */
    public function fromStreamDeltas(
        iterable $eventBodies,
        ?HttpResponse $responseData = null,
    ): iterable;

    public function toEventBody(string $data): string|bool;
}
```

The `toEventBody()` method extracts the payload from an SSE line (stripping the `data:` prefix, detecting `[DONE]` markers). The `fromStreamDeltas()` method parses a sequence of those payloads into `PartialInferenceDelta` objects carrying incremental content, tool call fragments, and usage snapshots.

Usage extraction is handled by `CanMapUsage`:

```php
interface CanMapUsage
{
    public function fromData(array $data): Usage;
}
```

Different providers report token usage under different keys and with different granularity. Some include cache tokens or reasoning tokens, others do not. Each provider's usage formatter encapsulates these differences into the normalized `Usage` object.


## Embeddings Adapters

Embeddings drivers follow the same pattern with their own set of contracts:

| Contract | Purpose |
|---|---|
| `EmbedRequestAdapter` | Converts `EmbeddingsRequest` to `HttpRequest` |
| `EmbedResponseAdapter` | Converts `HttpResponse` to `EmbeddingsResponse` |
| `CanMapRequestBody` | Assembles the embeddings request body |
| `CanMapUsage` | Extracts usage from embeddings response data |


## Adding a New Provider

To add support for a new provider, you typically need to create:

1. A **message format** class if the provider uses a non-OpenAI message structure
2. A **body format** class to assemble requests with any provider-specific fields
3. A **request adapter** to set the URL, headers, and authentication scheme
4. A **response adapter** to parse responses and streaming events
5. A **usage format** class if token usage is reported differently
6. A **driver** class that wires these adapters together and extends `BaseInferenceRequestDriver`

Many providers use OpenAI-compatible formats. In those cases, you can often reuse the OpenAI adapters directly or extend them with minimal overrides. The `OpenAICompatibleDriver` is designed exactly for this purpose -- drivers like `ollama`, `together`, and `moonshot` all map to it in the bundled driver registry.
