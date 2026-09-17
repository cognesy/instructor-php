---
title: Requests and Responses
description: The main data objects used by the package.
---

Polyglot normalizes all provider interactions into a small set of data objects. These objects are immutable -- every mutation returns a new instance, making them safe to pass around and branch from.


## InferenceRequest

`InferenceRequest` encapsulates everything needed for an LLM call. It stores the conversation messages, model selection, tools, response format, options, and caching/retry configuration.

**Namespace:** `Cognesy\Polyglot\Inference\Data\InferenceRequest`

### Key Properties

| Property | Type | Description |
|---|---|---|
| `id` | `InferenceRequestId` | Unique identifier, auto-generated |
| `createdAt` | `DateTimeImmutable` | Timestamp of creation |
| `updatedAt` | `DateTimeImmutable` | Carried over by `with()`, not advanced — see below |
| `messages` | `Messages` | The conversation messages |
| `model` | `string` | Model identifier |
| `tools` | `ToolDefinitions` | Tool/function definitions |
| `toolChoice` | `ToolChoice` | Tool selection strategy |
| `responseFormat` | `ResponseFormat` | Structured output format |
| `options` | `array` | Additional options (e.g. `stream`, `max_tokens`, `temperature`) |
| `cachedContext` | `CachedInferenceContext` | Shared context for prompt caching |
| `responseCachePolicy` | `ResponseCachePolicy` | Controls response caching behavior |
| `retryPolicy` | `?InferenceRetryPolicy` | Retry configuration |

#### A note on `updatedAt`

`updatedAt` is **not** a "last mutation" timestamp. A copy made by `with()` inherits the
source object's `updatedAt` instance rather than reading the clock, so for an object built
in the normal way `updatedAt === createdAt` no matter how many withers have run. The same
holds for `InferenceResponse`, `InferenceAttempt` and `InferenceExecution`.

Withers run several times per attempt, and nothing on any execution path reads `updatedAt`
— `InferenceRequest::toArray()` does not even emit it — so recomputing it was a clock read
and an allocation for nobody. The one thing the field still does is survive a
`fromArray()` → `with()` → `toArray()` round trip: a value you deserialise is preserved, not
overwritten with the load time. If you need "when was this copy made", record it yourself.

### Reading Values

```php
$request->messages();             // Messages -- the message list
$request->model();                // string
$request->isStreamed();           // bool -- checks options['stream']
$request->tools();               // ToolDefinitions
$request->toolChoice();          // ToolChoice
$request->responseFormat();      // ResponseFormat
$request->options();             // array
$request->cachedContext();       // ?CachedInferenceContext
$request->responseCachePolicy(); // ResponseCachePolicy
$request->retryPolicy();         // ?InferenceRetryPolicy
$request->id();                  // InferenceRequestId
```

Predicate methods are also available: `hasMessages()`, `hasModel()`, `hasTools()`, `hasToolChoice()`, `hasResponseFormat()`, `hasNonTextResponseFormat()`, `hasTextResponseFormat()`, `hasOptions()`.

### Modifying a Request

All mutators return a new instance, preserving the original request ID and creation timestamp:

```php
$updated = $request
    ->withMessages(Messages::fromString('New prompt'))
    ->withModel('gpt-4.1')
    ->withStreaming(true)
    ->withOptions(['temperature' => 0.7])
    ->withTools($toolDefinitions)
    ->withToolChoice('auto')
    ->withResponseFormat(['type' => 'json_object'])
    ->withRetryPolicy(new InferenceRetryPolicy(maxAttempts: 3))
    ->withResponseCachePolicy(ResponseCachePolicy::Memory);
```

The `with(...)` method allows setting multiple fields in a single call:

```php
$updated = $request->with(
    messages: Messages::fromString('New prompt'),
    model: 'gpt-4.1',
    options: ['temperature' => 0.7],
);
```

### Cached Context

The cached context mechanism allows you to separate stable parts of a prompt (system messages, tool definitions, response format) from the dynamic parts (user messages). When `withCacheApplied()` is called, the cached context is merged into the request:

```php
$request = new InferenceRequest(
    messages: Messages::fromString('What is 2+2?'),
    cachedContext: new CachedInferenceContext(
        messages: [['role' => 'system', 'content' => 'You are a math tutor.']],
        tools: $toolDefinitions,
        responseFormat: ['type' => 'json_object'],
    ),
);

// Merges cached messages before request messages,
// cached tools/format used if request has none
$merged = $request->withCacheApplied();
```

