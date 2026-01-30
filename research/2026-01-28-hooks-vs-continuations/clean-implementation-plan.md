# Clean Implementation Plan: Hook‑Only Flow Control

**Date:** 2026-01-28

## Goals
- Single flow‑control abstraction: `ContinuationOutcome` + `ContinuationDecision` (enum).
- Hooks return **only** `AgentState`.
- No `AgentState` embedded in any returned object or context.
- `CurrentExecution` is the **only** transient carrier for in‑flight step data.
- No parallel control systems; hooks are the authoritative flow control.
- Keep code flat and deterministic; avoid nested control flow and adapter classes.

---

## Core Decisions

### 1) Remove `HookContext`
Hooks already receive `AgentState`. Event‑specific payloads live in `CurrentExecution`.

**New hook signature**
```
handle(AgentState $state, callable $next): AgentState
```

**Hook type selection** stays via `HookType` + matchers (no `HookContext`).

### 2) Single loop control object
- Keep `ContinuationDecision` enum.
- Keep `ContinuationOutcome` as the **only** flow control object.
- Hooks that decide flow **write** a `ContinuationOutcome` into `CurrentExecution`.

### 3) Transient data lives in `CurrentExecution`
`CurrentExecution` becomes the in‑flight step builder container:
- `inputMessages` (snapshot used for inference)
- `inferenceResponse`
- `toolExecutions`
- `outputMessages`
- `errors`
- `continuationOutcome`
- `timestamps` (step started/finished)

All are **cleared** after `AgentStep` is finalized.

---

## Phase 0 — Safety & Constraints (No Behavior Change)
- Confirm no `AgentState` inside any context/outcome class.
- Ensure no new recursion or cyclic references.

---

## Phase 1 — Data Model Changes (Minimal, Safe)

### 1.1 Extend `CurrentExecution`
Add optional fields (nullable) for:
- `inputMessages`, `inferenceResponse`, `toolExecutions`, `outputMessages`, `errors`, `continuationOutcome`, `completedAt`.

Add fluent immutables:
- `withInputMessages(Messages $m)`
- `withInferenceResponse(InferenceResponse $r)`
- `withToolExecutions(ToolExecutions $e)`
- `withOutputMessages(Messages $m)`
- `withErrors(ErrorList $e)`
- `withContinuationOutcome(ContinuationOutcome $o)`
- `withCompletedAt(DateTimeImmutable $t)`
- `clearedTransient()` (returns new CurrentExecution with transient fields nulled)

### 1.2 Add AgentState helpers
- `withCurrentExecution(CurrentExecution $execution)`
- `withContinuationOutcome(ContinuationOutcome $outcome)` (writes to currentExecution)
- `continuationOutcome()` reads from currentExecution first, then last step execution

### 1.3 Keep `ContinuationDecision` and `ContinuationOutcome`
No new decision objects. No StopDecision.

---

## Phase 2 — Hook System Simplification

### 2.1 Remove `HookContext`
- Delete `AgentHooks/Data/*HookContext` classes.
- Update `Hook` interface signature to accept `AgentState`.

### 2.2 Update `HookStack`
- `process(AgentState $state, HookType $type, callable $terminal): AgentState`.
- Use `EventTypeMatcher` internally (or inject via matcher in `addHook`).

### 2.3 Fix `addHook()`
- Enforce event filtering via `EventTypeMatcher` + `CompositeMatcher::and`.
- Accept 1‑arg and 2‑arg callbacks.
  - 1‑arg: `fn(AgentState $state): AgentState`
  - 2‑arg: `fn(AgentState $state, callable $next): AgentState`

### 2.4 Update built‑in hooks
- All hooks: `handle(AgentState $state, callable $next): AgentState`.
- Hooks that affect continuation **write** `ContinuationOutcome` into `CurrentExecution`.
- Hooks that only mutate state return modified state.

---

## Phase 3 — Remove ContinuationCriteria System

### 3.1 Delete criteria classes
Remove:
- `ContinuationCriteria`
- `EvaluationProcessor`
- Individual criteria classes

### 3.2 Replace criteria with hooks
Implement hook equivalents:
- `StepsLimitHook` → `BeforeStep`
- `ExecutionTimeLimitHook` → `BeforeStep`
- `TokenUsageLimitHook` → `BeforeStep`
- `FinishReasonHook` → `AfterStep`
- `ToolCallPresenceHook` → `AfterStep`
- `ErrorPolicyHook` → `OnError` or `AfterStep`

Each writes a `ContinuationOutcome` to `CurrentExecution`.

### 3.3 Deterministic resolution
If multiple hooks write outcomes, resolution is:
- First hook to set a **ForbidContinuation** outcome wins.
- Else first **RequestContinuation** wins.
- Else **AllowStop** (default if no hook wrote outcome).

(This is identical to old criteria precedence; no new rules.)

---

## Phase 4 — AgentLoop Integration

### 4.1 Hook‑only loop
`AgentLoop` will:
- Run `ExecutionStart` hooks
- For each step:
  - `BeforeStep` hooks
  - Inference
  - `AfterInference` hooks (optional if added)
  - Tool execution (with `BeforeToolUse`/`AfterToolUse` hooks)
  - `AfterStep` hooks
- After each phase, read `AgentState::continuationOutcome()`.
- Stop when outcome indicates stop.

### 4.2 Stop hooks
- If needed, keep `Stop` hook as last‑chance override.
- It must only write `ContinuationOutcome` into state.

---

## Phase 5 — Tool Blocking / Error Policy

- Introduce `ErrorType::ToolBlocked`.
- Extend `ErrorPolicy` with `onToolBlocked` decision.
- When tool hook blocks, translate to `ToolCallBlockedException` tagged as `ToolBlocked`.
- Error policy decides whether to continue or stop (via hook).

---

## Phase 6 — Cleanups & Migration

- Remove `StopDecision` and `ToolUseDecision` classes.
- Update observers and adapters to hook‑only.
- Update docs and examples.
- Ensure serialization ignores transient `CurrentExecution` fields.

---

## Risks & Mitigations

- **Risk:** stale transient data in `CurrentExecution`.
  - **Mitigation:** `clearedTransient()` after step finalization and after error handling.

- **Risk:** ambiguous outcome when multiple hooks write outcomes.
  - **Mitigation:** explicit precedence rules + single write helper.

- **Risk:** hooks needing inference data.
  - **Mitigation:** store inference response/messages in `CurrentExecution` until step is finalized.

---

## Success Criteria
- One loop‑control abstraction (`ContinuationOutcome`).
- Hooks are authoritative and deterministic.
- No `AgentState` embedded anywhere else.
- No parallel continuation system.
- Lower nesting, fewer adapters, no ignored outcomes.

