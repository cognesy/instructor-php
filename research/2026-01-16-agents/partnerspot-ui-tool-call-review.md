# Partnerspot UI + Tool Call Handling Review

Scope: review of Partnerspot Laravel + Inertia + Reverb/Echo integration for the agent sidebar and execution events, focused on tool call handling and message rendering.

## Key Findings
- The UI only supports `role: 'user' | 'assistant'`, so tool messages and tool metadata are not surfaced. `packages/platform-feat-assistant/resources/js/types/assistant.ts`, `packages/platform-feat-assistant/resources/js/components/agent-chat-sidebar.tsx`
- The UI response uses `output.last_response` from the backend status event; this is derived from the last output message of the step, which can be tool-arg JSON because the agent always appends assistant `content` even when tool calls exist. `packages/platform-feat-assistant/resources/js/hooks/use-agent-execution.ts`, `packages/platform-ixn-agents/src/Platform/Integration/Agents/Services/AgentExecutionService.php`, `packages/addons/src/Agent/Drivers/ToolCalling/ToolCallingDriver.php`
- Tool call events are mapped using incorrect event keys, leading to `unknown` tool names and missing results. `packages/platform-feat-assistant/src/Platform/Features/Assistant/Services/AssistantEventBroadcaster.php`, `packages/addons/src/Agent/Events/ToolCallStarted.php`, `packages/addons/src/Agent/Events/ToolCallCompleted.php`
- Step start/step completion events attempt to read `description`/`result` fields that are not present in the emitted event payloads, so `content` is usually null. `packages/platform-feat-assistant/src/Platform/Features/Assistant/Services/AssistantEventBroadcaster.php`, `packages/addons/src/Agent/Events/AgentStepStarted.php`, `packages/addons/src/Agent/Events/AgentStepCompleted.php`
- Partnerspot already implemented a heuristic to skip assistant messages that look like tool args, but it is applied only when persisting messages, not when broadcasting step updates or rendering the live response. `packages/platform-ixn-agents/src/Platform/Integration/Agents/Services/AgentExecutionService.php`

## How Current Rendering Misuses or Ignores Capabilities
- Cognesy tool call metadata is already available in message metadata (`tool_calls`, `tool_call_id`), but the UI renders only the assistant content string and drops metadata entirely.
- Cognesy tool events provide timing and success/failure information, but the broadcaster does not use the `tool` or `success/error` fields, so the event log has limited fidelity.
- The stored message history includes tool messages, but the frontend never requests or renders them (no role support beyond user/assistant).

## Recommended Fixes (No Code Yet)
- Update event mapping to use actual event keys:
  - `ToolCallStarted` provides `tool` and `args`.
  - `ToolCallCompleted` provides `tool`, `success`, `error`, and timing, but not tool output.
- If tool outputs are needed in the UI, broadcast them from the step output messages, not from tool events, or add a dedicated tool-result event from the executor/formatter.
- When building `last_response`, prefer the last assistant message without `tool_calls` metadata and avoid JSON-only content (reuse the existing `looksLikeToolCallArgs()` filter).
- Expand the frontend `Message` type and renderer to support `role: 'tool'` and show tool results explicitly (or group them into a collapsible tool log section).
- Add a lightweight message history fetch in the sidebar after completion so the UI reflects the canonical stored conversation, not just streaming state.

## Supporting References
- UI components: `packages/platform-feat-assistant/resources/js/components/agent-chat-sidebar.tsx`, `packages/platform-feat-assistant/resources/js/components/agent-event-log.tsx`
- Client event hook: `packages/platform-feat-assistant/resources/js/hooks/use-agent-execution.ts`
- Backend event bridge: `packages/platform-feat-assistant/src/Platform/Features/Assistant/Services/AssistantEventBroadcaster.php`
- Execution service: `packages/platform-ixn-agents/src/Platform/Integration/Agents/Services/AgentExecutionService.php`
- Cognesy tool events: `packages/addons/src/Agent/Events/ToolCallStarted.php`, `packages/addons/src/Agent/Events/ToolCallCompleted.php`
