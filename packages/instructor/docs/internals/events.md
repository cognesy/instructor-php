---
title: Events
description: 'Observe requests and streaming updates.'
---

## Overview

Instructor dispatches events at every significant stage of its execution. You can
listen to these events for logging, monitoring, debugging, or custom processing.
All event classes extend `Cognesy\Events\Event`.


## Listening to Events

### Targeted Listeners

Use `onEvent()` on the `StructuredOutputRuntime` to listen for a specific event type:

```php
use Cognesy\Instructor\Events\Response\ResponseValidationFailed;

$runtime = StructuredOutputRuntime::fromDefaults()
    ->onEvent(ResponseValidationFailed::class, function ($event) {
        logger()->warning('Validation failed', $event->toArray());
    });
```

### Wiretap (All Events)

Use `wiretap()` to receive every event dispatched by Instructor. This is useful for
debugging or comprehensive logging:

```php
$runtime = StructuredOutputRuntime::fromDefaults()
    ->wiretap(fn($event) => $event->print());
```

### Practical Example

```php
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\StructuredOutputRuntime;
use Cognesy\Instructor\Extras\Scalar\Scalar;
use Cognesy\Http\Events\HttpRequestSent;
use Cognesy\Http\Events\HttpResponseReceived;

$runtime = StructuredOutputRuntime::fromDefaults()
    // Log HTTP-level details
    ->onEvent(HttpRequestSent::class, fn($e) => dump($e))
    ->onEvent(HttpResponseReceived::class, fn($e) => dump($e))
    // Console-friendly output for all events
    ->wiretap(fn($event) => $event->print())
    // Structured logging
    ->wiretap(fn($event) => YourLogger::log($event->asLog()));

$result = (new StructuredOutput($runtime))
    ->with(
        messages: 'What is the population of Paris?',
        responseModel: Scalar::integer(),
    )
    ->get();
```


## Event Categories

Events are organized into namespaces that correspond to the processing stage:

### High-Level Events (`Events\StructuredOutput`)

| Event | When |
|---|---|
| `StructuredOutputStarted` | A structured output operation begins |
| `StructuredOutputRequestReceived` | The request has been received by the runtime |
| `StructuredOutputResponseGenerated` | The final `StructuredOutputResponse` has been produced |
| `StructuredOutputResponseUpdated` | A streaming partial response is emitted |

### Response-model Events (`Events\ResponseModel`)

| Event | When |
|---|---|
| `ResponseModelRequested` | A response model has been submitted for processing |
| `ResponseModelBuildModeSelected` | The factory has chosen a build strategy |
| `ResponseModelBuilt` | The response model and schema are ready |

### Attempt Events (`Events\Attempt`)

| Event | When |
|---|---|
| `ResponseRetryScheduled` | A retry attempt has been scheduled |
| `ResponseRecoveryExhausted` | All retries have been exhausted |

### Response Events (`Events\Response`)

| Event | When |
|---|---|
| `ResponseDeserializationAttempt` | Deserialization is about to start |
| `ResponseDeserialized` | Deserialization succeeded |
| `ResponseDeserializationFailed` | Deserialization failed |
| `CustomResponseDeserializationAttempt` | A `CanDeserializeSelf` implementation is being used |
| `ResponseValidationAttempt` | Validation is about to start |
| `ResponseValidated` | Validation passed |
| `ResponseValidationFailed` | Validation failed |
| `CustomResponseValidationAttempt` | A `CanValidateSelf` implementation is being used |
| `ResponseTransformationAttempt` | Transformation is about to start |
| `ResponseTransformed` | Transformation succeeded |
| `ResponseTransformationFailed` | Transformation failed |
| `ResponseMaterialized` | Final materialization succeeded, with the actual result type |
| `ResponseMaterializationFailed` | Final materialization failed at a typed stage |

### Extraction Events (`Events\Extraction`)

