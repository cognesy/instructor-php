# Feedback: Improving ReverbAgentEventAdapter

**Date**: 2026-01-16
**From**: Partnerspot PRM Team
**To**: InstructorPHP/Cognesy Team
**Re**: ReverbAgentEventAdapter Enhancement Recommendations

---

## Executive Summary

Thank you for creating `ReverbAgentEventAdapter` based on our implementation patterns. After comparing it with our production `AssistantEventBroadcaster`, we've identified several enhancements that would make it more useful for real-world chat applications.

**Key gaps identified:**
1. No streaming text support (critical for chat UX)
2. Complex wiring pattern (requires individual event subscriptions)
3. Missing status change broadcasts
4. No framework-specific reference implementations

---

## Gap 1: Streaming Text Support (Critical)

### The Problem

Real-time chat applications need to stream LLM responses character-by-character for responsive UX. Your adapter handles discrete events (step completed, tool called) but misses `StreamEventReceived` which carries the actual text chunks.

**Current coverage:**
```
AgentStepStarted      ✓ Handled
AgentStepCompleted    ✓ Handled
ToolCallStarted       ✓ Handled
ToolCallCompleted     ✓ Handled
ContinuationEvaluated ✓ Handled
StreamEventReceived   ✗ NOT HANDLED  <-- Critical gap
```

### Our Implementation

```php
// AssistantEventBroadcaster.php - Streaming handler
private function handleStreamChunk(StreamEventReceived $event): void
{
    $content = $event->content;

    if ($content === '') {
        return;
    }

    AssistantStreamChunk::dispatch(
        $this->sessionId,
        $content,
        false,  // isComplete flag
    );
}
```

### Recommended Addition

```php
// ReverbAgentEventAdapter.php

use Cognesy\Polyglot\Inference\Events\StreamEventReceived;

public function onStreamChunk(StreamEventReceived $event): void
{
    $content = $event->content;

    if ($content === '') {
        return;
    }

    $this->emit('agent.stream.chunk', [
        'content' => $content,
        'is_complete' => false,
        'chunk_index' => $this->chunkCounter++,
    ]);
}

public function onStreamComplete(): void
{
    $this->emit('agent.stream.complete', [
        'is_complete' => true,
        'total_chunks' => $this->chunkCounter,
    ]);
    $this->chunkCounter = 0;
}
```

**Envelope format:**
```json
{
    "type": "agent.stream.chunk",
    "session_id": "sess-123",
    "execution_id": "exec-456",
    "timestamp": "2026-01-16T12:00:00.123Z",
    "payload": {
        "content": "Hello",
        "is_complete": false,
        "chunk_index": 0
    }
}
```

---

## Gap 2: Wiring Complexity

### The Problem

Current usage requires manually wiring each event type:

```php
// Current: Verbose, error-prone
$adapter = new ReverbAgentEventAdapter($broadcaster, $sessionId, $executionId);

$agent->onEvent(AgentStepStarted::class, [$adapter, 'onAgentStepStarted']);
$agent->onEvent(AgentStepCompleted::class, [$adapter, 'onAgentStepCompleted']);
$agent->onEvent(ToolCallStarted::class, [$adapter, 'onToolCallStarted']);
$agent->onEvent(ToolCallCompleted::class, [$adapter, 'onToolCallCompleted']);
$agent->onEvent(ContinuationEvaluated::class, [$adapter, 'onContinuationEvaluated']);
// Easy to forget one, no compile-time safety
```

### Our Implementation

```php
// AssistantEventBroadcaster.php - Single wiretap callable
public function wiretap(): callable
{
    return function (Event $event): void {
        match (true) {
            $event instanceof StreamEventReceived => $this->handleStreamChunk($event),
            $event instanceof AgentStepStarted => $this->handleStepStarted($event),
            $event instanceof AgentStepCompleted => $this->handleStepCompleted($event),
            $event instanceof ToolCallStarted => $this->handleToolCallStarted($event),
            $event instanceof ToolCallCompleted => $this->handleToolCallCompleted($event),
            $event instanceof ContinuationEvaluated => $this->handleContinuationEvaluated($event),
            default => null,
        };
    };
}

// Usage: One line
$agent->wiretap($broadcaster->wiretap());
```

