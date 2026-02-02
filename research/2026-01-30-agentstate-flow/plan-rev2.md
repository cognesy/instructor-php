# AgentState Flow Simplification Plan (rev2)

Goal: move to a simple AgentState → AgentState flow with transient step data on AgentState, remove StepRecorder/ErrorRecorder, simplify brittle ExecutionState methods, and eliminate the CanReportObserverState side-channel — while preserving stop reasons and events.

Builds on: cleaner-loop plan (StopSignal/StopReason/AgentStopException already landed; ContinuationEvaluation/ContinuationOutcome/EvaluationProcessor remain as legacy/backward‑compat helpers but are no longer part of the main flow).

## Guardrails (Non-Negotiable)

- AgentState remains the flow object passed across the loop.
- Stop reason must be preserved and emitted in events (monitoring + debugging).
- Each phase is independently deployable and testable — no phase requires a later phase to be stable.
- Avoid new surface area unless it replaces existing complexity.
- Keep behavior compatible with existing tests until a phase explicitly changes semantics.

## Success Criteria

- StepRecorder and ErrorRecorder removed; AgentLoop handles recording and event emission via private methods.
- ExecutionState step-management surface reduced: `withNewStepExecution`, `withStepInProgressCleared`, `$currentStepNumber` removed or collapsed.
- CanUseTools returns AgentState (state flow explicit).
- CanExecuteToolCalls unchanged (returns ToolExecution/ToolExecutions).
- CanReportObserverState removed; drivers own observer-state integration.
- Hooks still run inside HookStackObserver and can mutate state; drivers merge the result.
- Serializers exclude transient fields; continuation round-trips verified.

---

## Phase 0 — Inventory + Design (no code)

### Tasks
1. Confirm touched file list (mechanical — follows from Phase 1 interface changes):
   - Contracts: `CanUseTools`, `CanReportObserverState` (delete)
   - Drivers: `ToolCallingDriver`, `ReActDriver`, `DeterministicAgentDriver`
   - Loop: `AgentLoop`
   - Lifecycle: `StepRecorder` (delete), `ErrorRecorder` (delete)
   - State: `ExecutionState`, `AgentState`
   - Observer: `HookStackObserver` (minor — keep state() accessor for tool hooks)
   - Executor: `ToolExecutor` (remove CanReportObserverState impl)
   - Serializers: `ContinuationAgentStateSerializer`, `SlimAgentStateSerializer`
   - Error: `AgentErrorContextResolver` (no move required unless found outside Core/ErrorHandling/)

2. Audit serialization boundary:
   - Read `ContinuationAgentStateSerializer::serialize/deserialize` and `SlimAgentStateSerializer`.
   - Identify which ExecutionState fields are serialized today (`currentStepNumber`, `currentStepStartedAt`, `currentStep`, `hookContext`).
   - Decide: which become transient (excluded from serialization) vs. which stay.

3. Agree on transient field design (see below) and whether `currentStep` remains serialized.

### Transient Field Design

ExecutionState keeps these transient fields (not serialized, cleared between steps):

| Field | Type | Set by | Cleared by |
|---|---|---|---|
| `currentStep` | `?AgentStep` | Driver (via AgentState return) | AgentLoop after recording StepExecution |
| `currentStepStartedAt` | `?DateTimeImmutable` | AgentLoop at step start | AgentLoop after recording StepExecution |
| `hookContext` | `?HookContext` | HookStackObserver | AgentLoop after recording StepExecution |

Removed from ExecutionState:
- `$currentStepNumber` — derived as `stepExecutions->count() + 1` when needed (only used for StepExecution construction and event payloads).

NOT on ExecutionState:
- `currentToolExecutions` — stays local to driver scope. The driver builds `AgentStep` from tool results and embeds them there. No reason to put intermediate tool results on shared state.

### Deliverable
- This document (approved).
- Serializer audit notes (can be inline comments or a short section appended here).

---

## Phase 1 — Interface Simplification (state in → state out)

### Contract changes

```php
// CHANGED: returns AgentState instead of AgentStep
interface CanUseTools
{
    public function useTools(AgentState $state): AgentState;
}

// UNCHANGED
interface CanExecuteToolCalls
{
    public function executeTool(ToolCall $toolCall, AgentState $state): ToolExecution;
    public function executeTools(ToolCalls $toolCalls, AgentState $state): ToolExecutions;
}

// DELETED
interface CanReportObserverState { ... }
```