After applying, the merged request has an empty cached context to prevent double-application.

### Serialization

Requests can be serialized to and from arrays for storage or transport:

```php
$array = $request->toArray();
$restored = InferenceRequest::fromArray($array);
```


## PendingInference

`PendingInference` is a lazy handle for a single inference operation. It does not execute the request until you access the results. This enables the fluent `Inference` API to defer execution to the moment of consumption.

**Namespace:** `Cognesy\Polyglot\Inference\PendingInference`

### Consuming Results

```php
// Get the complete assistant message
$message = $pending->get();

// Get the full response object
$response = $pending->response();

// Stream the response (requires streaming to be enabled)
$stream = $pending->stream();

// Extract JSON from the response content
$json = $pending->asJson();          // string
$data = $pending->asJsonData();      // array

// Extract tool call arguments as JSON
$json = $pending->asToolCallJson();      // string
$data = $pending->asToolCallJsonData();  // array

// Check if streaming is enabled for this request
$isStreamed = $pending->isStreamed();
```

The underlying `InferenceExecutionSession` handles retry logic, event dispatching, and response caching. Once execution completes, the response is cached for the lifetime of the `PendingInference` instance.

> **Important:** Calling `stream()` on a non-streaming request will throw an `InvalidArgumentException`. Enable streaming via `withStreaming(true)` on the facade before calling `create()`.


## InferenceResponse

`InferenceResponse` is a `final readonly` envelope around the provider's complete
assistant `Message` and inference metadata. The message preserves the provider's block
order; text, reasoning, and tool calls are projections rather than parallel response fields.

**Namespace:** `Cognesy\Polyglot\Inference\Data\InferenceResponse`

### Reading the Response

```php
$message = $response->message(); // Message -- the ordered assistant turn
$message->parts();               // ContentParts in provider order
$message->content()->toString(); // generated text projection
$message->reasoningContent();    // reasoning projection
$message->toolCalls();            // ToolCalls projection
$response->usage();             // InferenceUsage object with token counts
$response->finishReason();      // InferenceFinishReason enum
$response->responseData();      // HttpResponse -- the raw HTTP response
$response->isPartial();         // bool -- true for intermediate streaming results
```

Message predicates such as `isEmpty()` and `hasToolCalls()` live on `Message`;
`hasFinishReason()` remains on the response envelope.

### JSON Extraction

The response provides convenience methods for extracting structured data:

```php
// Find JSON in the response content
$json = $response->findJsonData();           // Json object
$data = $response->findJsonData()->toArray(); // array
$str = $response->findJsonData()->toString(); // string

// Extract tool call arguments
$json = $response->findToolCallJsonData();  // Json object
```

When a response has a single tool call, `findToolCallJsonData()` returns the arguments of that call. When there are multiple tool calls, it returns an array of all tool call data.

### Reasoning Normalization

Some providers embed reasoning in `<think>` tags within a text field rather than in a
dedicated block. Response adapters normalize that representation before constructing the
assistant message:

```php
$message = $response->message();
$message->reasoningContent();    // extracted reasoning
$message->content()->toString(); // visible text without the tags
```

Dedicated reasoning blocks remain ordered and retain provider replay metadata.

### Finish Reason

The `finishReason()` method returns an `InferenceFinishReason` enum. The `hasFinishedWithFailure()` method checks whether the response ended with an error, content filter, or length limit:

```php
if ($response->hasFinishedWithFailure()) {
    // Handle error, content_filter, or length finish reasons
}
```

### Serialization

Responses support round-trip serialization:

```php
$array = $response->toArray();
$restored = InferenceResponse::fromArray($array);
```


## PartialInferenceDelta

During streaming, the driver emits `PartialInferenceDelta` objects for each SSE event.
Each delta carries ordered assistant-message block changes from that event.

**Namespace:** `Cognesy\Polyglot\Inference\Data\PartialInferenceDelta`

### Fields

| Field | Type | Description |
|---|---|---|
| `messageChunks` | `AssistantMessageChunks` | Ordered block starts, text/reasoning/tool deltas, and completed blocks |
| `finishReason` | `string` | Set on the final delta |
| `usage` | `?InferenceUsage` | Token usage (typically on the last delta) |
| `usageIsCumulative` | `bool` | Whether usage represents total (true) or incremental (false) |
| `responseData` | `?HttpResponse` | Raw response data for this event |
| `value` | `mixed` | Optional higher-level value |
| `replay` | `?ReplayEnvelope` | Provider replay state carried by this event |

