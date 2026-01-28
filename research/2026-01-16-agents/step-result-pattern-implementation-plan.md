# ContinuationOutcome-on-Step Pattern: Implementation Plan

## Summary

Agent now stores `ContinuationOutcome` on each `AgentStep`. This plan applies the same pattern to **ToolUse**, **Chat**, and **Collaboration** for consistency, using a new **StepResult** abstraction.

---

## Current State

### StepByStep Hierarchy

All four classes extend `StepByStep`:

| Class | Location | Uses ContinuationCriteria | Stores Outcome on Step |
|-------|----------|---------------------------|------------------------|
| **Agent** | `Agent/Agent.php` | Yes, `evaluateAll()` | **Yes** (on AgentStep) |
| **ToolUse** | `ToolUse/ToolUse.php` | Yes, `canContinue()` only | No |
| **Chat** | `Chat/Chat.php` | Yes, `canContinue()` only | No |
| **Collaboration** | `Collaboration/Collaboration.php` | Yes, `canContinue()` only | No |

### Agent Pattern (Reference Implementation)

```php
// Agent.performStep() - evaluates and attaches outcome to step
protected function performStep(object $state): object {
    $rawStep = $this->makeNextStep($stateWithStart);
    $transitionState = $stateWithStart->recordStep($rawStep);
    $outcome = $this->continuationCriteria->evaluateAll($transitionState);
    $completeStep = $rawStep->withContinuationOutcome($outcome);
    $nextState = $this->applyStep(state: $stateWithStart, nextStep: $completeStep);
    return $this->onStepCompleted($nextState);
}

// Agent.canContinue() - reads from step
protected function canContinue(object $state): bool {
    $currentStep = $state->currentStep();
    if ($currentStep === null) return true;
    return $currentStep->continuationOutcome()?->shouldContinue() ?? false;
}
```

### ToolUse/Chat/Collaboration Pattern (Current)

```php
// Uses simple boolean check - no outcome storage
protected function canContinue(object $state): bool {
    return $this->continuationCriteria->canContinue($state);
}

// No performStep() override - uses parent default
// No outcome attached to steps
```

---

## Key Differences

| Aspect | Agent | ToolUse/Chat/Collaboration |
|--------|-------|---------------------------|
| **Continuation Evaluation** | Full `ContinuationOutcome` with all criterion results | Boolean only |
| **When Evaluated** | After step creation, inside `performStep()` | Before step creation, in `canContinue()` |
| **Outcome Storage** | On each step | Not stored |
| **Debugging** | `step.continuationOutcome()` shows why stopped | Cannot inspect |
| **Serialization** | Outcomes persisted with steps | No outcome data |
| **Events** | `ContinuationEvaluated` event with full outcome | No continuation events |

---

## Design Decision: StepResult Wrapper

### The Problem with Current Agent Implementation

Current `performStep()` creates multiple copies of immutable objects:

```php
$rawStep = $this->makeNextStep($state);                    // Step created
$transitionState = $state->withNewStepRecorded($rawStep);           // State copy 1
$outcome = $this->continuationCriteria->evaluateAll($transitionState);
$completeStep = $rawStep->withContinuationOutcome($outcome); // Step copy 2
$nextState = $this->applyStep($state, $completeStep);       // State copy 2
```

This feels wrong because:
1. Step is modified after creation (violates "complete object" principle)
2. Multiple state copies for bookkeeping
3. Coupling is awkward - outcome "belongs to" step but added later

### Solution: StepResult Abstraction

```php
// packages/addons/src/StepByStep/Step/StepResult.php
final readonly class StepResult {
    public function __construct(
        public object $step,              // AgentStep, ChatStep, etc.
        public ContinuationOutcome $outcome,
    ) {}

    public function shouldContinue(): bool {
        return $this->outcome->shouldContinue();
    }

    public function stopReason(): ?string {
        return $this->outcome->stopReason();
    }
}
```

