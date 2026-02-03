# Simplified AgentLoop

## Current AgentLoop (328 lines)

The current `AgentLoop` has:
- ~40 lines of constructor + public API
- ~110 lines of lifecycle methods with implementation details
- ~25 lines of internal helpers
- ~70 lines of accessors/mutators
- ~30 lines of event delegation

## Proposed AgentLoop (~150 lines)

After extracting step recording to `CoreLifecycleObserver`:

```php
<?php declare(strict_types=1);

namespace Cognesy\Agents\Core;

use Cognesy\Agents\Core\Collections\Tools;use Cognesy\Agents\Core\Contracts\CanControlAgentLoop;use Cognesy\Agents\Core\Contracts\CanUseTools;use Cognesy\Agents\Core\Data\AgentState;use Cognesy\Agents\Core\Data\CurrentExecution;use Cognesy\Agents\Core\Enums\ExecutionStatus;use Cognesy\Agents\Lifecycle\CanObserveAgentLifecycle;use DateTimeImmutable;use Throwable;

/**
 * Minimal orchestration loop for agent execution.
 *
 * All implementation details (step recording, event emission, error handling)
 * are delegated to the observer. AgentLoop only manages the iteration sequence.
 */
class AgentLoop implements CanControlAgentLoop
{
    public function __construct(
        private readonly Tools $tools,
        private readonly CanUseTools $driver,
        private readonly CanObserveAgentLifecycle $observer,
    ) {}

    // PUBLIC API //////////////////////////////////

    #[\Override]
    public function execute(AgentState $state): AgentState
    {
        $finalState = $state;
        foreach ($this->iterate($state) as $stepState) {
            $finalState = $stepState;
        }
        return $finalState;
    }

    #[\Override]
    public function iterate(AgentState $state): iterable
    {
        $state = $this->observer->onExecutionStart($state);

        while (true) {
            try {
                $state = $this->beginStep($state);

                if ($this->shouldStop($state)) {
                    $state = $state->withClearedCurrentExecution();
                    break;
                }

                $state = $this->observer->onStepStart($state);
                $state = $this->performStep($state);
                $state = $this->observer->onStepEnd($state);

            } catch (\LogicException $error) {
                throw $error; // Programming errors propagate
            } catch (Throwable $error) {
                $state = $state->withError($error);
                $state = $this->observer->onError($state);
            }

            yield $state;
        }

        yield $this->observer->onExecutionEnd($state);
    }

    // INTERNAL ///////////////////////////////////////////

    private function beginStep(AgentState $state): AgentState
    {
        $stepNumber = $state->stepCount();
        return $state->withCurrentExecution(new CurrentExecution(
            stepNumber: $stepNumber,
            startedAt: new DateTimeImmutable(),
            id: uniqid('step_', true),
        ));
    }

    private function shouldStop(AgentState $state): bool
    {
        if ($state->status() === ExecutionStatus::Failed) {
            return true;
        }

        // First step always runs
        if ($state->stepCount() === 0) {
            return false;
        }

        // Check if previous step says to continue
        if ($state->stepExecutions()->shouldContinue()) {
            return false;
        }

        // About to stop - let observer intervene
        $state = $state->withStopReason($state->stopReason());
        $state = $this->observer->onBeforeStop($state);

        return !$state->isStopPrevented();
    }

    private function performStep(AgentState $state): AgentState
    {
        $rawStep = $this->driver->useTools(
            state: $state,
            tools: $this->tools,
            executor: $this, // AgentLoop implements tool execution via observer
        );

        return $state->withPendingStep($rawStep);
    }

    // ACCESSORS ////////////////////////////////////////////

    public function tools(): Tools
    {
        return $this->tools;
    }

    public function driver(): CanUseTools
    {
        return $this->driver;
    }

    public function observer(): CanObserveAgentLifecycle
    {
        return $this->observer;
    }

    // MUTATORS /////////////////////////////////////////////

    public function with(
        ?Tools $tools = null,
        ?CanUseTools $driver = null,
        ?CanObserveAgentLifecycle $observer = null,
    ): self {
        return new self(
            tools: $tools ?? $this->tools,
            driver: $driver ?? $this->driver,
            observer: $observer ?? $this->observer,
        );
    }
}
```

## Key Changes

### 1. Reduced Dependencies

Before:
```php
public function __construct(
    private readonly Tools $tools,
    private readonly CanExecuteToolCalls $toolExecutor,
    private readonly CanHandleAgentErrors $errorHandler,
    private readonly ContinuationCriteria $continuationCriteria,
    private readonly CanUseTools $driver,
    private readonly CanEmitAgentEvents $eventEmitter,
    private readonly ?CanObserveAgentLifecycle $observer = null,
) {}
```

After:
```php
public function __construct(
    private readonly Tools $tools,
    private readonly CanUseTools $driver,
    private readonly CanObserveAgentLifecycle $observer,
) {}
```

The `toolExecutor`, `errorHandler`, `continuationCriteria`, and `eventEmitter` are now internal to `CoreLifecycleObserver`.

### 2. Simplified Lifecycle Methods

Before `onAfterToolUse()` - 20 lines of step recording.

After - gone. The observer handles it in `onStepEnd()`.

### 3. Error Handling

Before:
```php
protected function onError(Throwable $error, AgentState $state): AgentState {
    $handling = $this->errorHandler->handleError($error, $state);
    // ... 30 more lines
}
```

After:
```php
} catch (Throwable $error) {
    $state = $state->withError($error);
    $state = $this->observer->onError($state);
}
```

### 4. Stop Decision

Before - mixed into `shouldContinue()` with observer intervention.

After - clean separation:
```php
private function shouldStop(AgentState $state): bool {
    // ... basic checks ...

    // Let observer intervene
    $state = $this->observer->onBeforeStop($state);
    return !$state->isStopPrevented();
}
```

## Tool Execution

Tool execution still needs the observer for before/after hooks. Two options:

### Option A: AgentLoop Implements CanExecuteToolCalls

```php
class AgentLoop implements CanControlAgentLoop, CanExecuteToolCalls
{
    public function executeToolCall(ToolCall $toolCall, AgentState $state): ToolExecution
    {
        $state = $state->withPendingToolCall($toolCall);
        $state = $this->observer->onBeforeToolUse($state);

        if ($state->isToolCallBlocked()) {
            return ToolExecution::blocked($toolCall, $state->blockReason());
        }

        $toolCall = $state->pendingToolCall(); // May be modified
        $execution = $this->actuallyExecuteTool($toolCall);

        $state = $state->withPendingExecution($execution);
        $state = $this->observer->onAfterToolUse($state);

        return $state->pendingExecution();
    }
}
```

### Option B: ToolExecutor Receives Observer

```php
class ToolExecutor implements CanExecuteToolCalls
{
    public function __construct(
        private readonly Tools $tools,
        private readonly CanObserveAgentLifecycle $observer,
    ) {}

    public function executeToolCall(ToolCall $toolCall, AgentState $state): ToolExecution
    {
        // Same pattern as Option A
    }
}
```

Option B is cleaner - keeps tool execution separate from the loop.

## Benefits

1. **~50% less code** in AgentLoop
2. **Single responsibility** - AgentLoop only iterates
3. **All behavior is observable** - Nothing hidden in private methods
4. **Easier to understand** - The loop logic is obvious
5. **Testable** - Mock the observer, verify interactions
6. **Extensible** - Swap observers for different behavior
