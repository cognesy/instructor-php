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
| `updatedAt` | `DateTimeImmutable` | Timestamp of last mutation |
| `messages` | `Messages` | The conversation messages |
| `model` | `string` | Model identifier |
| `tools` | `ToolDefinitions` | Tool/function definitions |
| `toolChoice` | `ToolChoice` | Tool selection strategy |
| `responseFormat` | `ResponseFormat` | Structured output format |
| `options` | `array` | Additional options (e.g. `stream`, `max_tokens`, `temperature`) |
| `cachedContext` | `CachedInferenceContext` | Shared context for prompt caching |
| `responseCachePolicy` | `ResponseCachePolicy` | Controls response caching behavior |
| `retryPolicy` | `?InferenceRetryPolicy` | Retry configuration |

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
// Get plain text content
$text = $pending->get();

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

`InferenceResponse` is a `final readonly` value object that normalizes the provider's result into a consistent shape.

**Namespace:** `Cognesy\Polyglot\Inference\Data\InferenceResponse`

### Reading the Response

```php
$response->content();           // string -- the generated text
$response->reasoningContent();  // string -- chain-of-thought (if available)
$response->toolCalls();         // ToolCalls collection
$response->usage();             // InferenceUsage object with token counts
$response->finishReason();      // InferenceFinishReason enum
$response->responseData();      // HttpResponse -- the raw HTTP response
$response->isPartial();         // bool -- true for intermediate streaming results
```

Predicate methods: `hasContent()`, `hasReasoningContent()`, `hasToolCalls()`, `hasFinishReason()`.

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

### Reasoning Content Fallback

Some providers embed reasoning in `<think>` tags within the content rather than in a dedicated field. The `withReasoningContentFallbackFromContent()` method handles this:

```php
$response = $response->withReasoningContentFallbackFromContent();
// Now $response->reasoningContent() contains the extracted reasoning
// And $response->content() has the <think> tags removed
```

This is a no-op if the response already has dedicated reasoning content or if no `<think>` tags are present.

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

During streaming, the driver emits `PartialInferenceDelta` objects for each SSE event. Each delta carries only the incremental change from that event.

**Namespace:** `Cognesy\Polyglot\Inference\Data\PartialInferenceDelta`

### Fields

| Field | Type | Description |
|---|---|---|
| `contentDelta` | `string` | Incremental text content |
| `reasoningContentDelta` | `string` | Incremental reasoning content |
| `toolId` | `ToolCallId\|string\|null` | Tool call identifier |
| `toolName` | `string` | Tool name (first delta of a tool call) |
| `toolArgs` | `string` | Incremental tool call arguments |
| `finishReason` | `string` | Set on the final delta |
| `usage` | `?InferenceUsage` | Token usage (typically on the last delta) |
| `usageIsCumulative` | `bool` | Whether usage represents total (true) or incremental (false) |
| `responseData` | `?HttpResponse` | Raw response data for this event |
| `value` | `mixed` | Optional provider-specific value |

The `InferenceStream` accumulates these deltas internally using `InferenceStreamState` and assembles the final `InferenceResponse` when the stream completes. A `VisibilityTracker` ensures that only deltas with meaningful content changes are yielded to the caller.


## InferenceUsage

The `InferenceUsage` object tracks token consumption across several categories:

**Namespace:** `Cognesy\Polyglot\Inference\Data\InferenceUsage`

```php
$usage = $response->usage();

$usage->inputTokens;       // int -- prompt tokens
$usage->outputTokens;      // int -- completion tokens
$usage->cacheWriteTokens;  // int -- tokens written to cache
$usage->cacheReadTokens;   // int -- tokens read from cache
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

Cost is calculated externally using a calculator rather than through methods on the usage object. Pricing is specified in USD per 1 million tokens:

```php
use Cognesy\Polyglot\Inference\Data\InferencePricing;
use Cognesy\Polyglot\Pricing\FlatRateCostCalculator;

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
