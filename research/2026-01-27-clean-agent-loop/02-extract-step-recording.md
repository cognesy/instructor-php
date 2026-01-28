# Extract Step Recording

## Current Problem

`AgentLoop::onAfterToolUse()` contains ~20 lines of step recording logic:

```php
private function onAfterToolUse(AgentState $state, AgentStep $rawStep): AgentState
{
    $currentExecution = $this->resolveCurrentExecution($state);
    $transitionState = $state->recordStep($rawStep);

    // Continuation evaluation
    $outcome = $this->continuationCriteria->evaluateAll($transitionState);
    $this->eventEmitter->continuationEvaluated($transitionState, $outcome);

    // StepExecution creation
    $stepExecution = new StepExecution(
        step: $rawStep,
        outcome: $outcome,
        startedAt: $currentExecution->startedAt,
        completedAt: new DateTimeImmutable(),
        stepNumber: $currentExecution->stepNumber,
        id: $rawStep->id(),
    );

    // State recording
    $nextState = $transitionState->recordStepExecution($stepExecution);
    $this->eventEmitter->stateUpdated($nextState);
    return $nextState;
}
```

Similarly, `onError()` has ~35 lines of error handling and step recording.

## Solution: CoreLifecycleObserver

Create an observer that handles core lifecycle operations:

```php
final class CoreLifecycleObserver implements CanObserveAgentLifecycle
{
    public function __construct(
        private readonly ContinuationCriteria $continuationCriteria,
        private readonly CanEmitAgentEvents $eventEmitter,
        private readonly CanHandleAgentErrors $errorHandler,
    ) {}

    public function onExecutionStart(AgentState $state): AgentState
    {
        $this->continuationCriteria->executionStarted(new DateTimeImmutable());
        $this->eventEmitter->executionStarted($state, $state->tools()->count());
        return $state;
    }

    public function onStepStart(AgentState $state): AgentState
    {
        $this->eventEmitter->stepStarted($state);
        return $state;
    }

    public function onStepEnd(AgentState $state): AgentState
    {
        // Step recording happens here
        $state = $this->recordStep($state);
        $this->eventEmitter->stepCompleted($state);
        return $state;
    }

    public function onError(AgentState $state): AgentState
    {
        $error = $state->currentError();
        $handling = $this->errorHandler->handleError($error, $state);

        $state = $this->recordFailureStep($state, $handling);

        if ($handling->finalStatus === AgentStatus::Failed) {
            $this->eventEmitter->executionFailed($state, $handling->exception);
        }

        return $state;
    }

    public function onExecutionEnd(AgentState $state): AgentState
    {
        $status = match ($state->stopReason()) {
            StopReason::ErrorForbade => AgentStatus::Failed,
            default => AgentStatus::Completed,
        };

        $state = $state->withStatus($status);
        $this->eventEmitter->executionFinished($state);
        return $state;
    }

    // Pass-through for methods this observer doesn't handle
    public function onBeforeToolUse(AgentState $state): AgentState
    {
        return $state;
    }

    public function onAfterToolUse(AgentState $state): AgentState
    {
        return $state;
    }

    public function onBeforeStop(AgentState $state): AgentState
    {
        return $state;
    }

    // PRIVATE ////////////////////////////////////////////////

    private function recordStep(AgentState $state): AgentState
    {
        $rawStep = $state->pendingStep();
        if ($rawStep === null) {
            return $state;
        }

        $currentExecution = $state->currentExecution();
        $transitionState = $state->recordStep($rawStep);

        $outcome = $this->continuationCriteria->evaluateAll($transitionState);
        $this->eventEmitter->continuationEvaluated($transitionState, $outcome);

        $stepExecution = new StepExecution(
            step: $rawStep,
            outcome: $outcome,
            startedAt: $currentExecution->startedAt,
            completedAt: new DateTimeImmutable(),
            stepNumber: $currentExecution->stepNumber,
            id: $rawStep->id(),
        );

        $nextState = $transitionState->recordStepExecution($stepExecution);
        $this->eventEmitter->stateUpdated($nextState);

        return $nextState;
    }

    private function recordFailureStep(AgentState $state, ErrorHandlingResult $handling): AgentState
    {
        $currentExecution = $state->currentExecution();

        $transitionState = $state
            ->withStatus(AgentStatus::Failed)
            ->recordStep($handling->failureStep);

        $this->eventEmitter->continuationEvaluated($transitionState, $handling->outcome);

        $stepExecution = new StepExecution(
            step: $handling->failureStep,
            outcome: $handling->outcome,
            startedAt: $currentExecution->startedAt,
            completedAt: new DateTimeImmutable(),
            stepNumber: $currentExecution->stepNumber,
            id: $handling->failureStep->id(),
        );

        return $transitionState
            ->withStatus($handling->finalStatus)
            ->recordStepExecution($stepExecution);
    }
}
```

## State Changes Required

Add `pendingStep` to transient execution data:

```php
final readonly class CurrentExecution
{
    public function __construct(
        public int $stepNumber,
        public DateTimeImmutable $startedAt,
        public string $id,
        public ?AgentStep $pendingStep = null,      // NEW
        public ?ToolCall $pendingToolCall = null,
        public ?ToolExecution $pendingExecution = null,
        public ?Throwable $error = null,            // NEW
        public ?StopReason $stopReason = null,
        public ?ExecutionDecision $decision = null,
    ) {}
}
```

Add convenience methods to `AgentState`:

```php
class AgentState
{
    public function withPendingStep(AgentStep $step): self {
        return $this->withCurrentExecution(
            $this->currentExecution->with(pendingStep: $step)
        );
    }

    public function pendingStep(): ?AgentStep {
        return $this->currentExecution?->pendingStep;
    }

    public function withError(Throwable $error): self {
        return $this->withCurrentExecution(
            $this->currentExecution->with(error: $error)
        );
    }

    public function currentError(): ?Throwable {
        return $this->currentExecution?->error;
    }
}
```

## Observer Composition

Compose `CoreLifecycleObserver` with `HookStackObserver`:

```php
final class CompositeObserver implements CanObserveAgentLifecycle
{
    /** @param CanObserveAgentLifecycle[] $observers */
    public function __construct(
        private readonly array $observers,
    ) {}

    public function onStepEnd(AgentState $state): AgentState
    {
        foreach ($this->observers as $observer) {
            $state = $observer->onStepEnd($state);
        }
        return $state;
    }

    // ... same pattern for all methods
}
```

In `AgentBuilder::build()`:

```php
$coreObserver = new CoreLifecycleObserver(
    $continuationCriteria,
    $eventEmitter,
    $errorHandler,
);

$hookObserver = new HookStackObserver($hookStack, $eventEmitter);

$observer = new CompositeObserver([$coreObserver, $hookObserver]);

return new AgentLoop(
    tools: $this->tools,
    driver: $driver,
    observer: $observer,
    // Note: errorHandler, continuationCriteria, eventEmitter
    // no longer needed as direct dependencies - they're in CoreLifecycleObserver
);
```

## Benefits

1. **Single Responsibility** - `AgentLoop` only orchestrates, `CoreLifecycleObserver` handles recording
2. **Testable in isolation** - Test step recording without running the full loop
3. **Swappable** - Could replace `CoreLifecycleObserver` with custom implementation
4. **Cleaner AgentLoop** - Lifecycle methods become one-liners
5. **Observer order matters** - Core observer runs first, hooks can modify the result
