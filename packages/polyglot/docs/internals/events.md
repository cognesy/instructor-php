---
title: Events
description: Runtime events are available for observability and debugging.
---

Polyglot uses an event system to provide observability into the internal execution pipeline. Events are dispatched at each stage of the lifecycle, making it straightforward to implement logging, metrics, debugging, and monitoring without modifying the core library.


## Listening to Events

Both inference and embeddings runtimes expose two ways to listen to events:

### Targeted Listeners

Use `onEvent()` to listen for a specific event class:

```php
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Events\InferenceResponseCreated;
use Cognesy\Polyglot\Inference\InferenceRuntime;

$runtime = InferenceRuntime::fromConfig(
    new LLMConfig(
        driver: 'openai',
        apiUrl: 'https://api.openai.com/v1',
        apiKey: getenv('OPENAI_API_KEY'),
        endpoint: '/chat/completions',
        model: 'gpt-4.1-nano',
    ),
)->onEvent(InferenceResponseCreated::class, function ($event): void {
    // Log or inspect the response
});
```

You can register multiple listeners for the same event class. An optional priority parameter controls the order (higher values run first):

```php
$runtime->onEvent(InferenceStarted::class, $highPriorityListener, priority: 10);
$runtime->onEvent(InferenceStarted::class, $lowPriorityListener, priority: 0);
```

### Wiretap

Use `wiretap()` to receive all events regardless of type. This is useful for debugging and general-purpose logging:

```php
$runtime->wiretap(function ($event): void {
    echo get_class($event) . "\n";
});
```


## Inference Events

The inference lifecycle dispatches events in this order:

### Execution-Level Events

| Event | When Dispatched | Key Data |
|---|---|---|
| `InferenceStarted` | Beginning of execution | execution ID, request, isStreamed |
| `InferenceCompleted` | End of execution (success or failure) | execution ID, isSuccess, finish reason, usage, attempt count, durationMs, completedAt, response |

These events bracket the entire inference operation, including any retry attempts. `InferenceCompleted` is dispatched exactly once per execution, whether it succeeded or failed.

### Attempt-Level Events

Each retry attempt dispatches its own events:

| Event | When Dispatched | Key Data |
|---|---|---|
| `InferenceAttemptStarted` | Beginning of an attempt | execution ID, attempt ID, attempt number, model |
| `InferenceAttemptSucceeded` | Attempt completed successfully | execution ID, attempt ID, attemptNumber, finish reason, usage, durationMs |
| `InferenceAttemptFailed` | Attempt failed | execution ID, attempt ID, attemptNumber, errorMessage, errorType, willRetry, HTTP status code, partial usage, durationMs |
| `InferenceUsageReported` | After a successful attempt | execution ID, InferenceUsage, model, isFinal |

When retries are configured, you may see multiple `InferenceAttemptStarted`/`InferenceAttemptFailed` pairs before a final `InferenceAttemptSucceeded` event. The `attemptNumber` field tracks which attempt is running.

### Response Events

| Event | When Dispatched | Key Data |
|---|---|---|
| `InferenceRequested` | Before sending the HTTP request | request data |
| `InferenceResponseCreated` | After receiving and parsing the response | full `InferenceResponse` |
| `InferenceFailed` | On unrecoverable failure | error details |

### Streaming Events

| Event | When Dispatched | Key Data |
|---|---|---|
| `StreamFirstChunkReceived` | First visible delta arrives | execution ID, timeToFirstChunkMs, receivedAt, model, initial content |
| `PartialInferenceDeltaCreated` | Each visible delta | execution ID, `PartialInferenceDelta` object |
| `StreamEventReceived` | Raw SSE event received | raw event data |
| `StreamEventParsed` | SSE event parsed into a delta | parsed event data |

The `StreamFirstChunkReceived` event is particularly useful for measuring time-to-first-chunk (TTFC), as it includes the `requestStartedAt` timestamp.

### Driver Events

| Event | When Dispatched | Key Data |
|---|---|---|
| `InferenceDriverBuilt` | After the driver is created by the factory | driver class, redacted config, HTTP client class |

Sensitive configuration values (API keys, tokens, secrets) are automatically redacted in the `InferenceDriverBuilt` event payload.


## Embeddings Events

The embeddings lifecycle dispatches a smaller set of events:

| Event | When Dispatched | Key Data |
|---|---|---|
| `EmbeddingsDriverBuilt` | After the embeddings driver is created | driver class, config, HTTP client class |
| `EmbeddingsRequested` | Before sending the embeddings request | request data |
| `EmbeddingsResponseReceived` | After receiving the response | `EmbeddingsResponse` object |
| `EmbeddingsFailed` | On failure | error details |


## Practical Examples

### Logging Token Usage

```php
use Cognesy\Polyglot\Inference\Events\InferenceUsageReported;

$runtime->onEvent(InferenceUsageReported::class, function ($event): void {
    logger()->info('Token usage', [
        'model' => $event->model,
        'usage' => $event->usage->toString(),
    ]);
});
```

### Measuring Time-to-First-Chunk

```php
use Cognesy\Polyglot\Inference\Events\StreamFirstChunkReceived;

$runtime->onEvent(StreamFirstChunkReceived::class, function (StreamFirstChunkReceived $event): void {
    logger()->info("TTFC: {$event->timeToFirstChunkMs}ms for model {$event->model}");
});
```

### Tracking Retry Attempts

```php
use Cognesy\Polyglot\Inference\Events\InferenceAttemptFailed;

$runtime->onEvent(InferenceAttemptFailed::class, function (InferenceAttemptFailed $event): void {
    logger()->warning("Attempt {$event->attemptNumber} failed", [
        'errorMessage' => $event->errorMessage,
        'errorType' => $event->errorType,
        'willRetry' => $event->willRetry,
        'httpStatus' => $event->httpStatusCode,
    ]);
});
```

### Monitoring Execution Outcomes

```php
use Cognesy\Polyglot\Inference\Events\InferenceCompleted;

$runtime->onEvent(InferenceCompleted::class, function (InferenceCompleted $event): void {
    logger()->info('Inference completed', [
        'success' => $event->isSuccess,
        'finishReason' => $event->finishReason->value,
        'attempts' => $event->attemptCount,
        'totalTokens' => $event->usage->total(),
        'durationMs' => $event->durationMs,
    ]);
});
```


## Event Dispatcher

Events are dispatched through an `EventDispatcher` that implements `CanHandleEvents` (which extends `Psr\EventDispatcher\EventDispatcherInterface`). When a runtime is created without an explicit event dispatcher, it creates a default one named `'polyglot.inference.runtime'` or `'polyglot.embeddings.runtime'`.

You can inject a shared event dispatcher to correlate events across multiple runtimes or integrate with your application's existing event system:

```php
use Cognesy\Events\Dispatchers\EventDispatcher;

$events = new EventDispatcher(name: 'my-app');
$runtime = InferenceRuntime::fromConfig($config, events: $events);
```

The same event dispatcher instance can be shared between inference and embeddings runtimes, allowing a single wiretap listener to observe all Polyglot activity.