### Recommended Addition

Add a `wiretap()` method that returns a callable handling all events:

```php
// ReverbAgentEventAdapter.php

use Cognesy\Events\Event;
use Cognesy\Polyglot\Inference\Events\StreamEventReceived;

/**
 * Returns a wiretap callable that handles all supported events.
 *
 * Usage:
 *   $adapter = new ReverbAgentEventAdapter($broadcaster, $sessionId, $executionId);
 *   $agent->wiretap($adapter->wiretap());
 *
 * @return callable(Event): void
 */
public function wiretap(): callable
{
    return function (Event $event): void {
        match (true) {
            $event instanceof StreamEventReceived => $this->onStreamChunk($event),
            $event instanceof AgentStepStarted => $this->onAgentStepStarted($event),
            $event instanceof AgentStepCompleted => $this->onAgentStepCompleted($event),
            $event instanceof ToolCallStarted => $this->onToolCallStarted($event),
            $event instanceof ToolCallCompleted => $this->onToolCallCompleted($event),
            $event instanceof ContinuationEvaluated => $this->onContinuationEvaluated($event),
            default => null,
        };
    };
}
```

**Benefits:**
- Single line integration: `$agent->wiretap($adapter->wiretap())`
- All events handled automatically
- No risk of forgetting to wire an event
- Matches existing `wiretap()` pattern users already know

---

## Gap 3: Status Change Broadcasting

### The Problem

`onAgentStatusChanged()` exists but requires manual invocation. There's no automatic status tracking based on agent lifecycle events.

### Recommended Enhancement

Track status internally and emit on transitions:

```php
// ReverbAgentEventAdapter.php

private string $currentStatus = 'idle';

public function onAgentStepStarted(AgentStepStarted $event): void
{
    $this->transitionStatus('processing');

    $this->emit('agent.step.started', [
        'step_number' => $event->stepNumber,
        'message_count' => $event->messageCount ?? 0,
        'available_tools' => $event->availableTools ?? 0,
    ]);
}

public function onContinuationEvaluated(ContinuationEvaluated $event): void
{
    // Existing logic...

    // Auto-transition on completion
    if (!$event->outcome->shouldContinue) {
        $finalStatus = match ($event->outcome->stopReason) {
            StopReason::Completed => 'completed',
            StopReason::ErrorForbade => 'failed',
            StopReason::UserRequested => 'cancelled',
            default => 'stopped',
        };
        $this->transitionStatus($finalStatus);
    }
}

private function transitionStatus(string $newStatus): void
{
    if ($this->currentStatus === $newStatus) {
        return;
    }

    $previousStatus = $this->currentStatus;
    $this->currentStatus = $newStatus;

    $this->emit('agent.status', [
        'status' => $newStatus,
        'previous_status' => $previousStatus,
        'error_message' => null,
    ]);
}
```

---

## Gap 4: Framework Bridge Examples

### The Problem

`CanBroadcastAgentEvents` is minimal, which is good for flexibility but leaves implementers guessing about best practices.

### Recommended: Reference Implementations

Provide ready-to-use implementations for common frameworks:

#### Laravel/Reverb Implementation

```php
// src/Agent/Broadcasting/Bridges/LaravelReverbBroadcaster.php

namespace Cognesy\Addons\Agent\Broadcasting\Bridges;

use Cognesy\Addons\Agent\Broadcasting\CanBroadcastAgentEvents;
use Illuminate\Support\Facades\Broadcast;

final class LaravelReverbBroadcaster implements CanBroadcastAgentEvents
{
    public function broadcast(string $channel, array $envelope): void
    {
        // Use Laravel's broadcast system
        Broadcast::channel($channel, fn() => true);

        event(new AgentBroadcastEvent($channel, $envelope));
    }
}

// Supporting event class
class AgentBroadcastEvent implements ShouldBroadcast
{
    public function __construct(
        private string $channel,
        private array $envelope,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel($this->channel)];
    }

    public function broadcastAs(): string
    {
        return $this->envelope['type'];
    }

    public function broadcastWith(): array
    {
        return $this->envelope;
    }
}
```

