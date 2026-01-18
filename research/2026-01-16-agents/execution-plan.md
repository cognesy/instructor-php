# Agent Framework Improvements: Comprehensive Execution Plan

**Date**: 2026-01-16
**Status**: Ready for Implementation
**Responding to**: PRM Team Feedback + Internal Review

---

## Executive Summary

This plan addresses all issues identified in the PRM team feedback and internal review, organized into logical phases with clear dependencies.

### Scope

| Work Stream | Issues Addressed | Priority |
|-------------|------------------|----------|
| **0. CRITICAL BUG FIX** | ExecutionTimeLimit uses session start instead of execution start | **P0** |
| **1. Core Continuation Redesign** | Issue 2 (observability), Issue 6 (single-step stop) | HIGH |
| **2. Error Policy** | Error handling, retry semantics | HIGH |
| **3. Bug Fixes** | Issue 1 (tool args leak), Issue 4 (helpers) | HIGH/LOW |
| **4. Time Tracking** | Issue 3 (cumulative time for pause/resume) | MEDIUM |
| **5. Serialization** | Slim state, pause/resume | MEDIUM |
| **6. Reverb/Events** | Broadcasting, event adapter | MEDIUM |
| **7. UI Contract** | Tool call rendering | MEDIUM |

### Reference Documents

| Document | Description |
|----------|-------------|
| `continuation-api-proposal.md` | Continuation tracing + error policy API |
| `continuation-tracing-redesign-spec.md` | High-level design spec |
| `remaining-prm-issues-spec.md` | Issues 1, 3, 4 specifications |
| `slim-serialization-reverb-adapter.md` | Serialization + Reverb adapter |
| `ui-tool-call-rendering-contract.md` | UI rendering contract |

---

## Phase 0: CRITICAL BUG FIX (Immediate - Day 1)

### 0.1 ExecutionTimeLimit Uses Wrong Timestamp
**Effort**: 4 hours | **Dependencies**: None | **Risk**: LOW (fix is straightforward) | **Priority**: **P0 - BLOCKS ALL MULTI-TURN USAGE**

#### Problem
`ExecutionTimeLimit` measures elapsed time from `stateInfo.startedAt`, which is set ONCE when the session is created. For multi-turn conversations spanning days, this causes immediate timeouts.

**Example:**
1. Day 1, 10:00 AM: Session created → `stateInfo.startedAt` = Day 1
2. Day 2, 10:00 AM: User sends query
3. ExecutionTimeLimit: `now - startedAt` = 24 hours → **TIMEOUT!**

#### Root Cause

```php
// AgentBuilder.php:305
new ExecutionTimeLimit($this->maxExecutionTime, static fn($state) => $state->startedAt(), null),
```

The `$state->startedAt()` returns `stateInfo.startedAt` which is the SESSION creation time, not the EXECUTION start time.

#### Solution

**Step 1: Add `executionStartedAt` to `AgentState`**

```php
// packages/addons/src/Agent/Core/Data/AgentState.php

public ?DateTimeImmutable $executionStartedAt;

public function markExecutionStarted(): self {
    return $this->with(executionStartedAt: new DateTimeImmutable());
}

public function executionStartedAt(): ?DateTimeImmutable {
    return $this->executionStartedAt;
}

// In toArray(): DO include for debugging
'executionStartedAt' => $this->executionStartedAt?->format(DATE_ATOM),

// In fromArray(): DO NOT restore - each execution starts fresh
executionStartedAt: null,
```

**Step 2: Reset at Execution Entry Point**

```php
// packages/addons/src/StepByStep/StepByStep.php (or Agent.php)

public function finalStep(object $state): object {
    // Reset execution clock at start of run
    if (method_exists($state, 'markExecutionStarted')) {
        $state = $state->markExecutionStarted();
    }

    while ($this->hasNextStep($state)) {
        $state = $this->nextStep($state);
    }
    return $this->onNoNextStep($state);
}
```

