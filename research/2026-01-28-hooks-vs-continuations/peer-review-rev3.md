# Peer Review rev3 — Hook‑Only Flow Control Plan

**Date:** 2026-01-28

This review validates rev3 against the **actual codebase**, the new constraints, and the “single abstraction + simplicity” goal. The direction is right, but there are still **concrete mismatches** and several missing mechanics.

---

## Critical Issues (must fix before implementation)

1) **Plan uses non‑existent methods / signatures in core classes**
   - `ContinuationEvaluation::forbid()` / `allowStop()` / `decision()` / `resolvedBy()` do **not** exist.
   - `ContinuationDecision::shouldContinue()` does **not** exist.
   - `ErrorHandlingDecision::shouldStop()` does **not** exist.
   - `ErrorList::with()` does **not** exist (`withAppended()` is the current API).
   - `AgentErrorContextResolver::resolve($state, $exception)` does **not** exist; it accepts only `AgentState`.
   - **Fix:** update the plan to use existing APIs, or explicitly add new APIs (and list them in the migration changes).

2) **Tool blocking flow relies on missing APIs**
   - `ErrorList::hasType()` does not exist (it stores `Throwable` only).
   - `ToolExecution::blocked()` with `status` field is incompatible with current `ToolExecution` (no status field, uses `Result`).
   - **Fix:** represent blocked as `Result::failure(new ToolCallBlockedException(...))` and detect via `instanceof ToolCallBlockedException`.

3) **ErrorPolicyHook still incorrect**
   - The plan calls `$this->resolver->resolve($state, $exception)` and then `$decision->shouldStop()`.
   - Actual `ErrorPolicy::evaluate(ErrorContext)` returns `ErrorHandlingDecision` and needs explicit mapping.
   - **Fix:** use `AgentErrorContextResolver->resolve($state)` and map:
     - `Stop` → `ContinuationEvaluation::fromDecision(...Forbid...)`
     - `Retry/Ignore` → no evaluation (or `AllowContinuation` evaluation if required).

4) **HookStack event filtering still ambiguous**
   - The plan calls `getHooksForType($type)` without defining it. Existing `HookStack` stores **all hooks**, not per‑type buckets.
   - Without explicit per‑type filtering or passing the **actual** event type into hook/matcher, hooks will run for all events.
   - **Fix:** either:
     - keep a single list and inject `EventTypeMatcher` at registration, and ensure `matches($state, $type)` uses the **actual** event type from `process()`, or
     - rework HookStack to store hooks by event type (and remove matchers or make them supplemental).

---

## Major Gaps / Inconsistencies

1) **Driver boundary is undefined**
   - The plan splits inference and tool execution, but current `ToolCallingDriver` performs both in one method (`useTools`).
   - Without refactoring the driver interface (or adding a new driver), the proposed loop cannot be implemented.
   - **Fix:** define whether to:
     - refactor the driver into `infer()` + `executeTools()`, or
     - add a new driver pipeline and keep old driver for backwards compatibility.

2) **Evaluation accumulation lifetime is unclear**
   - `aggregateEvaluations()` is called after each phase, but evaluations are not cleared afterwards.
   - This causes repeated evaluation of the same entries and multiple `continuationEvaluated` events for unchanged evaluations.
   - **Fix:** explicitly clear evaluations after aggregation (or define that evaluations accumulate across the whole step and only aggregate once at the end).

3) **Serialization risk for transient fields**
   - `CurrentExecution::toArray()` currently serializes its fields. Adding large transient objects (`InferenceResponse`, `ToolCall`, exceptions) will bloat or break serialization.
   - **Fix:** exclude event data and transient fields from serialization, or gate them behind a separate serializer config.

4) **OnError lifecycle still underspecified**
   - Plan adds `HookType::OnError`, but doesn’t define how exceptions are captured, stored on `CurrentExecution`, and then cleared.
   - Error handling currently uses `ErrorRecorder` and `AgentErrorHandler`; this integration isn’t updated.
   - **Fix:** define exact catch flow:
     - capture exception → `currentExecution->withException()` → OnError hooks → evaluation aggregation → error handling/step finalization.

5) **Stop/SubagentStop lifecycle semantics are omitted**
   - The plan keeps `HookType::Stop` / `SubagentStop` but doesn’t define when they run under the new evaluation model.
   - **Fix:** define when a stop hook runs relative to aggregation and whether it appends evaluations or only mutates state.

6) **Tool matchers for PostToolUse are broken unless `currentToolExecution` is set**
   - `ToolNameMatcher` reads `currentToolCall`, but `PostToolUse` hooks may only have `currentToolExecution`.
   - **Fix:** either set `currentToolCall` for both Pre and Post, or adjust matcher to read tool name from `currentToolExecution` when present.

7) **`CurrentExecution` is readonly; clone‑based mutation is invalid**
   - The proposal shows a `clone` approach in one place (rev2). In rev3 it uses `new self` (good), but ensure the implementation **never** mutates readonly props.

---

## Improvement Opportunities

1) **Define a single helper to append evaluations**
   - Add `AgentState::withEvaluation(...)` (already in plan) and ensure **all** flow‑control hooks use it.
   - This avoids direct manipulation of `CurrentExecution` and keeps code flat.

2) **Make evaluation aggregation explicit and deterministic**
   - Consider a single `aggregateEvaluations()` call per step (after `AfterStep`), instead of after every phase.
   - This reduces redundant event emissions and simplifies reasoning.

3) **Clarify the “no evaluation written” default**
   - Explicitly document that no evaluations → `ContinuationOutcome::empty()` → continue (bootstrap).

4) **Align naming with existing APIs**
   - Use `startedAt` consistently (not `stepStartedAt`), and avoid introducing new helper names unless they’re implemented.

---

## Summary

The updated plan fixes the major conceptual problems, but it still contains **multiple concrete API mismatches** and several undefined integration points (driver split, error flow, serialization, evaluation clearing). These are not cosmetic; they will block implementation or introduce subtle regressions. Address the critical issues above, then the plan is implementable with far lower risk.
