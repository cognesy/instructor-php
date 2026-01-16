# Continuation Decision Tracing + Error Policy Redesign Spec

## Goals
- Make continuation decisions explainable and observable for UI, logs, and support.
- Separate "should we continue" logic from "how we handle errors" policy.
- Preserve current behavior by default while enabling richer diagnostics and safe overrides.

## Non-goals
- No changes to tool schemas or model provider integrations.
- No change to execution loop structure (`StepByStep`) beyond decision evaluation and reporting.

## Current State (Summary)
- `ContinuationCriteria::canContinue()` collapses multiple decisions into a boolean.
- `ContinuationDecision` is a flat enum with no context or reason.
- Default criteria include `ErrorPresenceCheck`, which forbids continuation on any error.
- `RetryLimit` exists but is effectively disabled by `ErrorPresenceCheck` in the base list (ErrorPresenceCheck forbids first, making RetryLimit unreachable).

Key references:
- `packages/addons/src/StepByStep/Continuation/ContinuationCriteria.php`
- `packages/addons/src/StepByStep/Continuation/ContinuationDecision.php`
- `packages/addons/src/Agent/AgentBuilder.php`
- `packages/addons/src/StepByStep/Continuation/Criteria/ErrorPresenceCheck.php`
- `packages/addons/src/StepByStep/Continuation/Criteria/RetryLimit.php`

## Proposed Design

### 1) Rich Continuation Results
Introduce a structured decision record per criterion and a resolved outcome.

Core objects:
- `ContinuationEvaluation` - per-criterion decision record
  - `criterionClass` (string)
  - `decision` (`ContinuationDecision`)
  - `reason` (string)
  - `context` (array, optional)
- `ContinuationOutcome` - aggregate result with full trace
  - `decision` (final `ContinuationDecision`)
  - `shouldContinue` (bool)
  - `resolvedBy` (criterion class name)
  - `stopReason` (`StopReason` enum)
  - `evaluations` (list of `ContinuationEvaluation`)

Note: Original design had a separate `ContinuationTrace` wrapper, but evaluations are now directly on `ContinuationOutcome` for simplicity.

### 2) Standardized Stop Reasons
New `StopReason` enum for UI consumption:
- `Completed` - natural completion, no more work
- `StepsLimitReached` - max steps exceeded
- `TokenLimitReached` - max tokens exceeded
- `TimeLimitReached` - max execution time exceeded
- `RetryLimitReached` - max error retries exceeded
- `ErrorForbade` - error policy stopped execution
- `FinishReasonReceived` - LLM returned terminal finish reason
- `GuardForbade` - custom guard criterion forbade
- `UserRequested` - external stop request

Behavior:
- `ContinuationCriteria::evaluate(state)` returns `ContinuationOutcome`.
- `ContinuationCriteria::canContinue(state)` delegates to `evaluate` and returns `shouldContinue`.
- `ContinuationCriteria::decide(state)` can return the final decision for backward compatibility.

Trace resolution rules (same priority as today):
1) ForbidContinuation wins immediately.
2) RequestContinuation wins if no forbid exists.
3) AllowStop wins if no forbid or request exists.
4) AllowContinuation wins only if no stop exists.

### 3) Explainability Hooks
Allow criteria to provide reasons without changing every existing class.

Optional interface `CanExplainContinuation`:
```php
interface CanExplainContinuation {
    public function explain(object $state): ContinuationEvaluation;
}
```

Default reasoning (if no explicit reason):
- Generate from `criterionClass` + decision (e.g., "StepsLimit forbade continuation").

### 4) Error Type Classification
New `ErrorType` enum for granular error handling:
- `Tool` - tool execution failed
- `Model` - LLM returned error or refused
- `Validation` - response validation/parsing failed
- `RateLimit` - provider rate limit hit (typically retryable)
- `Timeout` - request timed out
- `Unknown` - unclassified error

Error classification is handled by `CanResolveErrorContext` interface with default implementation `AgentErrorContextResolver`.

### 5) Error Policy Separation
Replace `ErrorPresenceCheck` + `RetryLimit` with a unified policy-driven criterion.

`ErrorPolicy` object with named constructors (presets):
- `ErrorPolicy::stopOnAnyError()` - default, matches current behavior
- `ErrorPolicy::retryToolErrors(maxRetries)` - retry tool errors, stop on model errors
- `ErrorPolicy::ignoreToolErrors()` - ignore tool errors entirely
- `ErrorPolicy::retryAll(maxRetries)` - lenient, retry everything

Policy structure:
- `onToolError` (enum): `Stop`, `Retry`, `Ignore`
- `onModelError` (enum): `Stop`, `Retry`, `Ignore`
- `onValidationError` (enum): `Stop`, `Retry`, `Ignore`
- `onRateLimitError` (enum): `Stop`, `Retry`, `Ignore`
- `onTimeoutError` (enum): `Stop`, `Retry`, `Ignore`
- `onUnknownError` (enum): `Stop`, `Retry`, `Ignore`
- `maxRetries` (int)

`ErrorPolicyCriterion` **replaces both** `ErrorPresenceCheck` and `RetryLimit`:
- Uses `CanResolveErrorContext` interface (not closure) for testability
- Implements `CanExplainContinuation` for rich diagnostics
- Handles retry counting internally based on `ErrorPolicy.maxRetries`