**Step 3: Update AgentBuilder**

```php
// packages/addons/src/Agent/AgentBuilder.php:305
new ExecutionTimeLimit(
    $this->maxExecutionTime,
    static fn($state) => $state->executionStartedAt() ?? $state->startedAt(),  // Safe fallback
    null
),
```

#### Files to Modify

| File | Change |
|------|--------|
| `packages/addons/src/Agent/Core/Data/AgentState.php` | Add `executionStartedAt` field, `markExecutionStarted()`, serialization |
| `packages/addons/src/StepByStep/StepByStep.php` | Call `markExecutionStarted()` in `finalStep()` and `iterator()` |
| `packages/addons/src/Agent/AgentBuilder.php` | Use `executionStartedAt()` in ExecutionTimeLimit |

#### Tests

- [ ] Fresh execution: `executionStartedAt` set to now
- [ ] Multi-turn conversation: Each query resets `executionStartedAt`, no immediate timeout
- [ ] Serialized/restored state: `executionStartedAt` is null after restore, set fresh on next run
- [ ] Long pause (1 week): Execution starts fresh without timeout
- [ ] Session tracking unchanged: `stateInfo.startedAt` still reflects original creation time
- [ ] Fallback: If `executionStartedAt` is null, falls back to `startedAt()`

#### Semantics Clarification

| Timestamp | Set When | Purpose |
|-----------|----------|---------|
| `stateInfo.startedAt` | Session creation | Audit, session age tracking |
| `executionStartedAt` | Each user query | ExecutionTimeLimit per-query |
| `cumulativeExecutionSeconds` | Accumulated per step | Track processing time across pause/resume |

---

## Phase 1: Foundation (Week 1)

### 1.1 Message Role Helpers (Issue 4)
**Effort**: 2 hours | **Dependencies**: None | **Risk**: Low

Add convenience methods to `Message` class.

**Files**:
- `packages/messages/src/Message.php`

**Implementation**:
```php
public function isUser(): bool;
public function isAssistant(): bool;
public function isTool(): bool;
public function isSystem(): bool;
public function isDeveloper(): bool;
public function hasRole(MessageRole ...$roles): bool;
```

**Tests**:
- [ ] `isUser()` returns true for user messages
- [ ] `isAssistant()` returns true for assistant messages
- [ ] `isTool()` returns true for tool messages
- [ ] `isSystem()` returns true for system AND developer messages
- [ ] `hasRole()` works with multiple role arguments

---

### 1.2 Tool Args Leak Fix (Issue 1)
**Effort**: 4 hours | **Dependencies**: None | **Risk**: Medium

Fix tool call arguments appearing in message content.

**Files**:
- `packages/polyglot/src/Inference/Drivers/OpenAI/OpenAIResponseAdapter.php`
- `packages/addons/src/Agent/Drivers/ToolCalling/ToolCallingDriver.php`

**Implementation**:

Option A (Primary - Source Fix):
```php
// OpenAIResponseAdapter.php:82-90
protected function makeContent(array $data): string {
    return $data['choices'][0]['message']['content'] ?? '';
}
```

Option B (Defense-in-Depth):
```php
// ToolCallingDriver.php:150-169
private function buildStepFromResponse(...): AgentStep {
    $content = $response->content();
    $hasToolCalls = $response->hasToolCalls();

    $outputMessages = ($content !== '' && !$hasToolCalls)
        ? $followUps->appendMessage(Message::asAssistant($content))
        : $followUps;
    // ...
}
```

**Tests**:
- [ ] Tool call with empty content → no assistant message
- [ ] Tool call with natural language → assistant message preserved
- [ ] Final response without tool calls → normal behavior
- [ ] Streaming: tool args don't leak to content delta

---

## Phase 2: Continuation Tracing (Week 1-2)

### 2.1 Core Types
**Effort**: 4 hours | **Dependencies**: None | **Risk**: Low

