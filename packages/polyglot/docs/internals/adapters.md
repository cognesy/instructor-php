---
title: Adapters
description: How drivers translate between Polyglot data and provider payloads.
---

Polyglot drivers are composed from small, focused adapter classes. Each adapter handles one aspect of the translation between Polyglot's unified data model and a provider's native HTTP format. This composition makes it straightforward to add new providers -- most of the logic is shared, and only the provider-specific differences need new code.


## Adapter Responsibilities

Every inference driver is built from two main translators, each of which may use additional formatters internally:

### Request Translation

The request adapter converts a Polyglot `InferenceRequest` into an `HttpRequest`. It is responsible for:

- **Message formatting** -- mapping Polyglot's typed `Messages` (with roles, content parts, tool calls, and tool results) into the provider's expected structure
- **Body formatting** -- assembling the full request body including model, tools, response format, and mode-specific adjustments
- **HTTP request assembly** -- setting the URL, headers (including authentication), and body

These responsibilities are typically split across three classes:

| Class Pattern | Contract | Purpose |
|---|---|---|
| `*MessageFormat` | `CanMapMessages` | Maps `Messages` to provider format |
| `*BodyFormat` | `CanMapRequestBody` | Assembles the full request body |
| `*RequestAdapter` | `CanTranslateInferenceRequest` | Builds the final `HttpRequest` |

### Response Translation

The response adapter converts raw HTTP responses back into Polyglot data objects:

| Class Pattern | Contract | Purpose |
|---|---|---|
| `*ResponseAdapter` | `CanTranslateInferenceResponse` | Parses responses and stream deltas |
| `*UsageFormat` | `CanMapUsage` | Extracts token usage from response data |


## How They Compose

For most providers the wiring is not code at all -- it is a row in the bundled registry. An
`InferenceDriverSpec` names the pieces, and `SpecifiedInferenceDriver` is the single class
behind every provider declared this way:

```php
$table = [
    'openai' => new InferenceDriverSpec(bodyFormat: OpenAIBodyFormat::class),
    'groq' => new InferenceDriverSpec(
        bodyFormat: GroqBodyFormat::class,
        usageFormat: GroqUsageFormat::class,
        capabilities: new DriverCapabilities(responseFormatWithTools: false),
    ),
];
```

Anything left out defaults to the OpenAI implementation, so a provider that differs only in its
body format is one line. The spec assembles them in the fixed nesting order every driver used:

```php
$driver = new SpecifiedInferenceDriver(
    config: $config,
    httpClient: $httpClient,
    events: $events,
    requestTranslator: new OpenAIRequestAdapter(
        $config,
        new OpenAIBodyFormat($config, new OpenAIMessageFormat()),
    ),
    responseTranslator: new OpenAIResponseAdapter(new OpenAIUsageFormat()),
    capabilities: null,
);
```

Providers that assemble their own URL or headers still use bespoke request adapters, but they
are selected by the `requestAdapter` field in their `InferenceDriverSpec`; they do not need a
provider driver class. The adapter owns that provider-specific behavior while
`SpecifiedInferenceDriver` supplies the shared execution lifecycle.

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

Message formatting is handled by `CanMapMessages`, which receives typed `Messages` and returns a provider-native array. Implementations compose a `MessageMapper` utility for typed iteration instead of duplicating the loop:

```php
interface CanMapMessages
{
    public function map(Messages $messages): array;
}
```

A request adapter composes these together. It receives a `CanMapRequestBody` (which itself wraps
a `CanMapMessages`) and produces the final `HttpRequest`.

Almost none of that is per-provider. `BaseHttpRequestAdapter` owns the skeleton -- POST, the
body from the body format, the stream flag, the telemetry-correlation wrapper -- and leaves two
abstract hooks, which are the only things providers actually disagree about:

```php
abstract class BaseHttpRequestAdapter implements CanTranslateInferenceRequest
{
    public function __construct(
        protected LLMConfig $config,
        protected CanMapRequestBody $bodyFormat,
    ) {}

    public function toHttpRequest(InferenceRequest $request): HttpRequest { /* the skeleton */ }

    abstract protected function toUrl(InferenceRequest $request): string;
    abstract protected function toHeaders(InferenceRequest $request): array;
}
```

So `OpenAIRequestAdapter` is just the two hooks:

```php
class OpenAIRequestAdapter extends BaseHttpRequestAdapter
{
    protected function toUrl(InferenceRequest $request): string {
        return "{$this->config->apiUrl}{$this->config->endpoint}";
    }

    protected function toHeaders(InferenceRequest $request): array {
        return [
            'Authorization' => "Bearer {$this->config->apiKey}",
            'Content-Type' => 'application/json; charset=utf-8',
            'Accept' => 'application/json',
        ];
    }
}
```

Both hooks are abstract rather than defaulted to `{apiUrl}{endpoint}`. Only two of the bundled
adapters would use such a default; the rest assemble a URL from a region, a model name or a
fallback endpoint, and a silently plausible URL is a worse failure than a compile error.

