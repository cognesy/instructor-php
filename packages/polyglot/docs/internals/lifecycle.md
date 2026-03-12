---
title: Lifecycle
description: What happens between building a request and receiving the final response.
---

Understanding the request lifecycle helps when debugging provider issues, implementing custom drivers, or hooking into events for observability. This page traces the complete flow for both inference and embeddings operations.


## Inference Lifecycle

### 1. Request Construction

The lifecycle begins when the application builds an `InferenceRequest` through the `Inference` facade:

```php
$inference = Inference::using('openai')
    ->withMessages('Explain PHP generics.')
    ->withModel('gpt-4.1-nano')
    ->withMaxTokens(1024);
```

At this point, no HTTP call has been made. The facade holds an `InferenceRequestBuilder` that accumulates parameters. Every `with*()` call returns a new immutable copy, so the original instance is never modified.

### 2. Creating a Pending Handle

Calling `create()` (or a shortcut like `get()` or `response()`) builds the `InferenceRequest` and passes it to the runtime:

```php
$pending = $inference->create();
```

The `InferenceRuntime` wraps the request in an `InferenceExecution` object and returns a `PendingInference` handle. Execution is still deferred -- no HTTP call has been sent yet.

The `InferenceExecution` tracks the full lifecycle state: the original request, retry attempts, usage accumulation, and the final response.

### 3. Triggering Execution

The HTTP call is triggered only when you read from the `PendingInference`:

```php
$text = $pending->get();          // triggers execution, returns content string
$response = $pending->response(); // triggers execution, returns InferenceResponse
$stream = $pending->stream();     // triggers execution (streaming mode)
```

Internally, `PendingInference` delegates to `InferenceExecutionSession`, which orchestrates the full lifecycle.

### 4. The Execution Session

The `InferenceExecutionSession` is the heart of the lifecycle. It performs these steps for a non-streaming request:

1. **Dispatches `InferenceStarted`** -- signals the beginning of the operation, including the execution ID, request details, and whether streaming is enabled
2. **Dispatches `InferenceAttemptStarted`** -- signals the beginning of an attempt with the attempt number and model
3. **Calls the driver** -- `driver->makeResponseFor($request)` triggers the full request-response cycle:
   - The driver's request adapter converts `InferenceRequest` into an `HttpRequest`
   - The HTTP client sends the request to the provider
   - The driver's response adapter normalizes the raw `HttpResponse` into an `InferenceResponse`
4. **Checks the response** -- if the finish reason indicates a failure (error, content filter, or length limit), the session handles it according to the retry policy
5. **Dispatches success events**:
   - `InferenceResponseCreated` -- the response is ready
   - `InferenceAttemptSucceeded` -- the attempt completed, including finish reason and usage
   - `InferenceUsageReported` -- token usage is reported with the model name
   - `InferenceCompleted` -- the entire operation is done, including total attempt count and timing
6. **Attaches pricing** -- if the `LLMConfig` includes pricing data, it is attached to the `Usage` object so that `$response->usage()->cost()` returns the estimated cost
7. **Returns `InferenceResponse`** to the caller

### 5. Retry Handling

If the request fails with a retryable error (transient HTTP status, timeout, network error, or provider-classified retriable exception), the session:

1. Records the failure on the execution object
2. **Dispatches `InferenceAttemptFailed`** -- with the error details, HTTP status code, partial usage, and `willRetry: true`
3. Waits for the configured delay (exponential backoff with optional jitter)
4. **Dispatches a new `InferenceAttemptStarted`** and retries

If all attempts are exhausted, the session dispatches `InferenceCompleted` with `isSuccess: false` and throws the terminal error.

**Length-limit recovery** has special handling. When a response finishes with `Length` as the finish reason and the retry policy allows length recovery, the session can:

- **`'continue'`** -- append the partial response as an assistant message, add a continuation prompt, and retry
- **`'increase_max_tokens'`** -- increase the `max_tokens` option by the configured increment and retry

This is independent of the regular retry count and controlled by `lengthMaxAttempts`.

### 6. Cached Context