Create new continuation-related types.

**New Files**:
- `packages/addons/src/StepByStep/Continuation/StopReason.php`
- `packages/addons/src/StepByStep/Continuation/ContinuationEvaluation.php`
- `packages/addons/src/StepByStep/Continuation/ContinuationOutcome.php`
- `packages/addons/src/StepByStep/Continuation/CanExplainContinuation.php`

**Implementation**: See `continuation-api-proposal.md` for full code.

**Tests**:
- [ ] `StopReason` enum has all 9 cases
- [ ] `ContinuationEvaluation::fromDecision()` generates default reason
- [ ] `ContinuationOutcome::shouldContinue()` returns correct boolean
- [ ] `ContinuationOutcome::getForbiddingCriterion()` finds first forbid

---

### 2.2 ContinuationCriteria.evaluate()
**Effort**: 4 hours | **Dependencies**: 2.1 | **Risk**: Medium

Add `evaluate()` method to `ContinuationCriteria`.

**Files**:
- `packages/addons/src/StepByStep/Continuation/ContinuationCriteria.php`

**Implementation**:
```php
public function evaluate(object $state): ContinuationOutcome;
// canContinue() and decide() delegate to evaluate()
```

**Tests**:
- [ ] `evaluate()` returns correct outcome for forbid scenario
- [ ] `evaluate()` returns correct outcome for continue scenario
- [ ] `evaluate()` includes all criterion evaluations
- [ ] `canContinue()` matches `evaluate()->shouldContinue`
- [ ] `decide()` matches `evaluate()->decision`
- [ ] Empty criteria returns AllowStop with empty evaluations

---

### 2.3 Continuation Event
**Effort**: 2 hours | **Dependencies**: 2.1, 2.2 | **Risk**: Low

Add new event for continuation decisions.

**New Files**:
- `packages/addons/src/Agent/Events/ContinuationEvaluated.php`

**Files to Modify**:
- `packages/addons/src/Agent/Agent.php` (emit event)

**Tests**:
- [ ] Event emitted after each step
- [ ] Event contains full `ContinuationOutcome`
- [ ] `__toString()` produces readable output

---

## Phase 3: Error Policy (Week 2)

### 3.1 Error Types
**Effort**: 4 hours | **Dependencies**: None | **Risk**: Low

Create error classification types.

**New Files**:
- `packages/addons/src/StepByStep/Continuation/ErrorType.php`
- `packages/addons/src/StepByStep/Continuation/ErrorHandlingDecision.php`
- `packages/addons/src/StepByStep/Continuation/ErrorContext.php`
- `packages/addons/src/StepByStep/Continuation/CanResolveErrorContext.php`

**Tests**:
- [ ] `ErrorType` has all 6 cases
- [ ] `ErrorHandlingDecision` has Stop, Retry, Ignore
- [ ] `ErrorContext::none()` returns zeroed context

---

### 3.2 ErrorPolicy
**Effort**: 4 hours | **Dependencies**: 3.1 | **Risk**: Low

Create configurable error policy with named constructors.

**New Files**:
- `packages/addons/src/StepByStep/Continuation/ErrorPolicy.php`

**Implementation**:
```php
ErrorPolicy::stopOnAnyError();     // Default
ErrorPolicy::retryToolErrors(3);   // Retry tools
ErrorPolicy::ignoreToolErrors();   // Ignore tools
ErrorPolicy::retryAll(5);          // Lenient
```

**Tests**:
- [ ] `stopOnAnyError()` returns Stop for all error types
- [ ] `retryToolErrors(3)` returns Retry for tool errors
- [ ] `evaluate()` respects maxRetries
- [ ] Fluent modifiers work correctly

---

### 3.3 ErrorPolicyCriterion
**Effort**: 4 hours | **Dependencies**: 2.1, 3.1, 3.2 | **Risk**: Medium

