I reviewed the agent implementation end-to-end; the critical findings below are based on the current design.

**Findings**
- Critical: Tool-calling responses always append `InferenceResponse::content()` as an assistant message even when tool calls exist, so JSON tool args leak into UI/history and can short‑circuit continuation if tool calls weren’t parsed. `packages/addons/src/Agent/Drivers/ToolCalling/ToolCallingDriver.php`, `packages/addons/src/Agent/Core/Continuation/ToolCallPresenceCheck.php`, `packages/addons/src/Agent/Drivers/ToolCalling/ToolExecutionFormatter.php`
- High: Continuation decisions are opaque; `ContinuationCriteria::canContinue` collapses to bool and the enum carries no reason/context, making “why did it stop?” debugging and UI explainability painful. `packages/addons/src/StepByStep/Continuation/ContinuationCriteria.php`, `packages/addons/src/StepByStep/Continuation/ContinuationDecision.php`
- High: Execution time limits use wall‑clock `StateInfo::startedAt`, so persisted/resumed agents time out immediately; there’s no cumulative active‑time tracking. `packages/addons/src/StepByStep/Continuation/Criteria/ExecutionTimeLimit.php`, `packages/addons/src/StepByStep/State/StateInfo.php`, `packages/addons/src/Agent/Core/Data/AgentState.php`
- High: Default error policy forbids continuation on any tool failure, effectively nullifying `maxRetries` and causing “one step then stop” when tools return `Result::failure`. `packages/addons/src/Agent/AgentBuilder.php`, `packages/addons/src/StepByStep/Continuation/Criteria/ErrorPresenceCheck.php`, `packages/addons/src/StepByStep/Continuation/Criteria/RetryLimit.php`, `packages/addons/src/Agent/Core/ToolExecutor.php`
- Medium: Step duration metrics are inaccurate because `currentStepStartedAt` is set after tool execution, but `AgentStepCompleted` uses it for duration. `packages/addons/src/Agent/Agent.php`, `packages/addons/src/Agent/Events/AgentStepCompleted.php`
- Medium: Persisted state is heavy (full messages, steps, raw response data), which is risky for DB‑backed webapps without a “slim” serializer. `packages/addons/src/Agent/Core/Data/AgentState.php`, `packages/addons/src/Agent/Core/Data/AgentStep.php`, `packages/polyglot/src/Inference/Data/InferenceResponse.php`

**Misuses / Missed Capabilities (from Partnerspot feedback)**
- Tool call args are already captured as metadata (`tool_calls`) on assistant messages and tool results carry `tool_call_id`, so UI should render by role+metadata instead of assistant content. `packages/addons/src/Agent/Drivers/ToolCalling/ToolExecutionFormatter.php`
- Observability exists via events and `wiretap`/`onEvent`; you already get step start/complete, state snapshots, tool timing, and token usage—ideal for Reverb/Echo streaming. `packages/addons/src/Agent/Events/AgentStepStarted.php`, `packages/addons/src/Agent/Events/AgentStateUpdated.php`, `packages/addons/src/Agent/Events/ToolCallCompleted.php`, `packages/events/src/Traits/HandlesEvents.php`
- Message role mapping can use the public enum; `Message::role()` returns `MessageRole`, with helpers like `isSystem()`. `packages/messages/src/Message.php`, `packages/messages/src/Enums/MessageRole.php`
- Laravel queue integration is already supported via deterministic agent classes; you don’t need to serialize Agent instances. `packages/addons/src/AgentBuilder/Contracts/AgentInterface.php`, `packages/addons/src/AgentBuilder/Support/AbstractAgent.php`
- Cached context and response format are already supported for stable prompts/provider caching. `packages/addons/src/Agent/AgentBuilder.php`, `packages/addons/src/Agent/Core/StateProcessing/Processors/ApplyCachedContext.php`

**Current Design Snapshot (brief)**
- `Agent` is a `StepByStep` orchestrator with a driver (`ToolCallingDriver`/ReAct), `ToolExecutor`, processors, and continuation criteria. `packages/addons/src/Agent/Agent.php`, `packages/addons/src/StepByStep/StepByStep.php`
- `AgentState` is immutable and serializable; processors append step messages and metadata into the prompt, which affects UI if you render raw history. `packages/addons/src/Agent/Core/Data/AgentState.php`, `packages/addons/src/StepByStep/StateProcessing/Processors/AppendStepMessages.php`, `packages/addons/src/StepByStep/StateProcessing/Processors/AppendContextMetadata.php`
- Default continuation is a guard + work‑driver mix with priority logic and a fixed base set. `packages/addons/src/Agent/AgentBuilder.php`, `packages/addons/src/StepByStep/Continuation/ContinuationDecision.php`
- Tools must return `Result`; BaseTool wraps exceptions and auto‑builds JSON schema from `__invoke`. `packages/addons/src/Agent/Contracts/ToolInterface.php`, `packages/addons/src/Agent/Tools/BaseTool.php`

**Proposed Actions (for InstructorPHP team)**
- Fix tool‑call message handling: only append assistant content when no tool calls (or when content is genuinely natural language); otherwise attach it as metadata or a separate “final” message type. `packages/addons/src/Agent/Drivers/ToolCalling/ToolCallingDriver.php`
- Implement rich continuation results and tracing (criterion, decision, reason, context) and persist/emit them; this directly addresses the “why did it stop?” gap and aligns with the requested redesign. `packages/addons/src/StepByStep/Continuation/ContinuationCriteria.php`
- Make continuation policy explicit and configurable: allow disabling `ErrorPresenceCheck`, clarify retry semantics, or introduce a `ContinuationPolicy`/`Mode` that can be swapped in. `packages/addons/src/Agent/AgentBuilder.php`
- Track active execution time + step timing: record step start before tool execution, accumulate active seconds in state, and use that for `ExecutionTimeLimit`. `packages/addons/src/Agent/Agent.php`, `packages/addons/src/StepByStep/Continuation/Criteria/ExecutionTimeLimit.php`
- Add a built‑in trace/log adapter (PSR‑3 or `ExecutionTrace` collector) to turn existing events into structured debug output for apps. `packages/addons/src/Agent/Events/AgentStepCompleted.php`
- Add a “slim” state serializer or persistence policy to drop raw response payloads and large histories when storing in DB. `packages/addons/src/Agent/Core/Data/AgentState.php`

**Open Questions / Assumptions**
- Which LLM preset/provider did Partnerspot use? Some return tool‑call JSON in `content` when tool‑call parsing fails.
- Were any tool executions returning `Result::failure`? That would trigger `ErrorPresenceCheck` and explain one‑step termination.
- Are they persisting/resuming `AgentState` between requests/queue jobs? That directly affects the execution‑time limit behavior.

If you want, I can take the next step by:
1) drafting a redesign spec for continuation decision tracing + error policy,  
2) reviewing Partnerspot’s UI rendering and tool‑call handling in their Laravel app, or  
3) proposing a slim serialization format plus an event‑to‑Reverb adapter.