### Benefits of StepResult

1. **No modification of step** - created once, never copied
2. **Clear ownership** - result bundles step + outcome explicitly
3. **Consistent pattern** - all StepByStep consumers use same abstraction
4. **Simpler state** - stores results, not reconstructed steps
5. **Natural for serialization** - result is a complete unit

---

## Implementation Phases

### Phase 1: Create StepResult Wrapper Class

**Files to Create:**
- `packages/addons/src/StepByStep/Step/StepResult.php`

**Implementation:**
```php
<?php

declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\Step;

use Cognesy\Addons\StepByStep\Continuation\ContinuationOutcome;

final readonly class StepResult
{
    public function __construct(
        public object $step,
        public ContinuationOutcome $outcome,
    ) {}

    public function shouldContinue(): bool {
        return $this->outcome->shouldContinue();
    }

    public function stopReason(): ?string {
        return $this->outcome->stopReason();
    }
}
```

**Success Criteria:**
- [ ] StepResult class exists at specified path
- [ ] Class is readonly and immutable
- [ ] Constructor accepts step object and ContinuationOutcome
- [ ] `shouldContinue()` returns outcome's shouldContinue value
- [ ] `stopReason()` returns the stop reason from outcome

---

### Phase 2: Refactor Agent to Use StepResult

**Files to Modify:**
- `packages/addons/src/Agent/Core/Data/AgentStep.php` - Remove continuationOutcome property
- `packages/addons/src/Agent/Core/Data/AgentState.php` - Store StepResults, add accessors
- `packages/addons/src/Agent/Agent.php` - Update performStep() to create StepResult
- `packages/addons/src/Agent/Serialization/SlimAgentStateSerializer.php` - Handle StepResult serialization

**Implementation Details:**

1. **AgentStep changes:**
   - Remove `continuationOutcome` property
   - Remove `withContinuationOutcome()` method

2. **AgentState changes:**
   - Store `StepResult[]` instead of just steps
   - Add `lastStepResult(): ?StepResult`
   - Add `stepResults(): array`
   - Add `continuationOutcome(): ?ContinuationOutcome` (convenience)
   - Add `stopReason(): ?string` (convenience)
   - Update `recordStepResult(StepResult $result)` method

3. **Agent changes:**
   - Update `performStep()`:
     ```php
     protected function performStep(object $state): object {
         $step = $this->makeNextStep($state);
         $evalState = $state->withPendingStep($step);
         $outcome = $this->continuationCriteria->evaluateAll($evalState);
         $result = new StepResult($step, $outcome);
         return $this->applyStepResult($state, $result);
     }
     ```
   - Update `canContinue()`:
     ```php
     protected function canContinue(object $state): bool {
         $lastResult = $state->lastStepResult();
         return $lastResult?->shouldContinue() ?? true;
     }
     ```

**Success Criteria:**
- [ ] AgentStep no longer has continuationOutcome property
- [ ] AgentState stores and exposes StepResults
- [ ] `performStep()` creates StepResult after evaluation
- [ ] `canContinue()` reads from last StepResult
- [ ] Serialization preserves StepResults with outcomes
- [ ] All Agent tests pass

---

### Phase 3: Apply StepResult Pattern to ToolUse

**Files to Modify:**
- `packages/addons/src/ToolUse/Data/ToolUseStep.php` - Verify no outcome property needed
- `packages/addons/src/ToolUse/Data/ToolUseState.php` - Store StepResults, add accessors
- `packages/addons/src/ToolUse/ToolUse.php` - Override performStep(), update canContinue()

**Implementation Details:**

1. **ToolUseState changes:**
   - Add `stepResults` array property
   - Add `lastStepResult(): ?StepResult`
   - Add `stepResults(): array`
   - Add `continuationOutcome(): ?ContinuationOutcome`
   - Add `stopReason(): ?string`
   - Add `recordStepResult(StepResult $result)` or `withStepResult()`

