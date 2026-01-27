# Implementation Plan: Clean Conversation vs Execution Trace
**Date:** 2026-01-26  
**Status:** Proposed

## Goal
Separate ephemeral tool execution traces from persistent conversation messages while keeping LLM context intact during a single execution. The LLM must still see tool_call + tool_result pairs inside a step, but persistent state should store only user messages + final assistant responses.

## Summary of Fixes vs Original Plan
- **Processor gating**: `canProcess()` must return `true` for post-step processors because `StateProcessors` evaluates `canProcess()` *before* `$next` runs; `currentStep`/`continuationOutcome` are not yet available. Do state checks inside `process()` after `$next`.
- **Processor order**: With middleware unwinding, the first processor runs **last**. To clear buffers after execution, `ClearExecutionBuffer` must be **first** in the list (so it executes last).
- **Message parsing**: Use `Messages::filter()` + `Message::metadata()->hasKey()` rather than arrays. `Message::toArray()` uses `_metadata`, not `metadata`.
- **Error/continuation safety**: Clear execution buffer on `AgentState::forContinuation()` (and in `withUserMessage()` when reset is true) to avoid leaking tool traces when errors bypass processors.
- **Optional**: Prefer a dedicated processor (`AppendFinalResponse`) over branching in `AppendStepMessages` if you want stricter SRP.

## Design
### Message streams
- **messages (persistent)**: user messages + final assistant responses only.
- **execution_buffer (ephemeral)**: tool_call + tool_result messages produced within the current execution.

### Inference context
`messagesForInference()` should compile:
```
summary + buffer + messages + execution_buffer
```
so tool traces are visible to the LLM during execution but do not persist in `messages`.

## Implementation Steps

### Step 1: Add execution buffer section constant
**File:** `packages/agents/src/Agent/Data/AgentState.php`
```php
public const EXECUTION_BUFFER_SECTION = 'execution_buffer';
```

### Step 2: Include execution buffer in inference compilation
**File:** `packages/agents/src/Agent/Data/AgentState.php`
```php
public function messagesForInference(): Messages {
    return (new SelectedSections([
        'summary',
        'buffer',
        self::DEFAULT_SECTION,
        self::EXECUTION_BUFFER_SECTION,
    ]))->compile($this);
}
```

### Step 3: Clear execution buffer on continuation reset
**Why:** Errors/aborts can bypass processors; ensure a new user turn never inherits old tool traces.

**Files:**
- `packages/agents/src/Agent/Data/AgentState.php`

**Change:**
- In `forContinuation()`, clear the `execution_buffer` section.
- Ensure `withUserMessage(..., resetExecutionState = true)` uses `forContinuation()` as it does today.

Pseudo:
```php
public function forContinuation(): self {
    $store = $this->store
        ->section(self::EXECUTION_BUFFER_SECTION)
        ->setMessages(Messages::empty());
    return new self(
        status: AgentStatus::InProgress,
        currentExecution: null,
        currentStep: null,
        variables: $this->metadata,
        store: $store,
        agentId: $this->agentId,
        parentAgentId: $this->parentAgentId,
        createdAt: $this->createdAt,
        updatedAt: new DateTimeImmutable(),
        cache: new CachedContext(),
        stepExecutions: StepExecutions::empty(),
    );
}
```

### Step 4: Add `AppendToolTraceToBuffer` processor
**File:** `packages/agents/src/Agent/StateProcessing/Processors/AppendToolTraceToBuffer.php`

Key requirements:
- `canProcess()` returns `true`.
- Use `Messages::filter()` on `Message` objects.
- Tool traces = role `tool` OR assistant with `metadata` key `tool_calls`.

Pseudo:
```php
final class AppendToolTraceToBuffer implements CanProcessAgentState {
    public function canProcess(AgentState $state): bool { return true; }

    public function process(AgentState $state, ?callable $next = null): AgentState {
        $newState = $next ? $next($state) : $state;
        $currentStep = $newState->currentStep();
        if ($currentStep === null) {
            return $newState;
        }

        $toolTrace = $currentStep
            ->outputMessages()
            ->filter(fn(Message $m) =>
                $m->isTool()
                || ($m->isAssistant() && $m->metadata()->hasKey('tool_calls'))
            );

        if ($toolTrace->isEmpty()) {
            return $newState;
        }

        $store = $newState->store()
            ->section(AgentState::EXECUTION_BUFFER_SECTION)
            ->appendMessages($toolTrace);

        return $newState->withMessageStore($store);
    }
}
```

