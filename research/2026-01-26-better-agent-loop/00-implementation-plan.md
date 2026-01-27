# Plan: Separate Execution Trace from Conversation Messages

**Date:** 2026-01-26
**Status:** Planning Phase

## Summary

Refactor the agent architecture to properly separate intermediate execution data (tool calls, results) from the main conversation thread, using the existing MessageStore sections mechanism.

## Problem

Currently `AppendStepMessages` appends ALL `outputMessages` (tool calls + results + response) to `state.messages()` after each step, polluting the conversation with internal execution details.

## Solution: Ephemeral Execution Buffer

Use a new MessageStore section (`execution_buffer`) to hold tool traces during execution, keeping the main `messages` section clean.

```
messagesForInference() = messages + execution_buffer  (during execution)
messages = user queries + final responses only        (persistent)
execution_buffer = tool traces                        (cleared after execution)
```

## Implementation Steps

### Step 1: Add Execution Buffer Constant
**File:** `packages/agents/src/Agent/Data/AgentState.php`

Add constant:
```php
public const EXECUTION_BUFFER_SECTION = 'execution_buffer';
```

### Step 2: Create AppendToolTraceToBuffer Processor
**File:** `packages/agents/src/Agent/StateProcessing/Processors/AppendToolTraceToBuffer.php` (NEW)

Extracts tool_call and tool_result messages from step output, appends to execution_buffer section.

### Step 3: Create ClearExecutionBuffer Processor
**File:** `packages/agents/src/Agent/StateProcessing/Processors/ClearExecutionBuffer.php` (NEW)

Clears execution_buffer when execution ends (continuation says stop).

### Step 4: Modify AppendStepMessages
**File:** `packages/agents/src/Agent/StateProcessing/Processors/AppendStepMessages.php`

Change to only append final assistant response (not tool traces) to messages section.

### Step 5: Update messagesForInference()
**File:** `packages/agents/src/Agent/Data/AgentState.php`

Include execution_buffer in compilation for LLM context.

### Step 6: Add Builder Flag
**File:** `packages/agents/src/AgentBuilder/AgentBuilder.php`

Add `withSeparatedToolTrace(bool)` for opt-in behavior.

## Files to Modify

| File | Change |
|------|--------|
| `Agent/Data/AgentState.php` | Add constant, update messagesForInference() |
| `Agent/StateProcessing/Processors/AppendStepMessages.php` | Filter to final response only |
| `AgentBuilder/AgentBuilder.php` | Add builder flag, wire processors |

## New Files

| File | Purpose |
|------|---------|
| `Processors/AppendToolTraceToBuffer.php` | Tool traces → execution_buffer |
| `Processors/ClearExecutionBuffer.php` | Clear buffer on execution end |

## Verification

1. **During execution**: LLM sees conversation + tool traces (via messagesForInference)
2. **After execution**: messages contains only user queries + final responses
3. **Subagents**: Continue to work (already isolated)
4. **Backward compatible**: Default behavior unchanged, opt-in via builder flag

## Test Cases

1. Single tool call → messages has user + response only, no tool trace
2. Multiple tool calls → all traces in buffer, only final response in messages
3. Execution interrupted → buffer cleared, partial state preserved
4. Subagent call → parent sees only subagent response text
5. Resume execution → buffer starts fresh, messages preserved

## Key Design Decisions

### Why MessageStore Sections (not AgentStep changes)

1. **Already exists** - MessageStore supports multiple sections out of the box
2. **No driver changes** - Drivers continue producing same outputMessages format
3. **Processor responsibility** - Separation happens at processor level (SRP)
4. **Backward compatible** - Can be opt-in without breaking existing code

### Why Not Modify AgentStep

1. AgentStep is a value object - changing it affects serialization
2. Drivers would need updating to populate new fields
3. Higher risk of breaking changes

### Fractal Model Support

The execution buffer approach naturally supports the fractal model:
- Each agent (parent or subagent) has its own execution buffer
- Buffer is scoped to single execution, cleared when done
- Subagent already returns only text to parent (SpawnSubagentTool)
- Metrics can be aggregated via stepExecutions (already tracked)
