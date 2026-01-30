# Peer Review rev4 — Hook‑Only Flow Control Plan

**Date:** 2026-01-28

Rev4 is much closer and largely API‑aligned. There are still several **blocking gaps** and a few correctness issues that must be resolved before implementation.

---

## Critical Issues (blockers)

1) **`ContinuationOutcome` still depends on `EvaluationProcessor` via existing methods**
   - You added `fromEvaluations()`, but did not address `decision()`, `resolvedBy()`, `stopReason()`, which currently call `EvaluationProcessor`.
   - Since the plan deletes `EvaluationProcessor`, those methods will break.
   - **Fix:** update those methods to use stored evaluations (or keep `EvaluationProcessor`).

2) **Error policy hooks cannot work with current error resolution flow**
   - `AgentErrorContextResolver::resolve($state)` reads errors from `currentStep()` and tool executions, not from `CurrentExecution->exception`.
   - In your new error flow, `currentStep` may be null when OnError hooks run → `consecutiveFailures === 0` → policy never stops.
   - **Fix:** define how errors get recorded into `currentStep` (or update resolver to read `currentExecution->exception`).

3) **Driver/loop mismatch is still unresolved**
   - The loop logic assumes you can perform inference separately and execute tools manually (calls `toolExecutor->execute(...)`), but the current driver does both in `ToolCallingDriver::useTools()` and `ToolExecutor` has no `execute()` method.
   - **Fix:** either refactor drivers now (explicitly in plan), or define a wrapper that still uses `useTools()` and adapts hooks around it.

4) **Stop hooks cannot “prevent stop” in all cases (logic mismatch)**
   - You state stop hooks can prevent stop by appending `RequestContinuation`.
   - With `ContinuationDecision::canContinueWith()`, **any ForbidContinuation still wins** over RequestContinuation.
   - This means stop hooks only override `AllowStop`, not guard forbids.
   - **Fix:** update the plan language or adjust precedence if override is intended (but that would change semantics).

---

## Major Gaps / Inconsistencies

1) **`AgentState::continuationOutcome()` not updated**
   - The plan adds `CurrentExecution->continuationOutcome`, but does not update `AgentState::continuationOutcome()` to read it first.
   - Without this, `shouldContinue()` will miss outcomes written by hooks.
   - **Fix:** update `AgentState::continuationOutcome()` to check currentExecution outcome before last step execution.

2) **`StepRecorder` and error recorder remain unaddressed**
   - The loop currently relies on `StepRecorder` to create `StepExecution` with outcome.
   - With criteria removed, you must replace or update this to use `currentExecution->continuationOutcome`.
   - **Fix:** define new step finalization path and update `StepRecorder`/`ErrorRecorder` accordingly.

3) **No staging for step‑level data unless step is finalized before AfterStep**
   - You removed `inputMessages`, `toolExecutions`, `outputMessages`, `errors` from `CurrentExecution`.
   - If `AfterStep` runs **before** step finalization, hooks have no access to tool results/messages.
   - **Fix:** either finalize step before `AfterStep` hooks or re‑introduce step‑level transient fields.

4) **HookStack uses `CompositeMatcher` incorrectly**
   - Plan uses `new CompositeMatcher([...])`, but the current class only exposes `CompositeMatcher::and()` / `::or()` with a private constructor.
   - **Fix:** use `CompositeMatcher::and($eventMatcher, $additionalMatcher)` or change the class (document it).

5) **Event ordering stability lost**
   - Current `HookStack` preserves registration order for equal priority; the new plan uses `usort` only (unstable for ties).
   - **Fix:** retain registration order as tie‑breaker to avoid nondeterministic hook chains.

6) **Tool blocking does not feed ErrorPolicy by default**
   - Tool blocking stores `ToolCallBlockedException` in `currentExecution->exception`, but the ErrorPolicy hook uses `currentStep` errors (per resolver).
   - Blocked tools will not influence error policy unless you explicitly record them into the step or extend resolver.

---

## Improvement Opportunities

1) **Clear `continuationOutcome` between phases/steps**
   - You clear evaluations after aggregation, but never clear the outcome itself. A stop outcome can linger and incorrectly stop later phases.
   - **Fix:** reset `continuationOutcome` at the start of each phase/step (or after it is consumed).

2) **Prefer named args when creating new `CurrentExecution`**
   - The constructor has many optional params. Using positional args is error‑prone.
   - **Fix:** use named arguments in all `withX()` implementations to avoid order mistakes.

3) **Matcher API should include event type in `CallableHook`**
   - `CallableHook` no longer receives `$type`, and matchers only see `$state` + `$type` from HookStack.
   - This is fine, but ensure event type is always passed to matcher (no hidden defaults).

---

## Summary

Rev4 resolves most API mismatches, but the plan still has **four hard blockers** (EvaluationProcessor removal, error policy integration, driver/loop mismatch, stop‑hook semantics) and several gaps in step finalization and hook ordering. Fix these and the plan will be implementable without regressions.
