# Change Request: InstructorPHP Agent Framework Improvements

**Date**: 2026-01-16
**Status**: Feedback for InstructorPHP/Cognesy team
**Type**: Study / External Feedback

## Executive Summary

This document captures practical challenges encountered while implementing a production agent system using InstructorPHP's agent framework (`Cognesy\Addons\Agent`). The issues span observability, correctness, debugging, and state management. We offer concrete recommendations for API/design improvements.

**Context**: Partner Management Assistant with tools (SearchEntities, CreateProgramLead, DeleteProgramLead) helping users manage partner relationships via natural language.

---

## Issue 1: Tool Call Arguments Leak into Message Content

### Problem

In `ToolCallingDriver::buildStepFromResponse()` (~line 120):

```php
$messages = Messages::fromMessages([
    Message::asAssistant($response->content()),  // <-- Problem
    Message::asTool(...),
]);
```

When LLM makes a tool call, `$response->content()` contains **tool call arguments** (JSON), not natural language:

```json
{"query":"dbplus","types":["program","program_lead"]}
```

### Impact

- UI displays raw JSON instead of meaningful response
- Conversation history polluted with tool args as "assistant" messages
- We had to add heuristic filtering:

```php
// Our workaround
private function looksLikeToolCallArgs(string $content): bool
{
    $trimmed = trim($content);
    if (!str_starts_with($trimmed, '{')) return false;
    $decoded = json_decode($trimmed, true);
    // ... heuristic checks for natural language ...
}
```

### Recommendation

Don't append `$response->content()` when tool calls are present, or mark messages with metadata for filtering.

---

## Issue 2: ContinuationCriteria Lacks Observability

### Problem

`ContinuationCriteria::decide()` uses flat priority logic:

```php
// ForbidContinuation > RequestContinuation > AllowStop > AllowContinuation
if (in_array(ContinuationDecision::ForbidContinuation, $decisions, true)) {
    return ContinuationDecision::ForbidContinuation;
}
```

Debugging why agent stopped after 1 step required manually instantiating each criterion and logging results.

### Recommendation

**Rich decision objects** with reasons:

```php
class ContinuationResult
{
    public function __construct(
        public readonly ContinuationDecision $decision,
        public readonly string $criterionClass,
        public readonly string $reason,
        public readonly array $context = [],
    ) {}
}

// Returns: ContinuationResult(
//     decision: ForbidContinuation,
//     criterionClass: 'ExecutionTimeLimit',
//     reason: 'Execution time 45.2s exceeded limit 30s',
// )
```

**Add decision trace to AgentState** for debugging.

---

## Issue 3: AgentState Serialization Issues with Time-Based Fields

### Problem

`AgentState` has `DateTimeImmutable $startedAt`. When serialized to DB for pause/resume, then restored later:
- `startedAt` is original start time
- `ExecutionTimeLimit` calculates `now() - startedAt`
- If paused overnight, elapsed appears as 12+ hours → immediate stop

### Recommendation

Track **cumulative execution time** instead of wall clock:

```php
class AgentState
{
    private float $cumulativeExecutionSeconds = 0.0;

    public function recordStepDuration(float $seconds): self
    {
        return $this->with(
            cumulativeExecutionSeconds: $this->cumulativeExecutionSeconds + $seconds
        );
    }
}
```

---

## Issue 4: MessageRole Enum Not Exported for Consumers

### Problem

Had to import internal enum to process messages:

```php
use Cognesy\Messages\Enums\MessageRole as CognesyMessageRole;

$messageRole = match ($cognesyRole) {
    CognesyMessageRole::Assistant => MessageRole::Assistant,
    CognesyMessageRole::Tool => MessageRole::Tool,
    default => null,
};
```

### Recommendation

Add helper methods to Message class:

```php
class Message
{
    public function isAssistant(): bool;
    public function isTool(): bool;
    public function isUser(): bool;
}
```

---

## Issue 5: No Built-in Execution Logging/Tracing

### Problem

No visibility into:
- Why continuation criteria decided to stop
- What LLM returned vs what was processed
- Token usage per step
- Tool execution timing

We added custom `wiretap()` handlers, but this is ad-hoc.

### Recommendation

**Built-in structured execution trace**:

```php
class ExecutionTrace
{
    public array $steps = [];
}

class StepTrace
{
    public int $stepNumber;
    public float $durationMs;
    public array $inputMessages;
    public array $outputMessages;
    public ?ToolCallTrace $toolCall;
    public ContinuationResult $continuationDecision;
    public array $tokenUsage;
}

// Access: $state->executionTrace();
```

**PSR-3 Logger integration**:

```php
$agent = AgentBuilder::base()
    ->withLogger($psrLogger, LogLevel::DEBUG)
    ->build();
```

---

## Issue 6: Agent Stops After One Step Despite Tool Calls

### Problem

Agent makes tool call, executes successfully, then stops instead of continuing. `ToolCallPresenceCheck` correctly returned `RequestContinuation`, but agent still stopped.

### Recommendation

- Explicit continuation logging in `Agent::iterate()`
- Clear continuation mode configuration:

```php
$agent = AgentBuilder::base()
    ->withContinuationMode(ContinuationMode::UntilNoToolCalls)
    ->build();
```

---

## Summary of Recommendations

| Issue | Priority | Effort | Recommendation |
|-------|----------|--------|----------------|
| Tool args in message content | **High** | Low | Don't append content when tool calls present |
| ContinuationCriteria observability | **High** | Medium | Rich decision objects with reasons |
| Time-based state issues | Medium | Medium | Track cumulative execution time |
| MessageRole enum export | Low | Low | Add helper methods |
| Execution logging | **High** | Medium | Built-in structured trace + PSR-3 |
| Single-step stopping | **High** | Medium | Better continuation loop visibility |

---

## Code References

- `ToolCallingDriver::buildStepFromResponse()` - Line ~120
- `ContinuationCriteria::decide()` - Line ~27
- `AgentState::fromArray()` - Serialization logic
- `ToolCallPresenceCheck::decide()` - Tool call detection

---

## Next Steps

1. Share this document with InstructorPHP/Cognesy maintainers
2. Discuss and prioritize based on team roadmap
3. Consider contributing patches for low-effort fixes (Issues 1, 4)
4. Design RFC for larger changes (Issues 2, 5)

We're happy to provide more detailed code examples, test cases, or contribute patches.