Create unified error policy criterion replacing `ErrorPresenceCheck` + `RetryLimit`.

**New Files**:
- `packages/addons/src/StepByStep/Continuation/Criteria/ErrorPolicyCriterion.php`
- `packages/addons/src/Agent/Core/Continuation/AgentErrorContextResolver.php`

**Tests**:
- [ ] Implements `CanDecideToContinue` and `CanExplainContinuation`
- [ ] Returns ForbidContinuation on Stop
- [ ] Returns AllowContinuation on Retry/Ignore
- [ ] Explanation includes error type, count, handling
- [ ] Error classification works for tool, model, rate limit errors

---

### 3.4 AgentBuilder Integration
**Effort**: 2 hours | **Dependencies**: 3.3 | **Risk**: Medium

Update AgentBuilder to use new error policy.

**Files**:
- `packages/addons/src/Agent/AgentBuilder.php`

**Changes**:
- Add `withErrorPolicy(ErrorPolicy $policy): self`
- Replace `ErrorPresenceCheck` + `RetryLimit` with `ErrorPolicyCriterion`
- Default to `ErrorPolicy::stopOnAnyError()` for backward compatibility

**Tests**:
- [ ] Default behavior unchanged (stop on any error)
- [ ] Custom error policy applied correctly
- [ ] `withErrorPolicy()` fluent builder works

---

## Phase 4: Cumulative Time Tracking (Week 2-3)

### 4.1 StateInfo Enhancement
**Effort**: 2 hours | **Dependencies**: None | **Risk**: Low

Add cumulative execution time to StateInfo.

**Files**:
- `packages/addons/src/StepByStep/State/StateInfo.php`

**Changes**:
```php
private float $cumulativeExecutionSeconds = 0.0;

public function cumulativeExecutionSeconds(): float;
public function addExecutionTime(float $seconds): self;
```

**Tests**:
- [ ] New field serializes/deserializes correctly
- [ ] `addExecutionTime()` accumulates correctly
- [ ] Backward compatible with existing serialized data

---

### 4.2 CumulativeExecutionTimeLimit
**Effort**: 2 hours | **Dependencies**: 2.1, 4.1 | **Risk**: Low

Create cumulative time limit criterion.

**New Files**:
- `packages/addons/src/StepByStep/Continuation/Criteria/CumulativeExecutionTimeLimit.php`

**Tests**:
- [ ] Implements `CanExplainContinuation`
- [ ] Returns ForbidContinuation when cumulative time exceeded
- [ ] Returns AllowContinuation when under limit
- [ ] Explanation includes cumulative time and limit

---

### 4.3 Agent Step Duration Tracking
**Effort**: 2 hours | **Dependencies**: 4.1, 4.2 | **Risk**: Medium

Track step duration in Agent execution loop.

**Files**:
- `packages/addons/src/Agent/Agent.php`

**Changes**:
- Record step start time before tool execution
- Calculate duration after step completion
- Update state with `addExecutionTime()`

**Tests**:
- [ ] Step duration recorded accurately
- [ ] Cumulative time matches sum of step durations
- [ ] Pause/resume preserves cumulative time

---

### 4.4 AgentBuilder Cumulative Timeout
**Effort**: 1 hour | **Dependencies**: 4.1, 4.2, 4.3 | **Risk**: Low

Add option to use cumulative time limit.

**Files**:
- `packages/addons/src/Agent/AgentBuilder.php`

**Changes**:
- Add `withCumulativeTimeout(int $seconds): self`
- Conditionally use `CumulativeExecutionTimeLimit`

**Tests**:
- [ ] Default still uses wall-clock `ExecutionTimeLimit`
- [ ] `withCumulativeTimeout()` switches to cumulative

---

## Phase 5: Serialization (Week 3)

### 5.1 Slim Serialization Config
**Effort**: 2 hours | **Dependencies**: None | **Risk**: Low

Create configurable serialization.