If the request includes a `CachedInferenceContext`, the driver applies it before sending. Cached context allows you to pre-configure messages, tools, tool choice, and response format that are prepended to or merged with the request's own values. This is particularly useful for system prompts or shared tool definitions that remain constant across calls.


## Streaming Lifecycle

When streaming is enabled, the flow diverges after the HTTP request is sent:

1. `PendingInference::stream()` validates that streaming was requested, then creates an `InferenceStream`
2. The driver produces an iterable of `PartialInferenceDelta` objects from the SSE event stream via `driver->makeStreamDeltasFor($request)`
3. The `InferenceStream` tracks visibility state through a `VisibilityTracker` and yields only deltas with meaningful changes (filtering out empty or duplicate deltas)

```php
$stream = $inference->withMessages('Hello')->stream();

foreach ($stream->deltas() as $delta) {
    echo $delta->contentDelta;  // incremental text
}

$finalResponse = $stream->final();  // assembled InferenceResponse
```

### Stream Events

The stream dispatches events as deltas arrive:

- **`StreamFirstChunkReceived`** -- when the first visible delta arrives, including the request start time for TTFC measurement
- **`PartialInferenceDeltaCreated`** -- for each visible delta
- **`InferenceResponseCreated`** -- when the stream finishes and the final response is assembled from accumulated state

### Stream Processing

The stream supports functional-style processing through `map()`, `reduce()`, and `filter()`:

```php
// Map deltas to extracted values
$contents = $stream->map(fn($delta) => $delta->contentDelta);

// Reduce deltas into a single value
$fullText = $stream->reduce(fn($carry, $delta) => $carry . $delta->contentDelta, '');

// Filter deltas
$toolDeltas = $stream->filter(fn($delta) => $delta->toolName !== '');

// Collect all visible deltas
$allDeltas = $stream->all();
```

### Delta Callback

You can register a callback that fires for every visible delta:

```php
$stream->onDelta(function (PartialInferenceDelta $delta): void {
    echo $delta->contentDelta;
});
```

### Stream Finalization

Calling `final()` on a stream that has not been fully consumed will drain the remaining deltas first, ensuring the final response is complete. A stream can only be consumed once -- calling `deltas()` a second time throws a `LogicException`.

The final response assembled from the stream goes through the same pricing attachment and event dispatch as a synchronous response.


## Embeddings Lifecycle

The embeddings lifecycle is simpler since streaming is not involved:

1. **`Embeddings` builds an `EmbeddingsRequest`** from the configured inputs, model, and options
2. **`create()` returns `PendingEmbeddings`** -- a lazy handle that holds the request, driver, and event dispatcher
3. **`get()` triggers execution**:
   - The driver's `handle()` method sends the HTTP request
   - The response body is decoded and passed to `driver->fromData()` to build an `EmbeddingsResponse`
   - `EmbeddingsResponseReceived` is dispatched
4. **`EmbeddingsResponse` is returned** -- containing vectors and usage

```php
$response = Embeddings::using('openai')
    ->withInputs(['Hello', 'World'])
    ->get();

$vectors = $response->vectors();   // Vector[]
$first = $response->first();       // first Vector
$usage = $response->usage();       // Usage
```

Retry logic is handled internally by `PendingEmbeddings` based on the `EmbeddingsRetryPolicy` attached to the request. The retry loop follows the same exponential backoff pattern as inference retries.


## Response Caching

Both the inference and embeddings lifecycles support response caching. When `ResponseCachePolicy` is set on the request, the `InferenceExecutionSession` caches the response after the first successful execution. Subsequent calls to `response()` or `get()` on the same `PendingInference` return the cached result without making another HTTP call.

```php
use Cognesy\Polyglot\Inference\Enums\ResponseCachePolicy;

$pending = $inference
    ->withMessages('Hello')
    ->withResponseCachePolicy(ResponseCachePolicy::Memory)
    ->create();

$first = $pending->response();  // makes HTTP call
$second = $pending->response(); // returns cached response
```

For streaming, the stream itself cannot be replayed -- calling `deltas()` a second time will throw a `LogicException`. However, `final()` always returns the assembled response, which is stored in the execution object.