| Event | When |
|---|---|
| `ExtractionStarted` | Data extraction from the inference response begins |
| `ExtractionCompleted` | Extraction succeeded |
| `ExtractionFailed` | Extraction failed |
| `ExtractionStrategyAttempted` | A specific extraction strategy is being tried |
| `ExtractionStrategySucceeded` | The strategy produced a result |
| `ExtractionStrategyFailed` | The strategy did not produce a result |

### Streaming Events (`Events\Streaming`)

| Event | When |
|---|---|
| `ChunkReceived` | A raw chunk arrived from the provider |
| `StreamedResponseReceived` | A streamed response chunk was processed |
| `StreamedResponseFinished` | The stream has ended |
| `PartialJsonReceived` | A partial JSON fragment was accumulated |
| `PartialResponseGenerated` | A partial deserialized response is available |
| `PartialResponseGenerationFailed` | Partial deserialization failed (non-fatal) |
| `StreamedToolCallStarted` | A tool call began in the stream |
| `StreamedToolCallUpdated` | A tool call received more data |
| `StreamedToolCallCompleted` | A tool call finished |
| `SequenceUpdated` | A sequence item has been completed |


## Listener Gating

Some events are **not constructed at all** when nothing is listening for them. Building the
payload is not free: the structured-output lifecycle events each carry a telemetry envelope
that serialises the entire conversation, and `StructuredOutputResponseGenerated` additionally
normalizes the result value, runs `strlen()` over the content and reasoning content, and walks
the tool calls. `StructuredOutputResponseUpdated` pays a smaller version of that **once per
streamed emission**.

The rule is `Cognesy\Events\Support\ListenerGate`, shared with Polyglot — see the
"Listener Gating" section of `packages/polyglot/docs/internals/events.md` for the two
properties that matter to anyone writing a dispatcher.

### What is gated

| Emitter | Events |
|---|---|
| `StructuredOutputEventProjector` | `StructuredOutputRequestReceived`, `StructuredOutputStarted`, `StructuredOutputResponseUpdated`, `StructuredOutputResponseGenerated` |
| `DispatchStreamingEventsReducer` | `ChunkReceived`, `PartialResponseGenerated`, `SequenceUpdated`, `StreamedResponseReceived`, the `StreamedToolCall*` family |

Everything else is dispatched unconditionally.

**Fail-open is contractual.** A dispatcher that does not implement
`Cognesy\Events\Contracts\CanCheckListeners` cannot report its listeners, so it is assumed to
listen and receives every event. No dispatcher ever loses an event to this optimisation.

### When the gate is resolved

`StructuredOutputEventProjector` resolves its gates **once, at construction**, and a projector
is built per execution — by `StructuredOutputExecutionSession` for the sync path and inside
`StructuredOutputStream::__construct()` for the streaming path. Both are constructed after any
`onEvent()` or `wiretap()` call on the runtime, so no listener can be registered and then
missed.

`StructuredOutputRuntime` is the exception: it builds its projector **per request**, inside
`create()`. A runtime is long-lived and `onEvent()` mutates it, so gates resolved in its
constructor would silently drop `StructuredOutputRequestReceived` for every caller who
registers a listener the way the API invites. One `hasListenersFor()` per request costs
nothing next to the envelope it guards.

`DefaultRetryPolicy` also builds a projector, but the retry and recovery events it emits are
dispatched unconditionally — only the four events in the table above are gated.

Only the payload and the dispatch are conditional. Timing, attempt numbering and execution
state are not.


## Event Methods

Every event inherits the following convenience methods from `Cognesy\Events\Event`:

| Method | Description |
|---|---|
| `print()` | Print a console-friendly representation |
| `printLog()` | Print a log-formatted representation |
| `printDebug()` | Print console output and dump the full event object |
| `asConsole()` | Return the event formatted for console output |
| `asLog()` | Return the event formatted for log output |
| `toArray()` | Return the event data as an associative array |
| `name()` | Return the short class name of the event |

Events carry a `$logLevel` property (PSR log level) and a `$data` payload. The
`print()` method respects a configurable log-level threshold.

Instructor event payloads are normalized arrays. Structured-output lifecycle events
also expose correlation fields such as `requestId`, `executionId`, `attemptId`,
`phase`, and `phaseId` where applicable.
