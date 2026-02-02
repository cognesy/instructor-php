# Core Continuation/Stop Flow (Inventory)

Scope: Core continuation/stop evaluation and event emission. This is the baseline for simplification without losing stop reason visibility.

## Data Flow Overview (Current)

1) **Hook evaluations**
- Guard hooks and capability hooks write evaluations using `AgentState::withEvaluation()`.
- Storage location: `AgentState -> ExecutionState::$pendingEvaluations`.
- Files:
  - `packages/agents/src/AgentHooks/Guards/StepsLimitHook.php`
  - `packages/agents/src/AgentHooks/Guards/TokenUsageLimitHook.php`
  - `packages/agents/src/AgentHooks/Guards/ExecutionTimeLimitHook.php`
  - `packages/agents/src/AgentHooks/Hooks/FinishReasonHook.php`
  - `packages/agents/src/AgentHooks/Hooks/ToolCallPresenceHook.php`
  - `packages/agents/src/AgentHooks/Hooks/ErrorPolicyHook.php`
  - `packages/agents/src/AgentBuilder/Capabilities/SelfCritique/SelfCriticHook.php`

2) **Aggregation to outcome**
- `AgentLoop::aggregateAndClearEvaluations()` calls `ContinuationOutcome::fromEvaluations()` and sets `ExecutionState::$pendingOutcome`.
- `ContinuationOutcome` uses `EvaluationProcessor` to derive `shouldContinue`, `resolvedBy`, `stopReason`.
- Files:
  - `packages/agents/src/Core/AgentLoop.php`
  - `packages/agents/src/Core/Continuation/Data/ContinuationOutcome.php`
  - `packages/agents/src/Core/Continuation/EvaluationProcessor.php`

3) **Step recording**
- `AgentLoop::recordStep()` uses the pending outcome (or empty) and calls `StepRecorder::record()`.
- `StepExecution` stores the outcome with the step.
- Files:
  - `packages/agents/src/Core/Lifecycle/StepRecorder.php`
  - `packages/agents/src/Core/Data/StepExecution.php`

4) **Continuation decision**
- `AgentLoop::shouldContinue()` uses `AgentState::continuationOutcome()` (pending or last recorded).
- `AgentState::continuationOutcome()` delegates to `ExecutionState::continuationOutcome()`.
- Files:
  - `packages/agents/src/Core/AgentLoop.php`
  - `packages/agents/src/Core/Data/AgentState.php`
  - `packages/agents/src/Core/Data/ExecutionState.php`

5) **Error path**
- `ErrorRecorder::record()` uses `AgentErrorHandler` and sets a failure step + outcome.
- Emits `continuationEvaluated` and `stateUpdated`, records `StepExecution`.
- `AgentLoop::finalizeErrorOutcome()` can merge evaluations with last outcome and re-emit.
- Files:
  - `packages/agents/src/Core/Lifecycle/ErrorRecorder.php`
  - `packages/agents/src/Core/ErrorHandling/AgentErrorHandler.php`
  - `packages/agents/src/Core/AgentLoop.php`

## Stop Reasons (Current)
`StopReason` enum: `completed`, `steps_limit`, `token_limit`, `time_limit`, `retry_limit`, `error`, `finish_reason`, `guard`, `user_requested`.
- Defined in `packages/agents/src/Core/Continuation/Enums/StopReason.php`.
- Common emitters:
  - Steps limit: `StepsLimitHook` → `StopReason::StepsLimitReached`
  - Token limit: `TokenUsageLimitHook` → `StopReason::TokenLimitReached`
  - Time limit: `ExecutionTimeLimitHook` → `StopReason::TimeLimitReached`
  - Finish reason: `FinishReasonHook` → `StopReason::FinishReasonReceived`
  - Error policy: `ErrorPolicyHook` → `StopReason::ErrorForbade`
  - Error handler: `AgentErrorHandler` → `StopReason::ErrorForbade`

## Event Emission (Current)

### ContinuationEvaluated
- Emitted by `StepRecorder::record()` and `ErrorRecorder::record()`.
- Uses `AgentEventEmitter::continuationEvaluated()`.
- Payload includes:
  - `stepNumber`
  - `outcome` (ContinuationOutcome)
  - `outcome.stopReason()`
  - `outcome.resolvedBy()`
  - `outcome.evaluations[]` (criterion, decision, reason, stopReason)
- Files:
  - `packages/agents/src/Core/Events/AgentEventEmitter.php`
  - `packages/agents/src/Broadcasting/AgentEventEnvelopeAdapter.php`
  - `packages/agents/src/Broadcasting/AgentConsoleLogger.php`

### Status/Completion
- `AgentEventEmitter::executionFinished()` uses `AgentState::status()` and `AgentState::stopReason()`.
- `AgentLoop::onAfterExecution()` sets status based on `StopReason::ErrorForbade`.
- Files:
  - `packages/agents/src/Core/AgentLoop.php`
  - `packages/agents/src/Core/Data/AgentState.php`

## Core Invariants to Preserve in Simplification
- Stop reason must be preserved and emitted in `ContinuationEvaluated` and status-related events.
- The final StepExecution must retain the stop reason used for completion/failure.
- Monitoring payloads need:
  - stop reason
  - source/resolvedBy (criterion)
  - optional context for debugging

## Hotspots for Simplification
- `ExecutionState::$pendingEvaluations` + `ContinuationEvaluation` list
- `EvaluationProcessor` aggregation (decision/resolver/stop reason)
- `ContinuationOutcome` as a heavy container when only stop reason + source is required
- Duplicate aggregation paths (normal flow vs error path)