#### Pusher Implementation

```php
// src/Agent/Broadcasting/Bridges/PusherBroadcaster.php

namespace Cognesy\Addons\Agent\Broadcasting\Bridges;

use Cognesy\Addons\Agent\Broadcasting\CanBroadcastAgentEvents;
use Pusher\Pusher;

final class PusherBroadcaster implements CanBroadcastAgentEvents
{
    public function __construct(
        private Pusher $pusher,
    ) {}

    public function broadcast(string $channel, array $envelope): void
    {
        $this->pusher->trigger(
            "private-{$channel}",
            $envelope['type'],
            $envelope
        );
    }
}
```

#### WebSocket (Ratchet/Swoole) Implementation

```php
// src/Agent/Broadcasting/Bridges/WebSocketBroadcaster.php

namespace Cognesy\Addons\Agent\Broadcasting\Bridges;

use Cognesy\Addons\Agent\Broadcasting\CanBroadcastAgentEvents;

final class WebSocketBroadcaster implements CanBroadcastAgentEvents
{
    /** @var array<string, array<\SplObjectStorage>> */
    private array $channels = [];

    public function subscribe(string $channel, object $connection): void
    {
        $this->channels[$channel] ??= new \SplObjectStorage();
        $this->channels[$channel]->attach($connection);
    }

    public function broadcast(string $channel, array $envelope): void
    {
        $json = json_encode($envelope, JSON_THROW_ON_ERROR);

        foreach ($this->channels[$channel] ?? [] as $connection) {
            $connection->send($json);
        }
    }
}
```

#### Console/Debug Implementation

```php
// src/Agent/Broadcasting/Bridges/ConsoleBroadcaster.php

namespace Cognesy\Addons\Agent\Broadcasting\Bridges;

use Cognesy\Addons\Agent\Broadcasting\CanBroadcastAgentEvents;

final class ConsoleBroadcaster implements CanBroadcastAgentEvents
{
    public function __construct(
        private bool $verbose = false,
    ) {}

    public function broadcast(string $channel, array $envelope): void
    {
        $type = $envelope['type'] ?? 'unknown';
        $payload = $envelope['payload'] ?? [];

        echo "[{$type}] ";

        if ($this->verbose) {
            echo json_encode($payload, JSON_PRETTY_PRINT);
        } else {
            echo $this->summarize($type, $payload);
        }

        echo "\n";
    }

    private function summarize(string $type, array $payload): string
    {
        return match ($type) {
            'agent.step.started' => "Step {$payload['step_number']} started",
            'agent.step.completed' => "Step {$payload['step_number']} completed ({$payload['duration_ms']}ms)",
            'agent.tool.started' => "Tool: {$payload['tool_name']}",
            'agent.tool.completed' => "Tool done: {$payload['tool_name']} " . ($payload['success'] ? '✓' : '✗'),
            'agent.stream.chunk' => "..." . substr($payload['content'] ?? '', 0, 20),
            'agent.status' => "Status: {$payload['status']}",
            default => json_encode($payload),
        };
    }
}
```

---

## Gap 5: Builder Pattern for Configuration

### The Problem

Constructor has multiple parameters, some optional. Adding more features increases complexity.

### Recommended: Fluent Builder