The `InferenceStream` applies the chunks to a shared assistant-message assembler and
constructs the same `Message` shape produced by synchronous adapters. Stable block
indices preserve interleaving and allow replay metadata to round-trip.


## InferenceUsage

The `InferenceUsage` object tracks token consumption across several categories:

**Namespace:** `Cognesy\Polyglot\Inference\Data\InferenceUsage`

```php
$usage = $response->usage();

$usage->inputTokens;       // int -- prompt tokens
$usage->outputTokens;      // int -- completion tokens
$usage->cacheWriteTokens;  // int -- tokens written to cache when reported by provider
$usage->cacheReadTokens;   // int -- tokens read from cache when reported by provider
$usage->reasoningTokens;   // int -- tokens used for reasoning

// Aggregate accessors
$usage->total();   // sum of all token categories
$usage->input();   // input tokens only
$usage->output();  // output + reasoning tokens
$usage->cache();   // cache write + cache read tokens

// String representation
$usage->toString(); // "Tokens: 150 (i:100 o:40 c:0 r:10)"
```

### Cost Calculation

Cost is calculated externally using a calculator rather than through methods
on the usage object. The caller supplies current pricing in USD per 1 million
tokens; model catalog records do not contain pricing:

```php
use Cognesy\Polyglot\Inference\Data\InferencePricing;
use Cognesy\Polyglot\Inference\Pricing\FlatRateCostCalculator;

$calculator = new FlatRateCostCalculator();
$cost = $calculator->calculate($usage, new InferencePricing(
    inputPerMToken: 0.15,
    outputPerMToken: 0.60,
));

// The Cost value object
$cost->total;          // float -- total cost in USD
$cost->breakdown;      // array -- per-category breakdown
$cost->toString();     // string representation
$cost->toArray();      // array representation
```

### Accumulation

Usage and cost can be accumulated across multiple requests:

```php
$total = $usage1->withAccumulated($usage2);
$totalCost = $cost1->withAccumulated($cost2);
```


## Embeddings Data Objects

### EmbeddingsRequest

Holds the input texts, model, options, and retry policy for an embeddings call:

```php
$request = new EmbeddingsRequest(
    input: ['Hello world', 'Another text'],
    model: 'text-embedding-3-small',
    options: ['dimensions' => 256],
);

$request->inputs();     // array of strings
$request->model();      // string
$request->options();    // array
$request->hasInputs();  // bool
$request->retryPolicy(); // ?EmbeddingsRetryPolicy

// Immutable mutations
$updated = $request->withInputs('New text');
$updated = $request->withModel('text-embedding-3-large');
$updated = $request->withOptions(['dimensions' => 1024]);
```

### EmbeddingsResponse

Normalizes the provider's embeddings result:

```php
$response->vectors();       // Vector[] -- all embedding vectors
$response->first();         // ?Vector -- first vector
$response->last();          // ?Vector -- last vector
$response->all();           // Vector[] -- alias for vectors()
$response->usage();         // InferenceUsage
$response->toValuesArray(); // array of float arrays
$response->split($index);   // [Vector[], Vector[]] -- split at index
```

### PendingEmbeddings

A lazy handle similar to `PendingInference`. Calling `get()` triggers the HTTP request and returns an `EmbeddingsResponse`. The response is cached after the first call. Retry logic is handled internally based on the `EmbeddingsRetryPolicy` attached to the request, using the same exponential backoff pattern as inference retries.

```php
$pending = $embeddings->withInputs('Hello world')->create();
$response = $pending->get();      // triggers HTTP call
$request = $pending->request();   // access the original request
```


## Decision Data Objects

`DecisionRequest` carries text or structured input, a non-empty `Questions`
collection, an optional model override, retry policy, request ID, and telemetry
correlation. Its `toArray()` representation deliberately includes only input,
questions, and the optional model.

`PendingDecision` is the lazy execution boundary. The first `get()` returns
typed `Answers`; `response()` returns `DecisionResponse`. Both successful and
failed terminal results are memoized on that pending handle.

`DecisionResponse` contains typed answers, model, nullable token usage, the
provider request ID, and normalized `HttpResponse`. There is no partial delta or
streaming counterpart.

See [Question design](../decision/questions) for request primitives and
[Response handling](../decision/responses) for typed accessors and probability
distributions.
