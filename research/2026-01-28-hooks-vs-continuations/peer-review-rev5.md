# Peer Review rev5 — Current State (post‑changes)

**Date:** 2026-01-28

This reflects the actual codebase state after the latest changes (evaluation scaffolding, aggregation, StepRecorder updates, observer state propagation, and error resolver update).

---

## Critical Issues (blockers)

1) **Pre‑step evaluations are wiped before tool execution**
   - `AgentLoop::onBeforeStep()` aggregates evaluations, but `useTools()` immediately replaces `currentExecution` with a new `CurrentExecution`, clearing the outcome/evaluations.
   - Result: guard hooks in `BeforeStep` cannot stop execution reliably.
   - **Fix:** preserve `currentExecution` across `useTools()` or explicitly carry over `continuationOutcome`/evaluations.

2) **`shouldContinue()` ignores precomputed outcomes**
   - `AgentLoop::shouldContinue()` still uses `ContinuationCriteria` / `StepExecutions` and never checks `currentExecution->continuationOutcome`.
   - Result: evaluation‑driven flow control is not authoritative (only affects StepRecorder after step).
   - **Fix:** consult precomputed outcomes first, then fall back to criteria when absent.

3) **Stop‑hook integration still uses HookOutcome/StopDecision**
   - `HookStackObserver::onBeforeStopDecision()` still returns `StopDecision` and does not write evaluations.
   - Result: stop hooks remain in the old control path and do not participate in the evaluation pipeline.
   - **Fix:** either keep stop hooks in the old path explicitly, or update them to write evaluations and remove StopDecision from control flow.

---

## Major Gaps / Inconsistencies

1) **Hook lifecycle types are incomplete**
   - `HookType` still lacks `BeforeInference`, `AfterInference`, `OnError`.
   - Result: hook‑only lifecycle coverage is incomplete and inference/error hooks cannot be added yet.

2) **HookContext/HookOutcome still central**
   - Hook signatures and implementations are still context‑based; no migration to `AgentState`‑only hooks has started.
   - Result: the target model is still far from actual hook API.

3) **Step‑level transient data is missing**
   - `CurrentExecution` has event fields (tool/inference) and evaluations, but no step‑level transient fields (inputMessages, toolExecutions, outputMessages, errors).
   - Result: Once HookContext is removed, AfterStep hooks won’t have full access to step data unless step is finalized earlier.

4) **Criteria system still authoritative**
   - `ContinuationCriteria` remains in AgentLoop and builder; StepRecorder only uses precomputed outcome if present.
   - Result: evaluations are a secondary path, not the primary control system yet.

---

## Improvements Already Completed (no longer blockers)

- ErrorContext mismatch fixed: resolver reads `currentExecution->exception`.
- Duplicate continuation event emission controlled via `emitContinuationEvent` flags.
- Tool hook state propagation now supported (`HookStackObserver::state()` + `ToolExecutor::observerState()` + AgentLoop usage).
- Evaluation scaffolding added (CurrentExecution evaluations + aggregation + StepRecorder precomputed outcome).

---

## Summary

The scaffolding is in place, but **evaluation‑driven control is still non‑authoritative** due to `shouldContinue()` and `useTools()` clearing state. The next changes must make precomputed outcomes durable across the step boundary and authoritative in `shouldContinue()`. Only after that does it make sense to remove criteria and migrate hook signatures.