Rationale for keeping CanExecuteToolCalls unchanged:
- Tool execution is a lower-level concern. Returning AgentState would couple every ToolExecution to full agent state.
- `executeTools` iterates tool calls — extracting per-call results from AgentState would be awkward.
- HookStackObserver already tracks state mutations from tool-level hooks via `$lastState`. The driver (not the executor) is the right integration point.

### CanUseTools signature change

Remove `Tools` and `CanExecuteToolCalls` from the `useTools()` signature — inject them via driver constructors instead. The driver already knows its tools and executor; passing them per-call was a historical artifact.

```php
// Before
public function useTools(AgentState $state, Tools $tools, CanExecuteToolCalls $executor): AgentStep;

// After
public function useTools(AgentState $state): AgentState;
```

### Driver updates

Each driver:
1. Receives `Tools`, `CanExecuteToolCalls`, and `?HookStackObserver` via constructor.
2. Executes tools via `$this->executor->executeTools(...)` → gets `ToolExecutions`.
3. If tool hooks run via HookStackObserver, driver reads hook‑mutated state via `$this->observer->state()` (driver must receive the same observer instance used by ToolExecutor).
4. Merges: starts from input `$state`, applies observer state if changed, sets `currentStep`.
5. Returns `AgentState`.

**ToolCallingDriver / ReActDriver**: Already build AgentStep internally. After building the step, return `$state->withCurrentStep($step)` (merging observer state first if present).

**DeterministicAgentDriver**: Returns `$state->withCurrentStep($scriptedStep)`. Test fixtures need minor update — they now provide `AgentState` assertions instead of `AgentStep` assertions.

### ToolExecutor update

- Remove `implements CanReportObserverState`.
- Remove `observerState()` method.
- Keep `CanObserveAgentLifecycle $observer` for hook dispatch (onBeforeToolUse, onAfterToolUse).
- State propagation from hooks is now the driver's responsibility (via HookStackObserver->state()).

### AgentLoop update

- `useTools()` method simplifies: call `$this->driver->useTools($state)`, result is `AgentState` directly. No more `applyObserverState()` needed.
- Remove `applyObserverState()` method entirely.

### Deliverable
- State flow is explicit through return types.
- No observer-state side-channel.
- All existing tests updated for new signatures.

---

## Phase 2 — Inline Step/Error Recording in AgentLoop

### Remove
- `StepRecorder` class (delete file)
- `ErrorRecorder` class (delete file)
- `ErrorRecordingResult` data class (delete file — inline the three fields as local variables)

### AgentLoop gains private methods

```php
private function recordStep(AgentState $state): AgentState
{
    // 1. Retrieve transient timing: $state->execution()->currentStepStartedAt()
    // 2. Derive step number: $state->stepCount() + 1
    // 3. Get current step: $state->currentStep()
    // 4. Get pending stop signal: $state->pendingStopSignal()
    // 5. Build StepExecution
    // 6. Emit continuationEvaluated event (same payload as before)
    // 7. Record step execution in state
    // 8. Emit stateUpdated event
    // 9. Clear transient fields (currentStep, currentStepStartedAt, hookContext)
    // 10. Return updated state
}

private function recordError(Throwable $error, AgentState $state): AgentState
{
    // 1. Delegate to $this->errorHandler->handleError(...)
    // 2. Set failure step on state
    // 3. Apply stop signal if present
    // 4. Emit continuationEvaluated event
    // 5. Build and record StepExecution
    // 6. Emit stateUpdated event
    // 7. If failed: emit executionFailed event
    // 8. Clear transient fields
    // 9. Return updated state
}
```

### Event emission stays identical

The `continuationEvaluated` event payload must include:
- `stepNumber` (derived, not stored)
- `stopSignal` (from state)
- `stopReason` (from signal)
- `source` (from signal)

Verify: `AgentEventEmitter::continuationEvaluated()` signature is compatible. If it currently expects a `ContinuationOutcome`, it should already accept `?StopSignal` from the cleaner-loop migration.

### Deliverable
- AgentLoop contains the full recording flow.
- StepRecorder, ErrorRecorder, ErrorRecordingResult deleted.
- Event payloads unchanged (verified by test).

---

## Phase 3 — Simplify ExecutionState

### Remove from ExecutionState

