# Hooks vs Continuations: Current State Assessment

**Date:** 2026-01-28

---

## The Problem

We have **two parallel systems** controlling agent execution flow:

1. **ContinuationCriteria** - Post-step evaluation, returns decisions
2. **Hooks (via HookStackObserver)** - Lifecycle events, returns outcomes

These systems don't integrate cleanly, leading to:
- Confusing semantics about which is authoritative
- `HookOutcome::stop()` exists but is silently ignored in lifecycle hooks
- No clean way for hooks to cause stop without hacks
- Duplicate concepts (StopDecision vs HookOutcome, etc.)

---

## Current Architecture

### ContinuationCriteria System

**Location:** `/packages/agents/src/Core/Continuation/`

**Flow:**
```
AgentLoop.shouldContinue()
    → ContinuationCriteria.evaluate(state)
    → [Criterion.evaluate(state) for each criterion]
    → EvaluationProcessor.shouldContinue(evaluations)
    → ContinuationOutcome (shouldContinue, stopReason, resolvedBy)
```

**Criteria (10 total):**
| Criterion | Type | Purpose |
|-----------|------|---------|
| StepsLimit | Guard | Max steps enforcement |
| ExecutionTimeLimit | Guard | Max duration enforcement |
| TokenUsageLimit | Guard | Token budget limit |
| ErrorPresenceCheck | Guard | Error detection |
| ErrorPolicyCriterion | Guard | Error policy evaluation |
| RetryLimit | Guard | Retry limit enforcement |
| FinishReasonCheck | Guard | LLM finish reason check |
| ToolCallPresenceCheck | Work driver | Tool execution driver |
| ResponseContentCheck | Hybrid | Content-based decisions |
| CallableCriterion | Generic | Wraps callable |

**Decision Resolution:**
1. ANY `ForbidContinuation` → STOP
2. ANY `RequestContinuation` → CONTINUE (overrides AllowStop)
3. ANY `AllowStop` → STOP
4. ANY `AllowContinuation` → CONTINUE

### Hooks System

**Location:** `/packages/agents/src/AgentHooks/`

**HookTypes:**
- Tool: `PreToolUse`, `PostToolUse`
- Step: `BeforeStep`, `AfterStep`
- Execution: `ExecutionStart`, `ExecutionEnd`
- Continuation: `Stop`, `SubagentStop`
- Error: `AgentFailed`

**HookOutcome:**
- `proceed(?context)` - Continue with optional modified context
- `block(reason)` - Block the action (tool only)
- `stop(reason)` - Stop execution

**Problem: Outcomes are ignored for most hooks:**
| Hook | stop() | block() |
|------|--------|---------|
| BeforeStepHook | IGNORED | IGNORED |
| AfterStepHook | IGNORED | IGNORED |
| ExecutionStartHook | IGNORED | IGNORED |
| ExecutionEndHook | IGNORED | IGNORED |
| AgentFailedHook | IGNORED | IGNORED |
| BeforeToolHook | Handled | Handled |
| AfterToolHook | IGNORED | IGNORED |
| StopHook | Handled | Handled |

### CanObserveAgentLifecycle Interface

**Location:** `/packages/agents/src/Core/Lifecycle/`

**Methods (inconsistent signatures):**
```php
// Returns modified state
onBeforeExecution(AgentState $state): AgentState
onAfterExecution(AgentState $state): AgentState
onBeforeStep(AgentState $state): AgentState
onAfterStep(AgentState $state): AgentState
onError(AgentState $state, AgentException $exception): AgentState

// Returns decision objects
onBeforeToolUse(ToolCall $toolCall, AgentState $state): ToolUseDecision  // Param order differs!
onAfterToolUse(ToolExecution $execution, AgentState $state): ToolExecution

// Returns stop decision
onBeforeStopDecision(AgentState $state, StopReason $reason): StopDecision
```

**Missing lifecycle points:**
- BeforeInference / AfterInference
- BeforeContinuationEvaluation
- OnPause / OnResume (for iterative execution)

---

## Known Bugs

### 1. addHook() ignores event type

**File:** `AgentBuilder.php:440-454`

The `$event` parameter is accepted but not used to filter when the hook runs. All hooks run on all events.

### 2. CallableHook signature mismatch

**File:** `CallableHook.php:35-46`

Expects `callable(HookContext, callable): HookOutcome` but docs show 1-arg callbacks.

### 3. Synthetic ContinuationOutcome in stop hooks

**File:** `HookStackObserver.php:184-199`

`onBeforeStopDecision()` creates a fake `ContinuationOutcome` instead of passing the real one from criteria evaluation. Stop hooks can't see which criterion actually triggered the stop.

### 4. State mutations can't cleanly cause stop

- `withStatus(Failed)` creates new execution if none exists (side effect)
- No `withFailure()` method exists (only `failWith()` which expects AgentException)
- Status doesn't control flow - continuation outcome does

---

## The Core Conflict

```
ContinuationCriteria: "I evaluate state and decide continue/stop"
Hooks: "I can return stop() but it's ignored"
Observer: "I can prevent stop via StopDecision::prevent()"

Who's actually in charge?
```

Currently: ContinuationCriteria is authoritative, hooks are advisory (but API suggests otherwise).

---

## Impact

1. **Developer confusion** - Two systems, unclear which to use
2. **Ignored outcomes** - `HookOutcome::stop()` is misleading
3. **Incomplete data** - Stop hooks get synthetic outcomes
4. **Inconsistent API** - Observer method signatures vary
5. **Broken addHook()** - Event filtering doesn't work

---

## Solution Direction

See `clean-implementation-plan.md` and `02-proposal.md` for the resolution:

1. **Delete HookContext** - Hooks receive `AgentState` directly
2. **Hooks return AgentState** - Not decision objects
3. **Single flow control** - `ContinuationOutcome` written to `CurrentExecution`
4. **Delete ContinuationCriteria** - Convert all criteria to hooks
5. **CurrentExecution as transient carrier** - All in-flight step data lives here
6. **No AgentState embedded anywhere** - Passed as param, not stored in contexts