```php
// ReverbAgentEventAdapterBuilder.php

final class ReverbAgentEventAdapterBuilder
{
    private ?CanBroadcastAgentEvents $broadcaster = null;
    private ?string $sessionId = null;
    private ?string $executionId = null;
    private bool $includeContinuationTrace = false;
    private bool $includeStreamChunks = true;
    private bool $includeToolArgs = false;
    private int $maxArgLength = 100;

    public static function create(): self
    {
        return new self();
    }

    public function withBroadcaster(CanBroadcastAgentEvents $broadcaster): self
    {
        $this->broadcaster = $broadcaster;
        return $this;
    }

    public function forSession(string $sessionId, string $executionId): self
    {
        $this->sessionId = $sessionId;
        $this->executionId = $executionId;
        return $this;
    }

    public function withContinuationTrace(bool $include = true): self
    {
        $this->includeContinuationTrace = $include;
        return $this;
    }

    public function withStreamChunks(bool $include = true): self
    {
        $this->includeStreamChunks = $include;
        return $this;
    }

    public function withToolArgs(bool $include = true, int $maxLength = 100): self
    {
        $this->includeToolArgs = $include;
        $this->maxArgLength = $maxLength;
        return $this;
    }

    public function build(): ReverbAgentEventAdapter
    {
        if ($this->broadcaster === null) {
            throw new \InvalidArgumentException('Broadcaster is required');
        }
        if ($this->sessionId === null || $this->executionId === null) {
            throw new \InvalidArgumentException('Session and execution IDs are required');
        }

        return new ReverbAgentEventAdapter(
            broadcaster: $this->broadcaster,
            sessionId: $this->sessionId,
            executionId: $this->executionId,
            config: new ReverbAdapterConfig(
                includeContinuationTrace: $this->includeContinuationTrace,
                includeStreamChunks: $this->includeStreamChunks,
                includeToolArgs: $this->includeToolArgs,
                maxArgLength: $this->maxArgLength,
            ),
        );
    }
}

// Usage:
$adapter = ReverbAgentEventAdapterBuilder::create()
    ->withBroadcaster(new LaravelReverbBroadcaster())
    ->forSession($sessionId, $executionId)
    ->withContinuationTrace()
    ->withStreamChunks()
    ->build();

$agent->wiretap($adapter->wiretap());
```

---

## Complete Enhanced Implementation

Here's how all recommendations come together:

