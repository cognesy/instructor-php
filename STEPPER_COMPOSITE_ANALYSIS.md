# Composite Stepper Analysis

## Executive Summary

This analysis explores using Stepper as a **composite object** within Chat and ToolUse to handle standardized mechanisms while preserving domain-specific logic. The analysis reveals **limited value** in this approach given the current architecture.

## Current Orchestration Patterns

### Common Patterns (Extractable)

Both Chat and ToolUse share these patterns:

```php
// 1. Iteration control
while ($this->hasNextStep($state)) {
    $state = $this->nextStep($state);
}

// 2. Continuation checking
$this->continuationCriteria->canContinue($state)

// 3. State updates
$state->withAddedStep($step)->withCurrentStep($step)

// 4. Processor application
$this->stepProcessors->apply($state)

// 5. Iterator implementation
foreach ($this->iterator($state) as $newState) { ... }
```

### Domain-Specific Logic (Not Extractable)

**Chat-specific**:
- Participant selection: `selectParticipant($state)`
- Participant execution: `participant->act($state)`
- Chat-specific events: `ChatTurnStarting`, `ChatParticipantSelected`, etc.

**ToolUse-specific**:
- Tool execution: `driver->useTools($state, $tools, $toolExecutor)`
- Tool-specific error handling
- Tool-specific events: `ToolUseStepStarted`, etc.

## Composite Stepper Design Options

### Option 1: Iterator Stepper (Minimal Value)

```php
// Core/IteratorStepper.php
class IteratorStepper<TState extends HasSteps>
{
    public function __construct(
        private ContinuationCriteria $criteria
    ) {}

    public function hasNext(TState $state): bool {
        return $this->criteria->canContinue($state);
    }

    public function iterate(TState $state, callable $stepExecutor): Generator {
        while ($this->hasNext($state)) {
            $state = $stepExecutor($state);
            yield $state;
        }
    }

    public function final(TState $state, callable $stepExecutor): TState {
        while ($this->hasNext($state)) {
            $state = $stepExecutor($state);
        }
        return $state;
    }
}

// Usage in ToolUse
class ToolUse {
    private IteratorStepper $stepper;

    public function iterator(ToolUseState $state): iterable {
        return $this->stepper->iterate($state, fn($s) => $this->nextStep($s));
    }

    public function finalStep(ToolUseState $state): ToolUseState {
        return $this->stepper->final($state, fn($s) => $this->nextStep($s));
    }
}
```

**Problems**:
- Saves only 3-4 lines per method
- Adds indirection without clear benefit
- Still need to implement `nextStep()` with all complexity

### Option 2: State Update Stepper (Marginal Value)

```php
// Core/StateUpdateStepper.php
class StateUpdateStepper<TState extends HasSteps, TStep>
{
    public function __construct(
        private CanApplyProcessors $processors
    ) {}

    public function updateState(TState $state, TStep $step): TState {
        $newState = $state->withAddedStep($step)->withCurrentStep($step);
        return $this->processors->apply($newState);
    }
}

// Usage in Chat
class Chat {
    private StateUpdateStepper $stepper;

    private function updateState(ChatStep $step, ChatState $state): ChatState {
        $newState = $this->stepper->updateState($state, $step);
        $this->emitChatStateUpdated($newState, $state);
        return $newState;
    }
}
```

**Problems**:
- Only extracts 2 lines of code
- Events still need custom handling
- Minimal complexity reduction

### Option 3: Orchestration Stepper (Moderate Value)

```php
// Core/OrchestrationStepper.php
class OrchestrationStepper<TState extends HasSteps, TStep>
{
    public function __construct(
        private ContinuationCriteria $criteria,
        private CanApplyProcessors $processors,
        private ?CanHandleEvents $events = null,
    ) {}

    public function executeStep(
        TState $state,
        callable $stepProducer,
        callable $errorHandler,
        array $eventCallbacks = []
    ): TState {
        if (!$this->canContinue($state)) {
            $eventCallbacks['finished']?.($state);
            return $state;
        }

        $eventCallbacks['started']?.($state);

        try {
            $step = $stepProducer($state);
        } catch (Throwable $error) {
            $step = $errorHandler($state, $error);
        }

        $newState = $state->withAddedStep($step)->withCurrentStep($step);
        $newState = $this->processors->apply($newState);

        $eventCallbacks['completed']?.($newState);

        return $newState;
    }

    public function canContinue(TState $state): bool {
        return $this->criteria->canContinue($state);
    }
}

// Usage in ToolUse
class ToolUse {
    private OrchestrationStepper $stepper;

    public function nextStep(ToolUseState $state): ToolUseState {
        return $this->stepper->executeStep(
            state: $state,
            stepProducer: fn($s) => $this->driver->useTools($s, $this->tools, $this->toolExecutor),
            errorHandler: fn($s, $e) => ToolUseStep::failure($s->messages(), $e),
            eventCallbacks: [
                'started' => fn($s) => $this->emitToolUseStepStarted($s),
                'completed' => fn($s) => $this->emitToolUseStepCompleted($s),
                'finished' => fn($s) => $this->emitToolUseFinished($s),
            ]
        );
    }
}
```

