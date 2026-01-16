# Slim Serialization + Event-to-Reverb Adapter Proposal

## Motivation
- `AgentState::toArray()` is heavy (full message history, step history, raw inference response payloads). This is risky for DB persistence and bandwidth in commercial apps.
- Reverb/Echo integrations need a stable, compact event schema that maps cleanly to UI components without leaking internal structure.

Key references:
- `packages/addons/src/Agent/Core/Data/AgentState.php`
- `packages/addons/src/Agent/Core/Data/AgentStep.php`
- `packages/polyglot/src/Inference/Data/InferenceResponse.php`
- `packages/addons/src/Agent/Events/*`

---

## Slim Serialization Format

### Goals
- Preserve enough context to resume or display the conversation.
- Avoid storing provider-specific raw payloads.
- Allow opt-in inclusion of tool metadata.
- Support cumulative execution time for pause/resume workflows.

### Proposed Snapshot Schema (JSON)
```json
{
  "agent_id": "uuid-string",
  "parent_agent_id": "uuid-string|null",
  "status": "in_progress|completed|failed",
  "step_count": 3,
  "usage": { "prompt": 1200, "completion": 600, "total": 1800 },
  "execution": {
    "started_at": "2026-01-16T10:00:00Z",
    "updated_at": "2026-01-16T10:05:00Z",
    "cumulative_seconds": 45.2
  },
  "messages": [
    { "role": "user", "content": "...", "metadata": {} },
    { "role": "assistant", "content": "...", "metadata": { "tool_calls": [{"id": "...", "name": "..."}] } },
    { "role": "tool", "content": "...", "metadata": { "tool_call_id": "...", "tool_name": "..." } }
  ],
  "steps": [
    {
      "step_number": 1,
      "type": "tool_execution|final|error",
      "has_tool_calls": true,
      "finish_reason": "tool_calls|stop|length|error",
      "errors": 0,
      "usage": { "total": 500 },
      "duration_ms": 1250.5,
      "tool_calls": [{ "id": "...", "name": "..." }]
    }
  ],
  "last_continuation": {
    "should_continue": false,
    "stop_reason": "completed",
    "resolved_by": "ToolCallPresenceCheck"
  },
  "metadata": { "custom_key": "custom_value" }
}
```

### Field Descriptions

| Field | Required | Description |
|-------|----------|-------------|
| `agent_id` | Yes | Unique identifier for the agent instance |
| `parent_agent_id` | No | Parent agent ID for nested/sub-agent scenarios |
| `status` | Yes | Current execution status |
| `step_count` | Yes | Number of completed steps |
| `usage` | Yes | Aggregate token usage |
| `execution` | Yes | Timing information including cumulative seconds |
| `messages` | Yes | Bounded message history (see truncation policy) |
| `steps` | No | Step summaries (optional for resume, useful for diagnostics) |
| `last_continuation` | No | Last continuation decision for debugging |
| `metadata` | No | Custom application metadata |

### Message Truncation Policy

```php
final readonly class SlimSerializationConfig
{
    public function __construct(
        public int $maxMessages = 50,
        public int $maxSteps = 20,
        public int $maxContentLength = 2000,
        public bool $includeToolResults = true,
        public bool $includeSteps = true,
        public bool $includeContinuationTrace = false,
        public bool $redactToolArgs = false,
    ) {}

    public static function minimal(): self {
        return new self(
            maxMessages: 20,
            maxSteps: 0,
            maxContentLength: 500,
            includeToolResults: false,
            includeSteps: false,
        );
    }

    public static function standard(): self {
        return new self(); // Defaults
    }

    public static function full(): self {
        return new self(
            maxMessages: 100,
            maxSteps: 50,
            maxContentLength: 5000,
            includeToolResults: true,
            includeSteps: true,
            includeContinuationTrace: true,
        );
    }
}
```

### Serializer Interface