```php
<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Broadcasting;

use Cognesy\Addons\Agent\Events\AgentStepCompleted;
use Cognesy\Addons\Agent\Events\AgentStepStarted;
use Cognesy\Addons\Agent\Events\ContinuationEvaluated;
use Cognesy\Addons\Agent\Events\ToolCallCompleted;
use Cognesy\Addons\Agent\Events\ToolCallStarted;
use Cognesy\Addons\StepByStep\Continuation\ContinuationEvaluation;
use Cognesy\Addons\StepByStep\Continuation\StopReason;
use Cognesy\Events\Event;
use Cognesy\Polyglot\Inference\Events\StreamEventReceived;
use DateTimeImmutable;

final class ReverbAgentEventAdapter
{
    private int $chunkCounter = 0;
    private string $currentStatus = 'idle';

    public function __construct(
        private readonly CanBroadcastAgentEvents $broadcaster,
        private readonly string $sessionId,
        private readonly string $executionId,
        private readonly ReverbAdapterConfig $config = new ReverbAdapterConfig(),
    ) {}

    /**
     * Returns a wiretap callable that handles all supported events.
     *
     * @return callable(Event): void
     */
    public function wiretap(): callable
    {
        return function (Event $event): void {
            match (true) {
                $event instanceof StreamEventReceived => $this->onStreamChunk($event),
                $event instanceof AgentStepStarted => $this->onAgentStepStarted($event),
                $event instanceof AgentStepCompleted => $this->onAgentStepCompleted($event),
                $event instanceof ToolCallStarted => $this->onToolCallStarted($event),
                $event instanceof ToolCallCompleted => $this->onToolCallCompleted($event),
                $event instanceof ContinuationEvaluated => $this->onContinuationEvaluated($event),
                default => null,
            };
        };
    }

    public function onStreamChunk(StreamEventReceived $event): void
    {
        if (!$this->config->includeStreamChunks) {
            return;
        }

        $content = $event->content;
        if ($content === '') {
            return;
        }

        $this->emit('agent.stream.chunk', [
            'content' => $content,
            'is_complete' => false,
            'chunk_index' => $this->chunkCounter++,
        ]);
    }

    public function onAgentStepStarted(AgentStepStarted $event): void
    {
        $this->transitionStatus('processing');

        $this->emit('agent.step.started', [
            'step_number' => $event->stepNumber,
            'message_count' => $event->messageCount ?? 0,
            'available_tools' => $event->availableTools ?? 0,
        ]);
    }

    public function onAgentStepCompleted(AgentStepCompleted $event): void
    {
        $this->emit('agent.step.completed', [
            'step_number' => $event->stepNumber,
            'has_tool_calls' => $event->hasToolCalls,
            'errors' => $event->errorCount,
            'finish_reason' => $event->finishReason?->value,
            'usage' => $event->usage->toArray(),
            'duration_ms' => $event->durationMs,
        ]);
    }

    public function onToolCallStarted(ToolCallStarted $event): void
    {
        $payload = [
            'tool_name' => $event->tool,
            'tool_call_id' => $event->toolCallId ?? null,
        ];

        if ($this->config->includeToolArgs) {
            $payload['args'] = $this->truncateArgs(
                is_array($event->args) ? $event->args : [],
                $this->config->maxArgLength
            );
        } else {
            $payload['args_summary'] = $this->summarizeArgs(
                is_array($event->args) ? $event->args : []
            );
        }

        $this->emit('agent.tool.started', $payload);
    }

    public function onToolCallCompleted(ToolCallCompleted $event): void
    {
        $this->emit('agent.tool.completed', [
            'tool_name' => $event->tool,
            'tool_call_id' => $event->toolCallId ?? null,
            'success' => $event->success,
            'error' => $event->error,
            'duration_ms' => $this->durationMs($event->startedAt, $event->endedAt),
        ]);
    }

    public function onContinuationEvaluated(ContinuationEvaluated $event): void
    {
        // Auto-transition status on stop
        if (!$event->outcome->shouldContinue) {
            $finalStatus = match ($event->outcome->stopReason) {
                StopReason::Completed => 'completed',
                StopReason::ErrorForbade => 'failed',
                StopReason::UserRequested => 'cancelled',
                default => 'stopped',
            };
            $this->transitionStatus($finalStatus);

            // Reset chunk counter for next execution
            $this->chunkCounter = 0;
        }

        if (!$this->config->includeContinuationTrace) {
            return;
        }

        $this->emit('agent.continuation', [
            'step_number' => $event->stepNumber,
            'should_continue' => $event->outcome->shouldContinue,
            'stop_reason' => $event->outcome->stopReason->value,
            'resolved_by' => $event->outcome->resolvedBy,
            'evaluations' => array_map(
                static fn(ContinuationEvaluation $eval): array => [
                    'criterion' => basename(str_replace('\\', '/', $eval->criterionClass)),
                    'decision' => $eval->decision->value,
                    'reason' => $eval->reason,
                ],
                $event->outcome->evaluations,
            ),
        ]);
    }

    private function transitionStatus(string $newStatus): void
    {
        if ($this->currentStatus === $newStatus) {
            return;
        }

        $previousStatus = $this->currentStatus;
        $this->currentStatus = $newStatus;

        $this->emit('agent.status', [
            'status' => $newStatus,
            'previous_status' => $previousStatus,
        ]);
    }

    private function emit(string $type, array $payload): void
    {
        $this->broadcaster->broadcast(
            channel: "agent.{$this->sessionId}",
            envelope: [
                'type' => $type,
                'session_id' => $this->sessionId,
                'execution_id' => $this->executionId,
                'timestamp' => (new DateTimeImmutable())->format('Y-m-d\TH:i:s.v\Z'),
                'payload' => $payload,
            ],
        );
    }

    private function summarizeArgs(array $args): string
    {
        $parts = [];
        foreach (array_slice($args, 0, 3, true) as $key => $value) {
            $valueStr = is_string($value) ? "'{$value}'" : json_encode($value);
            if ($valueStr === false) {
                $valueStr = 'null';
            }
            if (strlen($valueStr) > 30) {
                $valueStr = substr($valueStr, 0, 27) . '...';
            }
            $parts[] = "{$key}: {$valueStr}";
        }
        return implode(', ', $parts);
    }

    private function truncateArgs(array $args, int $maxLength): array
    {
        $result = [];
        foreach ($args as $key => $value) {
            if (is_string($value) && strlen($value) > $maxLength) {
                $result[$key] = substr($value, 0, $maxLength) . '...';
            } else {
                $result[$key] = $value;
            }
        }
        return $result;
    }

    private function durationMs(DateTimeImmutable $startedAt, DateTimeImmutable $endedAt): int
    {
        $diff = $endedAt->getTimestamp() - $startedAt->getTimestamp();
        $microDiff = (int)($endedAt->format('u')) - (int)($startedAt->format('u'));
        return ($diff * 1000) + (int)($microDiff / 1000);
    }
}
```

