# Peer Review rev2 — Hook‑Only Flow Control Plan

**Date:** 2026-01-28

This review focuses on correctness, cohesion, and risk relative to the stated goal: one flow‑control abstraction, hooks return `AgentState`, no `AgentState` embedded elsewhere, minimal noise/complexity.

---

## Critical Issues (must fix before implementation)

1) **`ContinuationOutcome` depends on `EvaluationProcessor` (which the plan deletes).**
   - Today `ContinuationOutcome` uses `EvaluationProcessor` for `shouldContinue()`, `decision()`, `stopReason()`, etc.
   - Plan deletes `EvaluationProcessor` but does not replace those methods.
   - Result: `ContinuationOutcome` becomes unusable or wrong.
   - **Fix:** either keep `EvaluationProcessor` or move its logic into `ContinuationOutcome` (and update all call sites).

2) **No explicit mechanism to accumulate `ContinuationEvaluation` from hooks.**
   - Requirement: hooks should return `AgentState` with `CurrentExecution` containing `ContinuationOutcome` **with evaluations**.
   - Plan writes a `ContinuationOutcome` directly and stops the chain, losing evaluations from other hooks.
   - **Fix:** define a single accumulation strategy:
     - hooks append `ContinuationEvaluation` to `CurrentExecution` (not full `ContinuationOutcome`), and
     - a single aggregator builds `ContinuationOutcome` *once* per phase (order‑independent).

3) **Hook matchers are incompatible with the proposed signature.**
   - Current `HookMatcher::matches(HookContext $context)` and all matchers assume a context object.
   - Plan removes HookContext but still uses matchers (CallableHook uses `$matcher->matches($state)`).
   - **Fix:** update matcher contract to accept `(AgentState $state, HookType $type)` and update all matchers accordingly.

4) **Event data access is undefined without HookContext.**
   - Plan removes HookContext but does not specify how `PreToolUse` hooks access `ToolCall`, or `AfterInference` hooks access `InferenceResponse`.
   - CurrentExecution schema includes `toolExecutions` (post), but **no `currentToolCall`** (pre) or `inferenceMessages` (pre).
   - **Fix:** add explicit transient fields to `CurrentExecution`:
     - `currentToolCall`, `currentToolExecution`, `inferenceMessages`, `inferenceResponse`, `exception` (for onError).
     - define when they are set/cleared.

5) **ErrorPolicy hook API is incorrect.**
   - Plan uses `$policy->evaluate($errors)` and `$decision->isFatal()`. These APIs do not exist.
   - Actual `ErrorPolicy::evaluate` expects `ErrorContext` and returns `ErrorHandlingDecision`.
   - **Fix:** use `AgentErrorContextResolver` to build `ErrorContext`, then map `ErrorHandlingDecision` to a `ContinuationEvaluation`/Outcome.

6) **`ErrorType` changes are inconsistent with current code.**
   - Plan’s enum removes `RateLimit`, `Timeout`, `Unknown` and adds `System` (not in code).
   - This breaks existing error handling and inference retry logic.
   - **Fix:** add `ToolBlocked` without removing existing cases.

7) **`CanObserveAgentLifecycle` signature changes conflict with “hooks return AgentState”.**
   - Plan makes `onExecutionEnd` return void, which prevents state mutation at execution end.
   - Yet the plan elsewhere says “hooks return AgentState.”
   - **Fix:** make all lifecycle methods return `AgentState` consistently.

---

## Major Gaps / Inconsistencies

1) **HookType set is incomplete.**
   - Plan references `AfterInference` and `OnError`, but HookType currently has neither.
   - **Fix:** extend HookType enum and update any helpers (`isToolEvent`, etc).

2) **`HookStack` redesign conflicts with the existing matcher‑based design.**
   - Plan replaces stack with `add(type, hook)`, which duplicates event filtering logic and discards existing matchers.
   - It also diverges from “minimal changes” and adds new structure.
   - **Fix:** keep the existing HookStack and use `EventTypeMatcher + CompositeMatcher` to filter by event, or fully rework matchers to fit the new stack.

3) **`CurrentExecution` field names don’t match actual class.**
   - Plan uses `stepStartedAt` but current class uses `startedAt`.
   - Requires clarity to avoid mismatch and silent bugs.

4) **Stop/continue resolution is order‑dependent in the plan.**
   - “First forbid wins” implies the chain order matters.
   - Old criteria were **order‑independent** via evaluations + processor.
   - **Fix:** preserve order‑independent resolution by accumulating evaluations and computing outcome once.

5) **No lifecycle for clearing transient fields.**
   - Plan mentions `clearedTransient()` but doesn’t define when it is called, or how it interacts with errors/stops.
   - **Fix:** define exact points for clearing:
     - after `AgentStep` finalization,
     - after error handling, and
     - when a step is aborted before completion.

6) **Tool blocking flow is underspecified.**
   - Plan says “set error in CurrentExecution” but does not define a `withError(...)` API on AgentState.
   - It also doesn’t specify how ToolExecutor uses that to skip or retry tool calls.
   - **Fix:** define a precise path:
     - BeforeToolUse hook sees `currentToolCall`, decides block
     - writes a `ToolCallBlockedException` into `CurrentExecution->errors`
     - ToolExecutor returns a failed ToolExecution or throws based on policy.

7) **Event emission is not addressed.**
   - Current `StepRecorder` emits `continuationEvaluated` and `stateUpdated` events.
   - Removing criteria removes these events unless replaced.
   - **Fix:** define a single place where `ContinuationOutcome` is computed and `continuationEvaluated` is emitted.

---

## Improvement Opportunities

1) **Define a minimal “evaluation append” API**
   Add to `CurrentExecution`:
   - `withEvaluation(ContinuationEvaluation $e)`
   - `evaluations(): list<ContinuationEvaluation>`
   - `withContinuationOutcome(ContinuationOutcome $o)` only in the aggregator

2) **Ensure hook short‑circuit semantics are explicit**
   - If a hook decides to stop and returns without `$next`, should subsequent hooks run? Document it. If “no,” then evaluations from later hooks won’t exist.

3) **Avoid API drift in ErrorPolicy**
   - Keep `ErrorPolicy` class as‑is; add `onToolBlocked` decision only if needed, but don’t convert to interface unless required.

4) **Preserve observability**
   - Keep `resolvedBy` in `ContinuationOutcome` (via evaluations). This is valuable for stop analysis and aligns with the single‑object goal.

---

## Summary

The direction is correct (hook‑only, no state embedding, `CurrentExecution` as transient carrier), but the current plan has several **hard incompatibilities** with the existing codebase and the new constraints:
- `ContinuationOutcome` cannot survive the deletion of `EvaluationProcessor` without a replacement.
- Matchers and HookType need redesign for the no‑context signature.
- Tool/inference/error event data is not yet available to hooks.
- ErrorPolicy integration is currently wrong.

Fix those and the plan becomes implementable with far less risk.