`AzureOpenAIRequestAdapter`, `CohereV2RequestAdapter`, `GeminiOAIRequestAdapter` and
`HuggingFaceRequestAdapter` extend `OpenAIRequestAdapter` rather than the base directly -- they
are OpenAI-compatible providers that differ only in headers, and are happy to inherit the URL.

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
    public function fromData(array $data): InferenceUsage;
}
```

Different providers report token usage under different keys and with different granularity. Some include cache tokens or reasoning tokens, others do not. Each provider's usage formatter encapsulates these differences into the normalized `InferenceUsage` object.

### Streamed usage: pass `null`, do not build a zero

Usage arrives on only a handful of events per stream -- roughly one chunk in 943 on a real one. A response adapter must therefore pass `usage: null` on every delta that carries no usage payload, rather than calling its usage format unconditionally:

```php
$delta = new PartialInferenceDelta(
    contentDelta: $content,
    usage: $this->hasUsageData($data) ? $this->usageFormat->fromData($data) : null,
);
```

`StreamingUsageState::apply()` already discards a zero-total `InferenceUsage`, so building one is pure waste -- one constructor call and one allocate/free cycle per delta, ten thousand of them on a long stream.

**The predicate is provider-specific. Do not copy another adapter's.** `CanMapUsage::fromData()` implementations read different keys, and a guard that checks the wrong one silently drops real token counts:

| Adapter | Usage key(s) |
|---|---|
| OpenAI, OpenAI-compatible, reasoning variants | `usage` |
| Gemini | `usageMetadata` |
| Anthropic | `usage` **and** `message.usage` -- input tokens arrive on `message_start`, output tokens on `message_delta` |
| Cohere v2 | `usage` **and** `delta.usage` |
| OpenResponses | `usage` **and** `response.usage` -- carried on the terminal `response.completed` event |

Express it as a `protected function hasUsageData(array $data): bool` on the adapter, so the per-provider difference is visible and testable. `OpenAIResponseAdapter` defines the default (`!empty($data['usage'])`); subclasses that read usage from elsewhere override it.

Two tests enforce this contract, and a new adapter should be added to both:

- `tests/Unit/Drivers/StreamDeltaUsageGuardTest.php` -- asserts `usage === null` on a quiet delta, non-null on a carrying one, and that the assembled totals are unchanged.
- `tests/Benchmarks/StreamAdapterUsageAllocationTest.php` -- counts actual `InferenceUsage` constructions over a 1,000-delta stream and fails if an adapter exceeds O(1).


## Varying the `response_format` payload

Providers disagree sharply about how a response format reaches them: some take a `json_schema`
envelope with a name and a strict flag, some accept only `json_object`, some hang the schema off
`json_object` or off a bare `value` key, and some have no JSON-schema support at all and must
degrade to plain JSON.

A body format expresses that by overriding one of three methods on `OpenAIBodyFormat`. Each
receives the `ResponseFormat` the caller asked for and returns the payload to send:

| Method | Called for | Base behaviour |
|---|---|---|
| `toTextResponseFormat()` | `text` | `['type' => 'text']` |
| `toJsonObjectResponseFormat()` | `json_object` | `['type' => 'json_object']` |
| `toJsonSchemaResponseFormat()` | `json_schema` | `json_schema` envelope with `name`, `schema`, `strict` |

Override only what differs. A provider with no schema support degrades by delegating:

```php
class MyProviderBodyFormat extends OpenAICompatibleBodyFormat
{
    // Provider accepts json_object only -- schema mode degrades rather than being rejected.
    #[\Override]
    protected function toJsonSchemaResponseFormat(ResponseFormat $responseFormat): array {
        return $this->toJsonObjectResponseFormat($responseFormat);
    }
}
```

Do not override `toResponseFormat()` to do this. That method decides *whether* a response
format is sent at all — `DeepseekBodyFormat` overrides it because its reasoner models take no
structured output of any kind — while the three methods above decide what the payload looks
like once that question is settled.

> Provider variation used to be injected into `ResponseFormat` itself through
> `withToTextHandler()` / `withToJsonObjectHandler()` / `withToJsonSchemaHandler()`. Those
> methods no longer exist: `ResponseFormat` is a plain four-field value object and carries no
> rendering behaviour. Every request used to allocate two closures and two copies of it to
> reach a payload the body format already had everything to build.

Whatever these return is pinned by `ResponseFormatFragmentGoldenTest`, which snapshots the
emitted fragment for every body format in the family across every mode. A new provider must be
added to its table — the test fails if one is missing.


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
6. An **`InferenceDriverSpec`** naming those pieces -- or, if the provider assembles its own URL or headers, a **driver** class extending `BaseInferenceRequestDriver`

Many providers use OpenAI-compatible formats. In those cases you can reuse the OpenAI adapters directly and skip the driver class entirely: register an `InferenceDriverSpec` with `OpenAICompatibleBodyFormat`. The `ollama`, `together`, `moonshot` and `openai-compatible` names all share exactly that one spec in the bundled registry.