**Configuration class:**

```php
<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Broadcasting;

final readonly class ReverbAdapterConfig
{
    public function __construct(
        public bool $includeContinuationTrace = false,
        public bool $includeStreamChunks = true,
        public bool $includeToolArgs = false,
        public int $maxArgLength = 100,
    ) {}

    public static function minimal(): self
    {
        return new self(
            includeContinuationTrace: false,
            includeStreamChunks: false,
            includeToolArgs: false,
        );
    }

    public static function standard(): self
    {
        return new self(
            includeContinuationTrace: false,
            includeStreamChunks: true,
            includeToolArgs: false,
        );
    }

    public static function debug(): self
    {
        return new self(
            includeContinuationTrace: true,
            includeStreamChunks: true,
            includeToolArgs: true,
            maxArgLength: 500,
        );
    }
}
```

---

## Summary of Recommendations

| Priority | Enhancement | Benefit |
|----------|-------------|---------|
| **Critical** | Add `StreamEventReceived` handling | Real-time chat UX |
| **High** | Add `wiretap()` method | Single-line integration |
| **Medium** | Auto status transitions | Lifecycle tracking |
| **Medium** | Framework bridge examples | Faster adoption |
| **Low** | Builder pattern | Cleaner configuration |
| **Low** | Config presets | Common use cases |

---

## Questions for Cognesy Team

1. **Streaming granularity**: Should we also emit `agent.stream.complete` when the full response is assembled, or let consumers track that client-side?

2. **Tool call IDs**: `ToolCallStarted` currently doesn't have `toolCallId`. Would adding it help correlate started/completed events?

3. **Error details**: Should `onToolCallCompleted` include stack trace in debug mode, or is that too much for broadcast payloads?

4. **Channel naming**: Is `agent.{sessionId}` the right pattern, or should it be `private-agent.{sessionId}` to align with Pusher/Reverb conventions?

---

Thank you for the collaborative approach. These enhancements would make the adapter production-ready for real-time chat applications.

**Files referenced:**
- `packages/platform-feat-assistant/src/.../Services/AssistantEventBroadcaster.php` (our implementation)
- `packages/addons/src/Agent/Broadcasting/ReverbAgentEventAdapter.php` (your implementation)

---

# Appendix: Implementation Response

**Date**: 2026-01-16
**From**: Cognesy Team
**Status**: Implemented

---

## Implementation Decisions

We reviewed the Partnerspot recommendations and implemented a simplified approach that achieves the core goals with less complexity.

| Gap | Recommendation | Decision | Rationale |
|-----|----------------|----------|-----------|
| **Gap 1** | StreamEventReceived handling | **Implemented** | Critical for chat UX |
| **Gap 2** | wiretap() method | **Implemented** | Single-line integration |
| **Gap 3** | Auto status transitions | **Implemented** | High value, minimal overhead |
| **Gap 4** | Framework bridges | **Deferred** | Documentation preferred over framework coupling |
| **Gap 5** | Builder pattern | **Rejected** | Config object with presets suffices |