2. **ToolUse changes:**
   - Override `performStep()` similar to Agent
   - Update `canContinue()` to read from lastStepResult

**Success Criteria:**
- [ ] ToolUseState stores StepResults
- [ ] `performStep()` creates and stores StepResult
- [ ] `canContinue()` reads from StepResult
- [ ] Continuation outcome accessible via state
- [ ] All ToolUse tests pass

---

### Phase 4: Apply StepResult Pattern to Chat

**Files to Modify:**
- `packages/addons/src/Chat/Data/ChatStep.php` - Verify no outcome property needed
- `packages/addons/src/Chat/Data/ChatState.php` - Store StepResults, add accessors
- `packages/addons/src/Chat/Chat.php` - Override performStep(), update canContinue()

**Implementation Details:**
Same pattern as ToolUse.

**Success Criteria:**
- [ ] ChatState stores StepResults
- [ ] `performStep()` creates and stores StepResult
- [ ] `canContinue()` reads from StepResult
- [ ] Continuation outcome accessible via state
- [ ] All Chat tests pass

---

### Phase 5: Apply StepResult Pattern to Collaboration

**Files to Modify:**
- `packages/addons/src/Collaboration/Data/CollaborationStep.php` - Verify no outcome property needed
- `packages/addons/src/Collaboration/Data/CollaborationState.php` - Store StepResults, add accessors
- `packages/addons/src/Collaboration/Collaboration.php` - Override performStep(), update canContinue()

**Implementation Details:**
Same pattern as ToolUse.

**Success Criteria:**
- [ ] CollaborationState stores StepResults
- [ ] `performStep()` creates and stores StepResult
- [ ] `canContinue()` reads from StepResult
- [ ] Continuation outcome accessible via state
- [ ] All Collaboration tests pass

---

### Phase 6: Verification and Integration Testing

**Verification Steps:**
1. Run full test suite for addons package
2. Verify Agent tests pass
3. Verify ToolUse tests pass
4. Verify Chat tests pass
5. Verify Collaboration tests pass
6. Test serialization roundtrip for each orchestrator
7. Verify API consistency

**Test Commands:**
```bash
./vendor/bin/pest packages/addons/tests/
./vendor/bin/pest packages/addons/tests/Unit/Agent/
./vendor/bin/pest packages/addons/tests/Unit/Tools/
./vendor/bin/pest packages/addons/tests/Unit/Chat/
./vendor/bin/pest packages/addons/tests/Unit/Collaboration/
```

**Success Criteria:**
- [ ] All existing tests pass
- [ ] No regressions introduced
- [ ] StepResult accessible on state for all orchestrators
- [ ] Continuation outcome available via StepResult
- [ ] Stop reason accessible when stopped
- [ ] Serialization works correctly

---

## Files Summary

### New Files
- `packages/addons/src/StepByStep/Step/StepResult.php`

### Modified Files
- `packages/addons/src/Agent/Core/Data/AgentStep.php`
- `packages/addons/src/Agent/Core/Data/AgentState.php`
- `packages/addons/src/Agent/Agent.php`
- `packages/addons/src/Agent/Serialization/SlimAgentStateSerializer.php`
- `packages/addons/src/ToolUse/Data/ToolUseState.php`
- `packages/addons/src/ToolUse/ToolUse.php`
- `packages/addons/src/Chat/Data/ChatState.php`
- `packages/addons/src/Chat/Chat.php`
- `packages/addons/src/Collaboration/Data/CollaborationState.php`
- `packages/addons/src/Collaboration/Collaboration.php`

---

## Related Issue Tracker

Epic: `instructor-br5`
- Phase 1: `instructor-br5.1`
- Phase 2: `instructor-br5.2`
- Phase 3: `instructor-br5.3`
- Phase 4: `instructor-br5.4`
- Phase 5: `instructor-br5.5`
- Phase 6: `instructor-br5.6`
