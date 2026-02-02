# AgentState Flow Simplification Plan (rev1)

Goal: move to a simple AgentState ➜ AgentState flow with transient step data stored on AgentState, remove StepRecorder/ErrorRecorder, and simplify brittle ExecutionState methods while preserving stop reasons and events.

## Success Criteria
- StepRecorder and ErrorRecorder removed; AgentLoop handles recording and event emission directly.
- ExecutionState no longer needs: withContinuationRequested, withHookContextCleared, withReplacedStepExecution, withNewStepExecution, withStepInProgressCleared (removed or collapsed to one `with(...)` path).
- CanUseTools / CanExecuteToolCalls return AgentState (state flow explicit, no CanReportObserverState).
- Hooks still run inside collaborators (Option 1) and can mutate state, but state is returned explicitly.

## Phase 0 — Inventory + Design Decisions (no code)
- List all implementations of CanUseTools / CanExecuteToolCalls (ToolCallingDriver, ReActDriver, DeterministicAgentDriver, ToolExecutor).
- Identify all points where StepRecorder/ErrorRecorder are called and what events they emit.
- Decide where transient step data lives in AgentState:
  - Proposed: add transient fields in ExecutionState: `currentStepExecution` (StepExecution) and `currentToolExecutions` (ToolExecutions) or equivalent.
  - Ensure transient fields are not serialized.

Deliverable: exact list of touched files + agreed transient field shape.

## Phase 1 — Interface Simplification (state in → state out)
- Update contracts:
  - CanUseTools::useTools(AgentState): AgentState
  - CanExecuteToolCalls::executeTool/executeTools return AgentState
  - Inject Tools + executor into CanUseTools constructors (as per new shape).
- Update implementations:
  - ToolExecutor returns updated state and stores tool executions into transient state.
  - ToolCallingDriver/ReActDriver/DeterministicAgentDriver return state with current step set.
  - Remove CanReportObserverState usage from drivers/executor.

Deliverable: state flow is explicit, no observerState side-channel.

## Phase 2 — Inline Step/Error Recording in AgentLoop
- Remove StepRecorder and ErrorRecorder classes.
- AgentLoop handles:
  - step timing (startedAt/completedAt)
  - building StepExecution and appending to ExecutionState
  - emitting continuation/state updated/step completed events
  - error handling by calling error handler directly and recording failure step execution
- Keep stop reasons and event payloads identical.

Deliverable: AgentLoop contains the full recording flow and events.

## Phase 3 — Simplify ExecutionState / AgentState
- Replace brittle step-management methods with a minimal `with(...)` and basic helpers.
- Remove `currentStepNumber`, `currentStepStartedAt`, `withNewStepExecution`, `withStepInProgressCleared`.
- Keep minimal transient fields:
  - `currentStep` (AgentStep)
  - `currentStepExecution` (StepExecution)
  - `currentToolExecutions` (ToolExecutions) if needed by drivers
  - `hookContext` (optional)
- Ensure serialization excludes transient fields.

Deliverable: ExecutionState has fewer moving parts; AgentState exposes a smaller API.

## Phase 4 — Tests + Cleanups
- Update unit tests for drivers, AgentLoop, hook observer, error handling.
- Remove or update any tests referencing old step number/timing fields or StepRecorder/ErrorRecorder.
- Verify stop reasons still propagate to events and status.

Deliverable: green tests for modified suite (or targeted subset if full suite is too heavy).

## Risks / Questions
- Where to store transient tool execution results in state (new field vs HookContext)?
- ReAct/ToolCalling drivers currently build Step from tool executions; state must expose those transient results cleanly.
- Event emission ordering must remain stable.

## Explicit Non-Goals (for this phase)
- No change to StopSignal semantics.
- No changes to external packages beyond packages/agents.
- No redesign of CanEmitAgentEvents for now.