```php
interface CanSerializeAgentState
{
    public function serialize(AgentState $state): array;
    public function deserialize(array $data): AgentState;
}

final readonly class SlimAgentStateSerializer implements CanSerializeAgentState
{
    public function __construct(
        private SlimSerializationConfig $config = new SlimSerializationConfig(),
    ) {}

    public function serialize(AgentState $state): array {
        return [
            'agent_id' => $state->agentId,
            'parent_agent_id' => $state->parentAgentId,
            'status' => $state->status()->value,
            'step_count' => $state->stepCount(),
            'usage' => $state->usage()->toArray(),
            'execution' => $this->serializeExecution($state),
            'messages' => $this->serializeMessages($state),
            'steps' => $this->config->includeSteps
                ? $this->serializeSteps($state)
                : [],
            'last_continuation' => $this->config->includeContinuationTrace
                ? $this->serializeContinuation($state)
                : null,
            'metadata' => $state->metadata()->toArray(),
        ];
    }

    private function serializeMessages(AgentState $state): array {
        $messages = $state->messages()->toArray();

        // Truncate to maxMessages (keep most recent)
        if (count($messages) > $this->config->maxMessages) {
            $messages = array_slice($messages, -$this->config->maxMessages);
        }

        return array_map(fn($msg) => $this->serializeMessage($msg), $messages);
    }

    private function serializeMessage(array $message): array {
        $content = $message['content'] ?? '';
        if (is_string($content) && strlen($content) > $this->config->maxContentLength) {
            $content = substr($content, 0, $this->config->maxContentLength) . '...';
        }

        $metadata = $message['_metadata'] ?? [];

        // Optionally redact tool args
        if ($this->config->redactToolArgs && isset($metadata['tool_calls'])) {
            $metadata['tool_calls'] = array_map(fn($tc) => [
                'id' => $tc['id'] ?? null,
                'name' => $tc['name'] ?? $tc['function']['name'] ?? 'unknown',
            ], $metadata['tool_calls']);
        }

        // Optionally exclude tool results
        if (!$this->config->includeToolResults && ($message['role'] ?? '') === 'tool') {
            $content = '[tool result omitted]';
        }

        return [
            'role' => $message['role'] ?? 'user',
            'content' => $content,
            'metadata' => $metadata,
        ];
    }

    private function serializeExecution(AgentState $state): array {
        $stateInfo = $state->stateInfo();
        return [
            'started_at' => $stateInfo->startedAt()->format(DATE_ATOM),
            'updated_at' => $stateInfo->updatedAt()->format(DATE_ATOM),
            'cumulative_seconds' => $stateInfo->cumulativeExecutionSeconds(),
        ];
    }

    private function serializeSteps(AgentState $state): array {
        $steps = $state->steps()->all();
        if (count($steps) > $this->config->maxSteps) {
            $steps = array_slice($steps, -$this->config->maxSteps);
        }

        return array_map(fn($step, $i) => [
            'step_number' => $i + 1,
            'type' => $step->stepType()->value,
            'has_tool_calls' => $step->hasToolCalls(),
            'finish_reason' => $step->finishReason()?->value,
            'errors' => $step->errorCount(),
            'usage' => ['total' => $step->usage()->total()],
            'tool_calls' => array_map(fn($tc) => [
                'id' => $tc->id(),
                'name' => $tc->name(),
            ], $step->toolCalls()->all()),
        ], $steps, array_keys($steps));
    }

    private function serializeContinuation(AgentState $state): ?array {
        $outcome = $state->lastContinuationOutcome();
        if ($outcome === null) {
            return null;
        }

        return [
            'should_continue' => $outcome->shouldContinue,
            'stop_reason' => $outcome->stopReason->value,
            'resolved_by' => $outcome->resolvedBy,
        ];
    }

    public function deserialize(array $data): AgentState {
        // Reconstruct minimal state for resume
        // Note: Full message history may be truncated, steps may be missing
        // The agent can continue from the last known state
        return AgentState::fromArray([
            'agentId' => $data['agent_id'],
            'parentAgentId' => $data['parent_agent_id'],
            'status' => $data['status'],
            'usage' => $data['usage'],
            'metadata' => $data['metadata'] ?? [],
            'stateInfo' => [
                'startedAt' => $data['execution']['started_at'],
                'updatedAt' => $data['execution']['updated_at'],
                'cumulativeExecutionSeconds' => $data['execution']['cumulative_seconds'] ?? 0,
            ],
            'messageStore' => ['messages' => $data['messages']],
            // Steps are diagnostic only, not restored for resume
        ]);
    }
}
```

---

## Event-to-Reverb Adapter

### Goals
- One stable event schema for UI.
- Avoid hard-coding Cognesy event fields in the frontend.
- Preserve step timing, tool usage, and token usage.
- Include continuation trace when available.

### Proposed Event Envelope
```json
{
  "type": "agent.step.started|agent.step.completed|agent.tool.started|agent.tool.completed|agent.stream.chunk|agent.status|agent.continuation",
  "session_id": "...",
  "execution_id": "...",
  "timestamp": "2026-01-16T10:05:00.123Z",
  "payload": { ... }
}
```

### Event Types and Payloads