| Field/Method | Replacement |
|---|---|
| `$currentStepNumber` | Derived: `$this->stepExecutions->count() + 1` |
| `withNewStepExecution()` | Replaced by `withStepStarted(DateTimeImmutable $at): self` — sets only `currentStepStartedAt`, clears `pendingStopSignal` and `continuationRequested`, initializes `hookContext` |
| `withStepInProgressCleared()` | Replaced by clearing transient fields in `withStepExecution()` (recording already resets them) |
| `withReplacedStepExecution()` | Keep only if still needed by error path; otherwise remove |

### Simplified step lifecycle

```
Before (6 transitions):
  withNewStepExecution → [driver] → withCurrentStep → [record] → withStepExecution → withStepInProgressCleared

After (3 transitions):
  withStepStarted → [driver returns state with currentStep] → withStepRecorded(StepExecution)
```

`withStepRecorded(StepExecution)` combines: append to stepExecutions + clear transient fields (`currentStepStartedAt`, `hookContext`). `currentStep` is preserved for history access.

### AgentState surface reduction

Remove delegate methods that just forward to ExecutionState for fields that no longer exist:
- `currentStepNumber()` — if still needed externally, derive from `stepCount() + 1` (or remove if only used internally by recording).
- `withNewStepExecution()` → `withStepStarted()`
- `withStepInProgressCleared()` → removed (handled by recording)

### Serialization

Both serializers must exclude:
- `currentStepStartedAt` (transient)
- `hookContext` (transient, already excluded)
- `currentStep` (transient only if we accept the behavior change; otherwise keep serialized for resume)

Add test: serialize → deserialize round-trip with active transient fields → transient fields are null after deserialize (excluding any fields we intentionally keep serialized).

### Deliverable
- ExecutionState has fewer transitions and no stored step number.
- AgentState exposes a smaller API.
- Serialization verified.

---

## Phase 4 — Tests + Cleanup

### Test categories to update

1. **Driver tests** — Return type changes from `AgentStep` to `AgentState`. Every assertion on `useTools()` result needs updating. Files:
   - Tests referencing `ToolCallingDriver::useTools()`
   - Tests referencing `ReActDriver::useTools()`
   - `AgentDeterministicExecutionTest`

2. **AgentLoop tests** — Recording flow changes. Files:
   - `AgentLoopStepNumberTest` (step number is now derived)
   - `AgentExecutionBufferTest`
   - `AgentStateContinuationTest`
   - `AgentContinuationEvaluationFailureTest`

3. **Serialization tests** — Transient field exclusion. Files:
   - `ContinuationAgentStateSerializerTest`
   - `SlimAgentStateSerializerTest`
   - New: round-trip test with transient fields populated

4. **Guard/Hook tests** — Should mostly pass unchanged since hooks interact via AgentState. Files:
   - `GuardHooksTest`
   - `HookTest`
   - `ErrorPolicyHookTest`

5. **Event payload regression test** (new) — Run a full agent loop scenario, capture events via wiretap, assert:
   - `continuationEvaluated` contains `stopSignal` with `reason`, `source`, `message`
   - `executionFinished` contains correct `status` and `stopReason`
   - Step numbering in events is sequential and correct

6. **Tests for `StepRecorder` / `ErrorRecorder`** (if any exist — currently they don't have dedicated tests, their behavior is tested via AgentLoop tests)

### Cleanup
- Delete `CanReportObserverState` interface.
- Delete `StepRecorder`, `ErrorRecorder`, `ErrorRecordingResult` files.
- Remove any unused imports referencing deleted types.
- Verify `AgentErrorContextResolver` doesn’t reference deleted continuation types.

### Deliverable
- Green test suite for packages/agents.
- No references to deleted types remain.

---

## Risks

| Risk | Severity | Mitigation |
|---|---|---|
| AgentLoop grows too large after inlining | Medium | Keep recording as private methods, not inline in iterate() body |
| Transient field serialization leak | Medium | Phase 0 audit + Phase 3 round-trip test |
| DeterministicAgentDriver test fixture churn | Low | Wrapper: `$state->withCurrentStep($step)` |
| Event payload regression | Medium | Dedicated event payload assertion test in Phase 4 |
| Observer state lost between tool calls | Medium | Driver merges observer state after all tool calls complete, before returning AgentState |
| Step number derivation off-by-one | Low | Derived as `stepExecutions->count() + 1`; verified by existing step number tests |

## Explicit Non-Goals

- No change to StopSignal semantics or priority rules.
- No changes to external packages beyond packages/agents.
- No redesign of CanEmitAgentEvents or event envelope format.
- No change to CanExecuteToolCalls interface — tool executor returns ToolExecution/ToolExecutions.
- No change to HookStack or hook dispatch mechanics.
