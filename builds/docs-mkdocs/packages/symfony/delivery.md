# Symfony Event Delivery Model

`packages/symfony` owns the framework-facing delivery model for InstructorPHP runtime events.

The core rule is:

- the package-owned `Cognesy\Events\Contracts\CanHandleEvents` service is the authoritative runtime event bus
- Symfony's `event_dispatcher` is an optional observation bridge, not the source of truth
- transport-specific delivery such as Messenger handoff or HTTP streaming layers on top of those semantics instead of redefining them

## Delivery Surfaces

The package supports four distinct delivery surfaces:

1. Internal runtime bus
   This carries the full event stream and remains the place where wiretaps, telemetry, and logging attach.
2. Projected progress bus
   This carries `RuntimeProgressUpdate` objects derived from the internal runtime stream. Web, API, and CLI code can build on this stable projection without consuming every raw runtime event directly.
3. Symfony EventDispatcher bridge
   This mirrors the supported observation subset into Symfony so applications can use listeners, subscribers, and queued listener patterns.
4. Async delivery seams
   These are package-owned Messenger integration points for queued execution and observation workflows.

## Event Categories

The runtime event stream should be reasoned about in three categories:

### 1. Lifecycle events

These describe meaningful state transitions and are safe to bridge into Symfony by default.

Examples:

- extraction started, completed, failed
- response validated, transformed, or failed
- HTTP client built
- AgentCtrl execution started, completed, failed
- native-agent execution started, stepped, completed, failed

These are the events that application listeners, business monitoring, and queued follow-up work should primarily consume.

### 2. Observation detail events

These are still first-class runtime events, but they are primarily for low-level logging, telemetry enrichment, or debugging.

Examples:

- partial-response generation
- streamed tool-call updates
- raw chunk-received notifications
- verbose transport diagnostics

These should remain available on the package-owned bus and through wiretaps even when they are not mirrored into Symfony's dispatcher by default.

### 3. Internal infrastructure events

These exist to support package internals and should not define the public application-facing observation contract.

Examples:

- framework-specific adapter bootstrap details
- internal projector lifecycle hooks
- future delivery helper internals

These can stay internal to the package-owned bus unless a later task promotes them into the supported observation surface explicitly.

## Bridging Rules

The current and intended bridge semantics are:

- `instructor.events.dispatch_to_symfony: true` enables the framework bridge
- `instructor.events.dispatch_to_symfony: false` keeps all observation package-local
- the package-owned runtime bus still dispatches the full event stream either way
- framework listeners must never be required for core runtime correctness

The bridge should favor stable lifecycle events over noisy streaming internals.

Recommended long-term rule set:

- bridge lifecycle events by default
- keep high-frequency streaming or debug events on the internal wiretap path unless applications opt in later
- treat logging and telemetry as consumers of the internal bus, not as a side effect of Symfony listener registration

This keeps Symfony listeners useful without forcing every application to absorb the hottest parts of the runtime stream.

## Runtime Shape Expectations

### HTTP and API applications

- use the package-owned bus for correctness, logging, and telemetry
- use `Cognesy\Instructor\Symfony\Delivery\Progress\Contracts\CanHandleProgressUpdates` when you want stable lifecycle or streaming-friendly projection
- use the Symfony bridge for app-local listeners, notifications, and integration code
- build SSE, WebSocket, Mercure, or polling responses on top of `RuntimeProgressUpdate` rather than assuming every raw runtime event becomes a framework event

### Messenger workers

- Messenger is the primary package-supported async execution model
- queued handlers should consume explicit package-owned messages or handoff references, not raw framework event forwarding alone
- the same runtime event semantics must remain valid inside workers even when there is no active HTTP request

Current package-owned Messenger seams:

- `ExecuteAgentCtrlPromptMessage` queues AgentCtrl prompt execution against the `messenger` runtime adapter
- `ExecuteNativeAgentPromptMessage` queues native agent prompt execution against the package-owned session runtime and can resume a persisted session when `sessionId` is provided
- `RuntimeObservationMessage` carries explicitly selected runtime events into Messenger for queued observation workflows

Current config entrypoint:

```yaml
instructor:
  delivery:
    messenger:
      enabled: true
      bus_service: message_bus
      observe_events:
        - Cognesy\Agents\Session\Events\SessionSaved
# @doctest id="dfe9"
```

This is intentionally opt-in and explicit:

- execution uses package-owned message DTOs and handlers
- observation uses an allow-listed wiretap bridge from the internal event bus into the configured Symfony bus
- raw Symfony listener mirroring is still distinct from Messenger queue dispatch

### CLI applications

- CLI flows should keep using the same internal bus and wiretap semantics
- framework event bridging is optional, not required
- the package now exposes `SymfonyCliObservationFormatter` and `SymfonyCliObservationPrinter` on top of the projected progress bus
- `instructor.delivery.cli.enabled: true` auto-attaches the built-in printer to that progress bus
- CLI observation helpers still format the projected runtime stream instead of inventing a second event model

## Progress Projection

The package now owns a dedicated progress projection seam:

- raw runtime events stay on `Cognesy\Events\Contracts\CanHandleEvents`
- projected progress updates flow through `Cognesy\Instructor\Symfony\Delivery\Progress\Contracts\CanHandleProgressUpdates`
- consumers receive `Cognesy\Instructor\Symfony\Delivery\Progress\RuntimeProgressUpdate`

That projection currently classifies updates into:

- `started`
- `progress`
- `stream`
- `completed`
- `failed`

Supported sources currently include:

- native-agent lifecycle and step events
- AgentCtrl execution and streaming events
- structured-output lifecycle and partial-response events
- low-level HTTP streaming diagnostics

This gives applications one stable hook for transport-specific delivery code without forcing the full raw event surface onto every consumer.

Example:

```php
use Cognesy\Instructor\Symfony\Delivery\Progress\Contracts\CanHandleProgressUpdates;
use Cognesy\Instructor\Symfony\Delivery\Progress\RuntimeProgressUpdate;

$progress = $container->get(CanHandleProgressUpdates::class);

$progress->wiretap(static function (object $event): void {
    if (! $event instanceof RuntimeProgressUpdate) {
        return;
    }

    $payload = [
        'status' => $event->status->value,
        'source' => $event->source,
        'message' => $event->message,
        'operationId' => $event->operationId,
    ];
});
// @doctest id="d292"
```

## Relation To Logging And Telemetry

Logging and telemetry need access to the broadest and most coherent event stream.

Because of that:

- package-owned logging attaches to the internal event bus
- telemetry projectors and exporters should also prefer the internal event stream
- Symfony EventDispatcher mirroring is a convenience integration surface for applications, not the authoritative observability pipeline

This prevents logging, telemetry, and Symfony listeners from drifting into separate interpretations of the same runtime behavior.

## Public Contract For Later Tasks

Later tasks should preserve these rules:

- `packages/symfony` owns framework registration and delivery defaults
- `packages/events` keeps reusable bridge primitives such as `SymfonyEventDispatcher`
- Messenger integration should move execution or observation work explicitly, not by abusing framework event mirroring as a transport
- transport-specific streaming remains optional and layered under `instructor.delivery`
- the internal bus remains the only place guaranteed to see the full event stream

This is the semantic baseline for the later EventDispatcher, Messenger, telemetry, logging, and CLI observation tasks in the Symfony epic.