**New Files**:
- `packages/addons/src/Agent/Serialization/SlimSerializationConfig.php`

**Tests**:
- [ ] `minimal()` preset works
- [ ] `standard()` preset works
- [ ] `full()` preset works

---

### 5.2 SlimAgentStateSerializer
**Effort**: 4 hours | **Dependencies**: 4.1, 5.1 | **Risk**: Medium

Create slim serializer implementation.

**New Files**:
- `packages/addons/src/Agent/Serialization/CanSerializeAgentState.php`
- `packages/addons/src/Agent/Serialization/SlimAgentStateSerializer.php`

**Tests**:
- [ ] Message truncation works
- [ ] Content length truncation works
- [ ] Tool args redaction works
- [ ] Cumulative time included
- [ ] Deserialization produces valid state
- [ ] Round-trip preserves essential data

---

## Phase 6: Reverb/Events (Week 3-4)

### 6.1 Event Adapter Interface
**Effort**: 1 hour | **Dependencies**: None | **Risk**: Low

Create broadcasting interface.

**New Files**:
- `packages/addons/src/Agent/Broadcasting/CanBroadcastAgentEvents.php`

---

### 6.2 AgentEventEnvelopeAdapter
**Effort**: 4 hours | **Dependencies**: 2.3, 6.1 | **Risk**: Medium

Create Reverb event adapter.

**New Files**:
- `packages/addons/src/Agent/Broadcasting/AgentEventEnvelopeAdapter.php`

**Tests**:
- [ ] `agent.step.started` emitted correctly
- [ ] `agent.step.completed` emitted with usage, duration
- [ ] `agent.tool.started` uses correct event keys
- [ ] `agent.tool.completed` uses correct event keys
- [ ] `agent.continuation` optional, includes evaluations
- [ ] Envelope format matches spec

---

### 6.3 Verify Tool Event Keys
**Effort**: 1 hour | **Dependencies**: None | **Risk**: Low

Verify event keys match adapter expectations.

**Files to Verify**:
- `packages/addons/src/Agent/Events/ToolCallStarted.php`
- `packages/addons/src/Agent/Events/ToolCallCompleted.php`

**Verify**:
- [ ] `ToolCallStarted` has `tool` property (not `name`)
- [ ] `ToolCallCompleted` has `tool`, `success`, `error` properties

---

## Phase 7: Documentation (Week 4)

### 7.1 Troubleshooting Guide
**Effort**: 2 hours | **Dependencies**: All previous | **Risk**: Low

Create documentation for common continuation issues.

**Topics**:
- Why did my agent stop after one step?
- How to debug continuation decisions
- Configuring error policies
- Using cumulative time for pause/resume

---

### 7.2 Migration Guide
**Effort**: 2 hours | **Dependencies**: All previous | **Risk**: Low

Document breaking changes and migration path.

**Topics**:
- `ErrorPresenceCheck` → `ErrorPolicyCriterion`
- `RetryLimit` → `ErrorPolicy.maxRetries`
- Using slim serialization
- Reverb event envelope format

---

## Dependency Graph

```
Phase 0 (CRITICAL - Day 1) ⚠️
└── 0.1 ExecutionTimeLimit fix (BLOCKS ALL MULTI-TURN USAGE)

Phase 1 (Foundation)
├── 1.1 Message helpers (independent)
└── 1.2 Tool args fix (independent)

Phase 2 (Continuation)
├── 2.1 Core types (independent)
├── 2.2 ContinuationCriteria.evaluate() → depends on 2.1
└── 2.3 Continuation event → depends on 2.1, 2.2

Phase 3 (Error Policy)
├── 3.1 Error types (independent)
├── 3.2 ErrorPolicy → depends on 3.1
├── 3.3 ErrorPolicyCriterion → depends on 2.1, 3.1, 3.2
└── 3.4 AgentBuilder integration → depends on 3.3

Phase 4 (Time Tracking)
├── 4.1 StateInfo enhancement (independent)
├── 4.2 CumulativeExecutionTimeLimit → depends on 2.1, 4.1
├── 4.3 Agent step duration → depends on 4.1, 4.2
└── 4.4 AgentBuilder timeout → depends on 4.1, 4.2, 4.3

Phase 5 (Serialization)
├── 5.1 Config (independent)
└── 5.2 Serializer → depends on 4.1, 5.1

Phase 6 (Reverb)
├── 6.1 Interface (independent)
├── 6.2 Adapter → depends on 2.3, 6.1
└── 6.3 Verify events (independent)

Phase 7 (Docs)
└── All documentation → depends on all previous
```

