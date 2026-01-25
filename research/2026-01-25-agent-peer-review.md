# Peer Review: `packages/agents/src/Agent`

**Date**: 2026-01-25
**Scope**: `packages/agents/src/Agent` directory
**Context**: Post-refactoring review (StepResult → StepExecution)

---

## Executive Summary

The refactoring from `StepResult` → `StepExecution` is well-executed. The architecture follows clean immutable patterns with clear separation of concerns. However, I've identified several issues, inconsistencies, and opportunities for improvement.

---

## 🔴 Critical Issues

### 1. ID Correlation is Confusing and Potentially Unnecessary

**Location**: `Agent.php:221-234`

```php
private function correlateStepWithExecution(AgentStep $step, CurrentExecution $execution): AgentStep {
    if ($step->id() === $execution->id) {
        return $step;
    }
    return new AgentStep(/* ... */, id: $execution->id);  // OVERWRITES step's ID!
}
```

**Problem**: The `ToolCallingDriver` creates an `AgentStep` with its own UUID at `ToolCallingDriver.php:165`. Then the orchestrator *replaces* this ID with the `CurrentExecution->id`. This is confusing and wasteful:
- Two UUIDs are generated per step (one discarded)
- The step's "identity" is overwritten silently
- No documentation explains why this is necessary

**Question**: Should the driver receive the `CurrentExecution->id` and use it when creating the step? Or should `CurrentExecution` be created *from* the step's ID after it's returned?

---

### 2. Timing Gap Between CurrentExecution and StepExecution

**Location**: `Agent.php:100-104` and `Agent.php:120-127`

```php
// onBeforeStep (line 101)
$state = $state->beginStepExecution();  // Creates CurrentExecution with startedAt = now

// Much later in onAfterToolUse (line 124)
completedAt: new DateTimeImmutable(),  // Creates completedAt = now
```

**Problem**: The `startedAt` is captured in `onBeforeStep`, but the actual inference + tool execution happens in `performStep`. If processors do significant work before/after `useTools`, the timing will include processor time, not just the step time.

**Recommendation**: Consider capturing `startedAt` at the beginning of `useTools()` for more accurate step timing.

---

### 3. Error Fallback Logic is Implicit

**Location**: `AgentStep.php:45-49`

```php
$providedErrors = $errors ?? ErrorList::empty();
$this->errors = match (true) {
    $providedErrors->hasAny() => $providedErrors,
    default => $this->toolExecutions->errors(),  // Silent fallback!
};
```

**Problem**: If explicit errors are provided, tool execution errors are **silently ignored**. If no explicit errors, tool errors are used. This "either/or" logic could hide important errors.

**Recommendation**: Merge both error sources:
```php
$this->errors = $providedErrors->withAppended(...$this->toolExecutions->errors()->all());
```

---

## 🟡 Design Issues

### 4. Timestamp Naming Inconsistency

**Location**: `ToolExecution.php:43`

```php
completedAt: self::parseDate($data['completedAt'] ?? $data['endedAt'] ?? null),
```

The code accepts `endedAt` for backward compatibility but the canonical name is `completedAt`. This creates:
- Serialization inconsistency (outputs `completedAt`, accepts both)
- No deprecation warning or migration path
- Confusion about which name is correct

**Recommendation**: Add `@deprecated` comment or migration notice.

---

### 5. DATE_ATOM vs DateTimeImmutable::ATOM Inconsistency

| File | Format Used |
|------|-------------|
| `CurrentExecution.php:27` | `DATE_ATOM` |
| `StepExecution.php:69-70` | `DateTimeImmutable::ATOM` |
| `ToolExecution.php:131-132` | `DateTimeImmutable::ATOM` |
| `AgentState.php:454-455` | `DATE_ATOM` |

These are functionally identical but inconsistent usage suggests different authors or no standard.

---

### 6. Status Derivation is Complex and Stateful

**Location**: `AgentState.php:313-330`