#### `agent.status`
Overall agent status change.
```json
{
  "status": "in_progress|completed|failed",
  "step_count": 3,
  "error_message": "...|null",
  "last_response": "...|null"
}
```

#### `agent.step.started`
Step execution beginning.
```json
{
  "step_number": 2,
  "message_count": 5,
  "available_tools": ["SearchEntities", "CreateProgramLead"]
}
```

#### `agent.step.completed`
Step execution finished.
```json
{
  "step_number": 2,
  "has_tool_calls": true,
  "errors": 0,
  "finish_reason": "tool_calls",
  "usage": { "prompt": 500, "completion": 150, "total": 650 },
  "duration_ms": 1250.5,
  "continuation": {
    "should_continue": true,
    "stop_reason": "completed",
    "resolved_by": "ToolCallPresenceCheck"
  }
}
```

#### `agent.tool.started`
Tool execution beginning.
```json
{
  "tool_name": "SearchEntities",
  "tool_call_id": "call_abc123",
  "args_summary": "query: 'dbplus', types: ['program']"
}
```

#### `agent.tool.completed`
Tool execution finished.
```json
{
  "tool_name": "SearchEntities",
  "tool_call_id": "call_abc123",
  "success": true,
  "error": null,
  "duration_ms": 250.0,
  "result_summary": "Found 3 entities"
}
```

#### `agent.stream.chunk`
Streaming content chunk.
```json
{
  "chunk": "Here is the response...",
  "is_complete": false,
  "tokens_delta": 5
}
```

#### `agent.continuation`
Continuation decision (optional, for debugging).
```json
{
  "step_number": 2,
  "should_continue": false,
  "stop_reason": "steps_limit",
  "resolved_by": "StepsLimit",
  "evaluations": [
    { "criterion": "StepsLimit", "decision": "forbid", "reason": "Step 20 exceeded limit 20" },
    { "criterion": "ToolCallPresenceCheck", "decision": "request", "reason": "Tool calls present" }
  ]
}
```

### Adapter Implementation

```php
interface CanBroadcastAgentEvents
{
    public function broadcast(string $channel, array $envelope): void;
}

final class ReverbAgentEventAdapter
{
    public function __construct(
        private CanBroadcastAgentEvents $broadcaster,
        private string $sessionId,
        private string $executionId,
        private bool $includeContinuationTrace = false,
    ) {}

    public function onAgentStepStarted(AgentStepStarted $event): void {
        $this->emit('agent.step.started', [
            'step_number' => $event->stepNumber,
            'message_count' => $event->messageCount ?? 0,
            'available_tools' => $event->availableTools ?? [],
        ]);
    }

    public function onAgentStepCompleted(AgentStepCompleted $event): void {
        $payload = [
            'step_number' => $event->stepNumber,
            'has_tool_calls' => $event->hasToolCalls,
            'errors' => $event->errorCount,
            'finish_reason' => $event->finishReason?->value,
            'usage' => $event->usage->toArray(),
            'duration_ms' => $event->durationMs,
        ];

        // Include continuation trace if available and enabled
        if ($this->includeContinuationTrace && isset($event->continuationOutcome)) {
            $payload['continuation'] = [
                'should_continue' => $event->continuationOutcome->shouldContinue,
                'stop_reason' => $event->continuationOutcome->stopReason->value,
                'resolved_by' => $event->continuationOutcome->resolvedBy,
            ];
        }

        $this->emit('agent.step.completed', $payload);
    }

    public function onToolCallStarted(ToolCallStarted $event): void {
        $this->emit('agent.tool.started', [
            'tool_name' => $event->tool,  // Note: correct key is 'tool', not 'name'
            'tool_call_id' => $event->toolCallId ?? null,
            'args_summary' => $this->summarizeArgs($event->args ?? []),
        ]);
    }

    public function onToolCallCompleted(ToolCallCompleted $event): void {
        $this->emit('agent.tool.completed', [
            'tool_name' => $event->tool,  // Note: correct key is 'tool'
            'tool_call_id' => $event->toolCallId ?? null,
            'success' => $event->success ?? true,
            'error' => $event->error ?? null,
            'duration_ms' => $event->durationMs ?? 0,
            'result_summary' => $this->summarizeResult($event->result ?? null),
        ]);
    }

    public function onContinuationEvaluated(ContinuationEvaluated $event): void {
        if (!$this->includeContinuationTrace) {
            return;
        }

        $this->emit('agent.continuation', [
            'step_number' => $event->stepNumber,
            'should_continue' => $event->outcome->shouldContinue,
            'stop_reason' => $event->outcome->stopReason->value,
            'resolved_by' => $event->outcome->resolvedBy,
            'evaluations' => array_map(fn($e) => [
                'criterion' => basename(str_replace('\\', '/', $e->criterionClass)),
                'decision' => $e->decision->value,
                'reason' => $e->reason,
            ], $event->outcome->evaluations),
        ]);
    }

    public function onAgentStatusChanged(string $status, ?string $error = null, ?string $lastResponse = null): void {
        $this->emit('agent.status', [
            'status' => $status,
            'error_message' => $error,
            'last_response' => $lastResponse,
        ]);
    }

    private function emit(string $type, array $payload): void {
        $this->broadcaster->broadcast(
            channel: "agent.{$this->sessionId}",
            envelope: [
                'type' => $type,
                'session_id' => $this->sessionId,
                'execution_id' => $this->executionId,
                'timestamp' => (new \DateTimeImmutable())->format('Y-m-d\TH:i:s.v\Z'),
                'payload' => $payload,
            ],
        );
    }

    private function summarizeArgs(array $args): string {
        $parts = [];
        foreach (array_slice($args, 0, 3) as $key => $value) {
            $valueStr = is_string($value) ? "'{$value}'" : json_encode($value);
            if (strlen($valueStr) > 30) {
                $valueStr = substr($valueStr, 0, 27) . '...';
            }
            $parts[] = "{$key}: {$valueStr}";
        }
        return implode(', ', $parts);
    }

    private function summarizeResult(mixed $result): ?string {
        if ($result === null) {
            return null;
        }
        $str = is_string($result) ? $result : json_encode($result);
        return strlen($str) > 100 ? substr($str, 0, 97) . '...' : $str;
    }
}
```