### 6) Agent State + Events
Expose continuation outcome for observability.

State additions:
- `AgentState::lastContinuationOutcome()` - returns most recent outcome
- `AgentState::withContinuationOutcome()` - immutable setter

New event:
- `ContinuationEvaluated` - emitted after each evaluation, contains full `ContinuationOutcome`

Integration with existing events:
- `AgentStepCompleted` can reference `ContinuationOutcome` for unified observability

Key references:
- `packages/addons/src/Agent/Agent.php`
- `packages/addons/src/Agent/Events/AgentStepCompleted.php`

## Migration Plan
- **Phase 1**: Add `evaluate()` and trace objects; keep `canContinue()` and `decide()` compatible.
- **Phase 2**: Add `CanExplainContinuation` interface for criteria that can provide reasons.
- **Phase 3**: Add `ErrorPolicy`, `ErrorType`, `ErrorContext`, `CanResolveErrorContext`, and `ErrorPolicyCriterion`. Remove `ErrorPresenceCheck`/`RetryLimit` from base criteria list.
- **Phase 4**: Expose `ContinuationOutcome` via events and state.
- **Phase 5**: Update docs and examples (AgentBuilder config, troubleshooting guide).

## Breaking Changes
- `ErrorPresenceCheck` removed from base criteria (replaced by `ErrorPolicyCriterion`)
- `RetryLimit` removed from base criteria (functionality merged into `ErrorPolicyCriterion`)

Default behavior preserved via `ErrorPolicy::stopOnAnyError()`.

## File Locations

| Type | Location |
|------|----------|
| `ContinuationEvaluation` | `packages/addons/src/StepByStep/Continuation/ContinuationEvaluation.php` |
| `ContinuationOutcome` | `packages/addons/src/StepByStep/Continuation/ContinuationOutcome.php` |
| `StopReason` | `packages/addons/src/StepByStep/Continuation/StopReason.php` |
| `CanExplainContinuation` | `packages/addons/src/StepByStep/Continuation/CanExplainContinuation.php` |
| `ErrorType` | `packages/addons/src/StepByStep/Continuation/ErrorType.php` |
| `ErrorContext` | `packages/addons/src/StepByStep/Continuation/ErrorContext.php` |
| `ErrorHandlingDecision` | `packages/addons/src/StepByStep/Continuation/ErrorHandlingDecision.php` |
| `ErrorPolicy` | `packages/addons/src/StepByStep/Continuation/ErrorPolicy.php` |
| `CanResolveErrorContext` | `packages/addons/src/StepByStep/Continuation/CanResolveErrorContext.php` |
| `ErrorPolicyCriterion` | `packages/addons/src/StepByStep/Continuation/Criteria/ErrorPolicyCriterion.php` |
| `AgentErrorContextResolver` | `packages/addons/src/Agent/Core/Continuation/AgentErrorContextResolver.php` |
| `ContinuationEvaluated` | `packages/addons/src/Agent/Events/ContinuationEvaluated.php` |

## Design Decisions Made

| Question | Decision |
|----------|----------|
| Should `ErrorPolicy` live in StepByStep or Agent? | **StepByStep** - generic enough to be reusable |
| Should `ContinuationOutcome` be persisted in state or only via events? | **Both** - state for debugging, events for observability |
| Do we want a standard `StopReason` enum? | **Yes** - defined with 9 cases for UI consumption |
| `ErrorPolicyMode` enum vs named constructors? | **Named constructors** - clearer API, avoids mode+granular redundancy |
| Closure vs interface for error context? | **Interface** (`CanResolveErrorContext`) - better testability |
| Separate `ContinuationTrace` class? | **No** - evaluations directly on `ContinuationOutcome` |

## Related Issues (from PRM Team Feedback)

This redesign addresses:
- **Issue 2**: ContinuationCriteria lacks observability → `ContinuationOutcome` with full trace
- **Issue 5**: No built-in execution logging → `ContinuationEvaluated` event
- **Issue 6**: Agent stops after one step → Resolved by observability (hidden guard was blocking)

Not addressed by this redesign (separate work items):
- **Issue 1**: Tool args leak into message content (bug in `OpenAIResponseAdapter`)
- **Issue 3**: Cumulative time tracking for pause/resume
- **Issue 4**: Message role convenience methods

---

## CRITICAL BUG: ExecutionTimeLimit Semantics

**See `remaining-prm-issues-spec.md` for full details.**

### Summary
`ExecutionTimeLimit` currently uses `stateInfo.startedAt` (session creation time) instead of per-execution start time. This causes immediate timeouts in multi-turn conversations spanning days.

### Impact on this Redesign
- `StopReason::TimeLimitReached` may fire incorrectly if not fixed
- `ContinuationOutcome` will correctly report the stop reason, but the reason itself is wrong
- **Fix is required BEFORE this redesign** to ensure correct behavior

### Relationship to Time Limits

| Concept | Purpose | Addressed By |
|---------|---------|--------------|
| `ExecutionTimeLimit` | Prevent single query from running too long | Uses `executionStartedAt` (FIX NEEDED) |
| `CumulativeExecutionTimeLimit` | Track total processing across pause/resume | Issue 3, Phase 4 |
| `stateInfo.startedAt` | Session age tracking, audit | Existing (no change) |