```php
public function status(): AgentStatus {
    if ($this->status === AgentStatus::Failed) {
        return AgentStatus::Failed;  // Explicit failure wins
    }
    $outcome = $this->continuationOutcome();
    if ($outcome === null || $outcome->shouldContinue()) {
        return $this->status;  // Stored status
    }
    return match ($outcome->stopReason()) {
        StopReason::ErrorForbade => AgentStatus::Failed,
        default => AgentStatus::Completed,
    };
}
```

**Problem**: Status is partially stored, partially derived. This creates:
- Confusion about what `$this->status` actually represents
- Different behavior depending on whether `continuationOutcome` exists
- Potential bugs if someone checks `$state->status()` at wrong time

**Recommendation**: Either:
- Make status fully derived (remove stored `$status`)
- Or finalize status explicitly and never derive

---

### 7. transientStepCount vs stepCount Confusion

**Location**: `AgentState.php:339-348`

```php
public function transientStepCount(): int {
    if ($this->isCurrentStepRecorded($currentStep)) {
        return $this->stepCount();
    }
    return $this->stepCount() + 1;
}
```

**Problem**: Two methods for counting steps, depending on whether you want the "in-progress" count. This is confusing for callers.

**Question**: Is `transientStepCount` used anywhere? If only by continuation criteria, consider passing the count explicitly rather than having two methods.

---

### 8. resolveCurrentExecution Creates Fallback Object

**Location**: `Agent.php:213-218`

```php
private function resolveCurrentExecution(AgentState $state): CurrentExecution {
    if ($currentExecution !== null) {
        return $currentExecution;
    }
    return new CurrentExecution(stepNumber: $state->stepCount() + 1);
}
```

**Problem**: If `currentExecution` is null (shouldn't happen in normal flow), a new one is created with `startedAt = now`. This means the step duration would be near-zero instead of reflecting actual time.

This is a defensive fallback, but it masks bugs. If this branch is ever hit in production, timing data is corrupted.

**Recommendation**: Consider throwing an exception or logging a warning instead of silently creating a fallback.

---

### 9. SlimAgentStateSerializer Loses StepExecutions Data

**Location**: `SlimAgentStateSerializer.php:42-67`

The `deserialize` method doesn't restore `stepExecutions`, only basic state. This is intentional (it's "slim"), but:
- Not documented that round-trip loses data
- `serialize()` includes steps but `deserialize()` ignores them

---

### 10. ToolExecution.toArray() Missing `id` Field

**Location**: `ToolExecution.php:118-134`

```php
public function toArray(): array {
    return [
        'toolCall' => [...],
        'tool' => ...,
        'args' => ...,
        'result' => ...,
        'error' => ...,
        'startedAt' => ...,
        'completedAt' => ...,
        // Missing: 'id' => $this->id !!
    ];
}
```

The `id` is accepted in `fromArray()` but not output in `toArray()`. This breaks round-trip serialization.

---

## 🟢 Positive Observations

1. **Immutability is consistent** - All data classes are `final readonly` with proper `with()` methods
2. **Clean separation** - Agent orchestrates, drivers execute, criteria evaluate
3. **Good error handling** - `ErrorHandlingResult` properly bundles failure information
4. **Flexible serialization** - Multiple serializer options for different use cases
5. **Event system** - Clean event emission at lifecycle points

---

## 📋 Recommended Next Steps

1. **Fix ToolExecution.toArray()** - Add `'id'` field (bug)
2. **Document ID correlation strategy** - Explain why step IDs are replaced
3. **Standardize timestamp naming** - Pick `completedAt` and deprecate `endedAt`
4. **Standardize date format constant** - Use `DateTimeImmutable::ATOM` everywhere
5. **Consider merging errors** in AgentStep instead of "either/or"
6. **Add tests for timing edge cases** - Ensure step duration is captured correctly
7. **Document status derivation** - Clarify when status is stored vs derived
8. **Review SlimAgentStateSerializer** - Document data loss in round-trip

---

## Questions for the Team

1. Should `CurrentExecution->id` be created from `AgentStep->id()` rather than the other way around?
2. Is `transientStepCount()` actually needed, or can we pass counts explicitly?
3. Should `resolveCurrentExecution()` throw instead of creating a fallback?
4. What's the intended behavior when both explicit errors AND tool errors exist?