### Laravel Integration

```php
// In a service provider or event subscriber
$adapter = new ReverbAgentEventAdapter(
    broadcaster: new LaravelReverbBroadcaster(),
    sessionId: $session->id,
    executionId: $execution->id,
    includeContinuationTrace: config('agent.broadcast_continuation_trace', false),
);

$events->listen(AgentStepStarted::class, [$adapter, 'onAgentStepStarted']);
$events->listen(AgentStepCompleted::class, [$adapter, 'onAgentStepCompleted']);
$events->listen(ToolCallStarted::class, [$adapter, 'onToolCallStarted']);
$events->listen(ToolCallCompleted::class, [$adapter, 'onToolCallCompleted']);
$events->listen(ContinuationEvaluated::class, [$adapter, 'onContinuationEvaluated']);
```

---

## Design Decisions

| Question | Decision | Rationale |
|----------|----------|-----------|
| Exclude tool results by default? | **No**, include by default | Tool results are often needed for UI; use `redactToolArgs` for sensitive data |
| UI rely on events only or reconcile? | **Events + reconcile** | Stream events for real-time, fetch final state on completion for consistency |
| Canonical message sanitizer? | **Yes**, in serializer | `SlimAgentStateSerializer` handles truncation and redaction consistently |
| Include continuation trace in events? | **Optional**, off by default | Useful for debugging but adds payload size |

---

## Files to Create/Modify

| File | Type | Description |
|------|------|-------------|
| `packages/addons/src/Agent/Serialization/SlimSerializationConfig.php` | New | Configuration class |
| `packages/addons/src/Agent/Serialization/CanSerializeAgentState.php` | New | Interface |
| `packages/addons/src/Agent/Serialization/SlimAgentStateSerializer.php` | New | Implementation |
| `packages/addons/src/Agent/Broadcasting/CanBroadcastAgentEvents.php` | New | Interface |
| `packages/addons/src/Agent/Broadcasting/ReverbAgentEventAdapter.php` | New | Reverb adapter |
| `packages/addons/src/Agent/Events/ToolCallStarted.php` | Verify | Confirm `tool` key exists |
| `packages/addons/src/Agent/Events/ToolCallCompleted.php` | Verify | Confirm `tool`, `success`, `error` keys |

---

## Migration Notes

### For Partnerspot
1. Replace `AgentState::toArray()` calls with `SlimAgentStateSerializer::serialize()`.
2. Update `AssistantEventBroadcaster` to use `ReverbAgentEventAdapter`.
3. Fix event key mappings: `tool` not `name`, `success` not `status`.
4. Add `execution_id` and `timestamp` to envelope.
5. Frontend can progressively adopt new envelope format while keeping old field names in payload.

### Backward Compatibility
- Existing `AgentState::toArray()` unchanged (full serialization still available).
- Old event subscribers continue to work.
- Envelope wraps existing payloads, adding structure without removing fields.