**IMPORTANT**: Phase 0 MUST be completed before any multi-turn testing can occur!

---

## Test Matrix

### Unit Tests

| Component | Test File | Coverage |
|-----------|-----------|----------|
| Message helpers | `MessageTest.php` | Role checks |
| StopReason | `StopReasonTest.php` | All cases |
| ContinuationEvaluation | `ContinuationEvaluationTest.php` | Construction, defaults |
| ContinuationOutcome | `ContinuationOutcomeTest.php` | All methods |
| ContinuationCriteria | `ContinuationCriteriaTest.php` | evaluate(), priorities |
| ErrorType | `ErrorTypeTest.php` | All cases |
| ErrorPolicy | `ErrorPolicyTest.php` | Presets, evaluation |
| ErrorPolicyCriterion | `ErrorPolicyCriterionTest.php` | All scenarios |
| StateInfo | `StateInfoTest.php` | Cumulative time |
| CumulativeExecutionTimeLimit | `CumulativeExecutionTimeLimitTest.php` | Limits |
| SlimAgentStateSerializer | `SlimAgentStateSerializerTest.php` | Round-trip |
| AgentEventEnvelopeAdapter | `AgentEventEnvelopeAdapterTest.php` | All events |

### Integration Tests

| Scenario | Test File | Coverage |
|----------|-----------|----------|
| Tool call execution | `AgentToolCallTest.php` | Content not leaked |
| Error retry | `AgentErrorRetryTest.php` | Policy respected |
| Pause/resume | `AgentPauseResumeTest.php` | Cumulative time |
| Event broadcasting | `AgentEventBroadcastTest.php` | All events emitted |

---

## Risk Assessment

| Risk | Likelihood | Impact | Mitigation |
|------|------------|--------|------------|
| Breaking change in error handling | Medium | High | Default to `stopOnAnyError()` |
| Serialization incompatibility | Low | Medium | Keep `toArray()` unchanged |
| Event key mismatch | Low | Low | Verify existing event structure |
| Performance overhead from tracing | Low | Low | Trace collection is lightweight |

---

## Success Criteria

0. **CRITICAL BUG**: Multi-turn conversations work without immediate timeout
1. **Issue 1**: Tool args no longer appear in UI/conversation
2. **Issue 2**: `ContinuationOutcome` explains why agent stopped
3. **Issue 3**: Paused agents resume without immediate timeout (cumulative time)
4. **Issue 4**: `message.isAssistant()` works
5. **Issue 5**: Events provide full observability
6. **Issue 6**: Debugging shows which criterion stopped agent

---

## Timeline Summary

| Day/Week | Phase | Deliverables |
|----------|-------|--------------|
| **Day 1** | **Phase 0 (CRITICAL)** | **ExecutionTimeLimit fix - MUST DO FIRST** |
| Week 1 | Foundation + Continuation | Message helpers, tool args fix, core types |
| Week 2 | Continuation + Error Policy | evaluate(), ErrorPolicyCriterion, AgentBuilder |
| Week 3 | Time Tracking + Serialization | Cumulative time, slim serializer |
| Week 4 | Reverb + Docs | Event adapter, troubleshooting guide |

**Total Estimated Effort**: ~54 hours (including Phase 0)
