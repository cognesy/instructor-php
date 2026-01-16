# Reverb Event Envelope Alignment (Partnerspot)

## Goal
Align the proposed Reverb event envelope with the current Partnerspot broadcast events so the UI can consume a stable schema without losing fidelity.

## Current Broadcast Events (Partnerspot)

### 1) `session.status`
Emitter: `AssistantSessionStatusChanged`
Payload:
- `session_id`
- `execution_id`
- `status`
- `assistant_name`
- `step_count`
- `error_message`
- `output` (includes `last_response`)

Reference:
- `packages/platform-feat-assistant/src/Platform/Features/Assistant/Events/AssistantSessionStatusChanged.php`

### 2) `step.completed`
Emitter: `AssistantStepCompleted`
Payload:
- `session_id`
- `step_number`
- `content`
- `status_type`
- `tool_info`
- `tokens`

Reference:
- `packages/platform-feat-assistant/src/Platform/Features/Assistant/Events/AssistantStepCompleted.php`

### 3) `stream.chunk`
Emitter: `AssistantStreamChunk`
Payload:
- `session_id`
- `chunk`
- `is_complete`
- `tokens_delta`

Reference:
- `packages/platform-feat-assistant/src/Platform/Features/Assistant/Events/AssistantStreamChunk.php`

## Proposed Envelope (Target)
```
{
  "type": "agent.step.started|agent.step.completed|agent.tool.started|agent.tool.completed|agent.stream.chunk|agent.status",
  "session_id": "...",
  "execution_id": "...",
  "timestamp": "...",
  "payload": { ... }
}
```

## Alignment Strategy

### A) Map current events into envelope (no new UI behavior)
Wrap the existing payloads into the envelope so the frontend can depend on a single schema while maintaining existing fields.

Suggested mapping:
- `session.status` -> `agent.status`
  - `payload.status` = `status`
  - `payload.assistant_name` = `assistant_name`
  - `payload.step_count` = `step_count`
  - `payload.error_message` = `error_message`
  - `payload.output` = `output`
- `step.completed` -> `agent.step.completed`
  - `payload.step_number` = `step_number`
  - `payload.status_type` = `status_type`
  - `payload.content` = `content`
  - `payload.tool_info` = `tool_info`
  - `payload.tokens` = `tokens`
- `stream.chunk` -> `agent.stream.chunk`
  - `payload.chunk` = `chunk`
  - `payload.is_complete` = `is_complete`
  - `payload.tokens_delta` = `tokens_delta`

### B) Fix tool event data at the source (high impact)
Current `AssistantEventBroadcaster` reads the wrong keys from Cognesy events, resulting in missing tool names and results.

Fixes:
- `ToolCallStarted` uses data keys `tool` and `args`, not `name` or `arguments`.
- `ToolCallCompleted` has `tool`, `success`, `error`, but not tool output.

Reference:
- `packages/platform-feat-assistant/src/Platform/Features/Assistant/Services/AssistantEventBroadcaster.php`
- `packages/addons/src/Agent/Events/ToolCallStarted.php`
- `packages/addons/src/Agent/Events/ToolCallCompleted.php`

### C) Optional: Add explicit tool events to envelope
If the UI should show tool calls distinctly, emit:
- `agent.tool.started` (tool name + args summary)
- `agent.tool.completed` (tool name + success/error + duration)

This is a clean fit for the proposed envelope and avoids overloading `step.completed`.

## Minimal Backward-Compatible Changes
1) Wrap existing events into the envelope format server-side.
2) Keep existing event names to avoid breaking subscribers.
3) Add `timestamp` and `execution_id` fields where missing.

## Migration Notes
- Frontend can progressively switch to envelope handling without losing existing behavior.
- The old fields can be kept in `payload` for an overlap period.
- The broadcaster key fixes can be shipped independently and will immediately improve tool call visibility.