**Trade-offs**:
- ✅ Centralizes error handling and state updates
- ✅ Reduces code duplication (~20 lines)
- ❌ Complex callback structure
- ❌ Less readable than current implementation
- ❌ Harder to debug

## Quantitative Analysis

### Current Code Complexity

| Component | Total LOC | Orchestration LOC | Domain LOC | % Orchestration |
|-----------|-----------|------------------|------------|-----------------|
| Chat | ~150 | ~30 | ~120 | 20% |
| ToolUse | ~190 | ~40 | ~150 | 21% |

### Potential Savings with Composite Stepper

| Approach | Lines Saved | Complexity Change | Risk |
|----------|-------------|-------------------|------|
| Iterator Stepper | 8-10 | +5% (indirection) | Low |
| State Update Stepper | 4-6 | +3% (indirection) | Low |
| Orchestration Stepper | 20-25 | +15% (callbacks) | Medium |

## Benefits vs. Drawbacks

### Potential Benefits

1. **Code Reuse** - Save 10-25 lines per component
2. **Consistency** - Ensure identical iteration patterns
3. **Single Point of Change** - Fix bugs in one place

### Significant Drawbacks

1. **Increased Indirection** - Makes code harder to follow
2. **Callback Complexity** - Event handling becomes convoluted
3. **Type Safety Loss** - Callbacks reduce type checking benefits
4. **Debugging Difficulty** - Stack traces become less clear
5. **Limited Savings** - Only 20% of code is orchestration
6. **Domain Logic Dominates** - 80% remains component-specific

## Real-World Example: Why It Doesn't Help

Consider the current `Chat.nextStep()`:

```php
public function nextStep(ChatState $state): ChatState {
    if (!$this->hasNextStep($state)) {                    // Common (2 lines)
        $this->emitChatCompleted($state);                  // Domain-specific
        return $state;
    }

    $this->emitChatTurnStarting($state);                   // Domain-specific
    $participant = $this->selectParticipant($state);       // Domain-specific
    $this->emitChatBeforeSend($participant, $state);       // Domain-specific

    try {
        $nextStep = $participant->act($state);             // Domain-specific
    } catch (Throwable $error) {                           // Common pattern
        $failureStep = ChatStep::failure(                  // Domain-specific factory
            participantName: $participant->name(),
            inputMessages: $state->messages(),
            error: $error,
        );
        $newState = $this->updateState($failureStep, $state); // Partially common
        $this->emitChatTurnCompleted($newState);           // Domain-specific
        return $newState;
    }

    $newState = $this->updateState($nextStep, $state);    // Partially common
    $this->emitChatTurnCompleted($newState);              // Domain-specific
    return $newState;
}
```

**Analysis**: Only ~6 lines are truly generic. The rest is domain logic that MUST remain in Chat.

## Recommendation: Don't Do It

### Why Not

1. **Insufficient Commonality** - Only 20% of code is shareable orchestration
2. **Complexity Increase** - Callbacks and indirection harm readability
3. **Marginal Benefits** - Saving 20 lines doesn't justify the complexity
4. **Current Design is Good** - Clear, explicit, easy to debug
5. **YAGNI Principle** - No third component needs this abstraction yet

### What You Already Have is Better

The current architecture with:
- Shared state base classes
- Common processors
- Unified continuation criteria
- Consistent error handling

Provides the **right level of abstraction** without over-engineering.

### Alternative: Documentation Pattern

Instead of code extraction, document the **orchestration pattern**:

```php
/**
 * Standard Orchestration Pattern:
 * 1. Check continuation
 * 2. Emit start event
 * 3. Execute domain logic (with error handling)
 * 4. Update state
 * 5. Apply processors
 * 6. Emit completion event
 */
```

This ensures consistency without unnecessary abstraction.

## Conclusion

**Composite Stepper offers limited value** for the current codebase. The orchestration logic represents only ~20% of each component, and the domain-specific logic is too intertwined to extract cleanly. The current architecture strikes the right balance between reuse and clarity.

**Recommendation**: Keep the current design. It's more maintainable, easier to understand, and the duplication is minimal and acceptable.