### Why No Framework Bridges?

1. **Maintenance burden**: Framework-specific code creates version coupling
2. **Interface simplicity**: `CanBroadcastAgentEvents` is trivial to implement (one method)
3. **Flexibility**: Users can integrate exactly as their stack requires
4. **Documentation over code**: Examples are more valuable than shipped implementations that may not fit

### Why No Builder Pattern?

1. **Minimal parameters**: Only 4-5 configuration options
2. **PHP 8 named arguments**: Already provide clarity without Builder overhead
3. **Config object with presets**: Achieves discoverability without extra class

---

## Files Changed

### New: `BroadcastConfig`

```
packages/addons/src/Agent/Broadcasting/BroadcastConfig.php
```

Configuration value object with static factory presets.

### Modified: `ReverbAgentEventAdapter`

```
packages/addons/src/Agent/Broadcasting/ReverbAgentEventAdapter.php
```

Added streaming, wiretap(), and automatic status tracking.

---

## Answers to Partnerspot Questions

**Q1: Streaming granularity - should we emit `agent.stream.complete`?**

> No. The `is_complete` flag in chunk payloads and the automatic status transition to `completed` provide sufficient signals. Consumers can track stream completion client-side by observing the status change.

**Q2: Tool call IDs - would adding `toolCallId` help?**

> The field exists in the payload (currently `null`). When `ToolCallStarted` event gains a `toolCallId` property, the adapter will automatically include it. No adapter changes needed.

**Q3: Error details - stack trace in debug mode?**

> Not included. Stack traces are verbose and may leak sensitive information. The `error` field in `agent.tool.completed` provides the error message. Full traces belong in server-side logs, not broadcast payloads.

**Q4: Channel naming - `agent.{sessionId}` vs `private-agent.{sessionId}`?**

> Kept as `agent.{sessionId}`. The `private-` prefix is a Pusher/Reverb convention that consumers can apply in their broadcaster implementation. Keeping the channel name generic allows flexibility across different backends.

---

## Usage Patterns

### Pattern 1: Basic Integration (Recommended)

Single-line setup using wiretap:

```php
use Cognesy\Addons\Agent\Broadcasting\ReverbAgentEventAdapter;

// Create adapter
$adapter = new ReverbAgentEventAdapter(
    broadcaster: $myBroadcaster,
    sessionId: $sessionId,
    executionId: $executionId,
);

// Wire to agent (one line!)
$agent->wiretap($adapter->wiretap());

// Run agent - all events automatically broadcast
$result = $agent->run($task);
```

### Pattern 2: Config Presets

```php
use Cognesy\Addons\Agent\Broadcasting\BroadcastConfig;

// Minimal: status events only (no streaming)
$adapter = new ReverbAgentEventAdapter(
    $broadcaster, $sessionId, $executionId,
    BroadcastConfig::minimal(),
);

// Standard: status + streaming (default)
$adapter = new ReverbAgentEventAdapter(
    $broadcaster, $sessionId, $executionId,
    BroadcastConfig::standard(),
);

// Debug: everything including continuation trace and full tool args
$adapter = new ReverbAgentEventAdapter(
    $broadcaster, $sessionId, $executionId,
    BroadcastConfig::debug(),
);
```

### Pattern 3: Custom Configuration

```php
$adapter = new ReverbAgentEventAdapter(
    broadcaster: $broadcaster,
    sessionId: $sessionId,
    executionId: $executionId,
    config: new BroadcastConfig(
        includeStreamChunks: true,
        includeContinuationTrace: false,
        includeToolArgs: true,
        maxArgLength: 200,
        autoStatusTracking: true,
    ),
);
```

### Pattern 4: Implementing a Broadcaster