### Step 5: Append only final assistant response
**File:** `packages/agents/src/Agent/StateProcessing/Processors/AppendStepMessages.php`

Two options:

**Option A (minimal change):** Add a flag and extract final response only.
- If `separateToolTrace` is enabled, append only the last assistant message without `tool_calls` and with non-empty content.
- Use `Messages::reversed()` + `Message` objects.

**Option B (cleaner SRP):** Introduce a new `AppendFinalResponse` processor and keep `AppendStepMessages` unchanged (legacy mode).  
This reduces branching and keeps older behavior stable.

Regardless of option:
- `canProcess()` must return `true` (gate after `$next`).
- Guard with `AgentStep::hasToolCalls()`; if a step has tool calls, do **not** append assistant content from that step.

Pseudo (Option A):
```php
private function extractFinalResponse(Messages $output): ?Message {
    foreach ($output->reversed()->each() as $message) {
        if (!$message->isAssistant()) {
            continue;
        }
        if ($message->metadata()->hasKey('tool_calls')) {
            continue;
        }
        if ($message->content()->isEmpty()) {
            continue;
        }
        return $message;
    }
    return null;
}
```

### Step 6: Clear execution buffer at end of execution
**File:** `packages/agents/src/Agent/StateProcessing/Processors/ClearExecutionBuffer.php`

Key requirements:
- `canProcess()` returns `true`.
- Check `continuationOutcome()` after `$next`.
- Clear only when outcome exists and `shouldContinue() === false`.

Pseudo:
```php
public function process(AgentState $state, ?callable $next = null): AgentState {
    $newState = $next ? $next($state) : $state;
    $outcome = $newState->continuationOutcome();
    if ($outcome === null || $outcome->shouldContinue()) {
        return $newState;
    }
    $store = $newState->store()
        ->section(AgentState::EXECUTION_BUFFER_SECTION)
        ->setMessages(Messages::empty());
    return $newState->withMessageStore($store);
}
```

### Step 7: Wire processors in correct order
**File:** `packages/agents/src/AgentBuilder/AgentBuilder.php`

Add a builder flag, or a small capability wrapper if preferred.

Important ordering: because the chain unwinds, the **first** processor runs last.
```
// Order in list (left → right)
ClearExecutionBuffer
AppendStepMessages (or AppendFinalResponse)
AppendToolTraceToBuffer
AppendContextMetadata
ApplyCachedContext
```

With base processors + optional processors:
```php
if ($this->separateToolTrace) {
    $baseProcessors[] = new ClearExecutionBuffer();
    $baseProcessors[] = new AppendStepMessages(separateToolTrace: true);
    $baseProcessors[] = new AppendToolTraceToBuffer();
} else {
    $baseProcessors[] = new AppendStepMessages();
}
```

This guarantees:
1. Tool traces are appended to `execution_buffer`.
2. Final response is appended to `messages`.
3. Buffer is cleared when execution stops.

### Step 8: Usage example (optional docs)
```php
$agent = AgentBuilder::base()
    ->withSeparatedToolTrace(true)
    ->build();
```

## Backward Compatibility
- Default remains unchanged unless `withSeparatedToolTrace(true)` is called.
- Legacy behavior still appends all output messages to `messages`.

## Tests / Verification Checklist
1. **Single tool call step**: `messages` contains only user + final assistant response; tool traces are in `execution_buffer`.
2. **Multiple tool calls**: all traces buffered, only final response persists.
3. **No tool calls**: response appended normally.
4. **Stop condition**: buffer is cleared at end of execution.
5. **Error path**: after `forContinuation()` or `withUserMessage(..., true)` the buffer is empty.
6. **Subagents**: parent still sees only subagent summary text.

## Notes on Existing Patterns
- Use `Messages::filter()` and `Message::metadata()->hasKey()` instead of array access.
- Avoid deep nesting and `if/else if` chains; prefer early returns and simple checks.
- Keep changes scoped to `packages/agents` only.