```php
use Cognesy\Addons\Agent\Broadcasting\CanBroadcastAgentEvents;

// Laravel Reverb example
final class LaravelReverbBroadcaster implements CanBroadcastAgentEvents
{
    public function broadcast(string $channel, array $envelope): void
    {
        // Add 'private-' prefix for Reverb convention
        broadcast(new AgentEvent("private-{$channel}", $envelope));
    }
}

// Pusher example
final class PusherBroadcaster implements CanBroadcastAgentEvents
{
    public function __construct(private Pusher $pusher) {}

    public function broadcast(string $channel, array $envelope): void
    {
        $this->pusher->trigger(
            "private-{$channel}",
            $envelope['type'],
            $envelope
        );
    }
}

// Console/Debug example
final class ConsoleBroadcaster implements CanBroadcastAgentEvents
{
    public function broadcast(string $channel, array $envelope): void
    {
        $type = $envelope['type'];
        $payload = json_encode($envelope['payload']);
        echo "[{$type}] {$payload}\n";
    }
}
```

### Pattern 5: Reusing Adapter Across Executions

```php
$adapter = new ReverbAgentEventAdapter($broadcaster, $sessionId, $executionId);
$agent->wiretap($adapter->wiretap());

// First execution
$result1 = $agent->run($task1);

// Reset for second execution (clears chunk counter and status)
$adapter->reset();

// Second execution
$result2 = $agent->run($task2);
```

### Pattern 6: Legacy Per-Event Wiring (Still Supported)

```php
// Individual event handlers still work for fine-grained control
$agent->onEvent(AgentStepStarted::class, [$adapter, 'onAgentStepStarted']);
$agent->onEvent(AgentStepCompleted::class, [$adapter, 'onAgentStepCompleted']);
$agent->onEvent(ToolCallStarted::class, [$adapter, 'onToolCallStarted']);
$agent->onEvent(ToolCallCompleted::class, [$adapter, 'onToolCallCompleted']);
$agent->onEvent(ContinuationEvaluated::class, [$adapter, 'onContinuationEvaluated']);
$agent->onEvent(StreamEventReceived::class, [$adapter, 'onStreamChunk']);
```

---

## Event Types Reference

### Broadcast Events

| Event Type | Trigger | Payload |
|------------|---------|---------|
| `agent.status` | Lifecycle transitions | `{status, previous_status}` |
| `agent.step.started` | AgentStepStarted | `{step_number, message_count, available_tools}` |
| `agent.step.completed` | AgentStepCompleted | `{step_number, has_tool_calls, errors, finish_reason, usage, duration_ms}` |
| `agent.tool.started` | ToolCallStarted | `{tool_name, tool_call_id, args_summary}` or `{..., args}` in debug |
| `agent.tool.completed` | ToolCallCompleted | `{tool_name, tool_call_id, success, error, duration_ms}` |
| `agent.stream.chunk` | StreamEventReceived | `{content, is_complete, chunk_index}` |
| `agent.continuation` | ContinuationEvaluated (if enabled) | `{step_number, should_continue, stop_reason, resolved_by, evaluations}` |

### Status Values

| Status | Meaning |
|--------|---------|
| `idle` | Initial state, waiting for execution |
| `processing` | Agent is executing steps |
| `completed` | Agent finished successfully (StopReason::Completed) |
| `failed` | Agent encountered an error (StopReason::ErrorForbade) |
| `cancelled` | User requested stop (StopReason::UserRequested) |
| `stopped` | Other stop reasons (steps limit, etc.) |

### Envelope Format

All events are wrapped in a standard envelope:

```json
{
    "type": "agent.stream.chunk",
    "session_id": "sess-abc123",
    "execution_id": "exec-xyz789",
    "timestamp": "2026-01-16T12:00:00.123Z",
    "payload": {
        "content": "Hello",
        "is_complete": false,
        "chunk_index": 0
    }
}
```

---

## Test Coverage

13 tests covering:
- Step event emission
- Tool call event emission
- Continuation events (when enabled)
- wiretap() callable functionality
- StreamEventReceived handling
- Empty content filtering
- Automatic status transitions
- StopReason to status mapping
- Status deduplication
- Config presets
- Minimal config behavior
- Debug mode tool args
- Adapter reset functionality
