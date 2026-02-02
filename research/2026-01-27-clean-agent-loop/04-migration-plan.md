# Migration Plan

## Phase 1: Extend CurrentExecution

Add transient data fields without breaking existing code.

```php
final readonly class CurrentExecution
{
    public function __construct(
        public int $stepNumber,
        public DateTimeImmutable $startedAt,
        public string $id,
        // New fields with defaults
        public ?AgentStep $pendingStep = null,
        public ?ToolCall $pendingToolCall = null,
        public ?ToolExecution $pendingExecution = null,
        public ?Throwable $error = null,
        public ?StopReason $stopReason = null,
        public ?ExecutionDecision $decision = null,
        public ?string $decisionReason = null,
    ) {}

    public function with(
        ?int $stepNumber = null,
        ?DateTimeImmutable $startedAt = null,
        ?string $id = null,
        ?AgentStep $pendingStep = null,
        ?ToolCall $pendingToolCall = null,
        ?ToolExecution $pendingExecution = null,
        ?Throwable $error = null,
        ?StopReason $stopReason = null,
        ?ExecutionDecision $decision = null,
        ?string $decisionReason = null,
    ): self {
        return new self(
            stepNumber: $stepNumber ?? $this->stepNumber,
            startedAt: $startedAt ?? $this->startedAt,
            id: $id ?? $this->id,
            pendingStep: $pendingStep ?? $this->pendingStep,
            pendingToolCall: $pendingToolCall ?? $this->pendingToolCall,
            pendingExecution: $pendingExecution ?? $this->pendingExecution,
            error: $error ?? $this->error,
            stopReason: $stopReason ?? $this->stopReason,
            decision: $decision ?? $this->decision,
            decisionReason: $decisionReason ?? $this->decisionReason,
        );
    }
}
```

**Tests:** Existing tests should pass unchanged.

## Phase 2: Add AgentState Convenience Methods

```php
// In AgentState class

public function withPendingStep(AgentStep $step): self {
    return $this->withCurrentExecution(
        $this->currentExecution?->with(pendingStep: $step)
            ?? throw new \LogicException('No current execution')
    );
}

public function pendingStep(): ?AgentStep {
    return $this->currentExecution?->pendingStep;
}

public function withPendingToolCall(ToolCall $toolCall): self {
    return $this->withCurrentExecution(
        $this->currentExecution?->with(pendingToolCall: $toolCall)
            ?? throw new \LogicException('No current execution')
    );
}

public function pendingToolCall(): ?ToolCall {
    return $this->currentExecution?->pendingToolCall;
}

public function withPendingExecution(ToolExecution $execution): self {
    return $this->withCurrentExecution(
        $this->currentExecution?->with(pendingExecution: $execution)
            ?? throw new \LogicException('No current execution')
    );
}

public function pendingExecution(): ?ToolExecution {
    return $this->currentExecution?->pendingExecution;
}

public function withError(Throwable $error): self {
    return $this->withCurrentExecution(
        $this->currentExecution?->with(error: $error)
            ?? throw new \LogicException('No current execution')
    );
}

public function currentError(): ?Throwable {
    return $this->currentExecution?->error;
}

public function withDecision(ExecutionDecision $decision, ?string $reason = null): self {
    return $this->withCurrentExecution(
        $this->currentExecution?->with(decision: $decision, decisionReason: $reason)
            ?? throw new \LogicException('No current execution')
    );
}

public function isToolCallBlocked(): bool {
    return $this->currentExecution?->decision === ExecutionDecision::Block;
}

public function isStopPrevented(): bool {
    return $this->currentExecution?->decision === ExecutionDecision::Proceed;
}

public function decisionReason(): ?string {
    return $this->currentExecution?->decisionReason;
}
```

**Tests:** Add unit tests for new methods.

## Phase 3: Create ExecutionDecision Enum

```php
<?php declare(strict_types=1);

namespace Cognesy\Agents\Core\Enums;

enum ExecutionDecision: string
{
    case Proceed = 'proceed';
    case Block = 'block';
    case Stop = 'stop';
}
```

## Phase 4: Update CanObserveAgentLifecycle Interface

Create new unified interface alongside old one:

```php
<?php declare(strict_types=1);

namespace Cognesy\Agents\Core\Lifecycle;

use Cognesy\Agents\Core\Data\AgentState;

interface CanObserveAgentLifecycle
{
    public function onExecutionStart(AgentState $state): AgentState;
    public function onExecutionEnd(AgentState $state): AgentState;
    public function onError(AgentState $state): AgentState;
    public function onStepStart(AgentState $state): AgentState;
    public function onStepEnd(AgentState $state): AgentState;
    public function onBeforeToolUse(AgentState $state): AgentState;
    public function onAfterToolUse(AgentState $state): AgentState;
    public function onBeforeStop(AgentState $state): AgentState;
}
```

**Option:** Keep old interface temporarily as `LegacyObserver` for migration.

## Phase 5: Create CoreLifecycleObserver

Implement the new observer with extracted step recording logic.

```php
<?php declare(strict_types=1);

namespace Cognesy\Agents\Core\Lifecycle;

// ... implementation from 02-extract-step-recording.md
```

**Tests:** Copy existing AgentLoop tests, adapt to test CoreLifecycleObserver.

## Phase 6: Update HookStackObserver

Update to implement new unified interface.

```php
// Before
public function beforeToolUse(ToolCall $toolCall, AgentState $state): ToolUseDecision

// After
public function onBeforeToolUse(AgentState $state): AgentState
{
    $toolCall = $state->pendingToolCall();
    // ... run hooks ...
    if ($outcome->isBlocked()) {
        return $state->withDecision(ExecutionDecision::Block, $outcome->reason());
    }
    // Potentially modified tool call
    $modifiedToolCall = $this->extractToolCall($outcome, $toolCall);
    return $state->withPendingToolCall($modifiedToolCall);
}
```

## Phase 7: Create CompositeObserver

```php
<?php declare(strict_types=1);

namespace Cognesy\Agents\Core\Lifecycle;

use Cognesy\Agents\Core\Data\AgentState;use Cognesy\Agents\Lifecycle\CanObserveAgentLifecycle;

final class CompositeObserver implements CanObserveAgentLifecycle
{
    /** @param CanObserveAgentLifecycle[] $observers */
    public function __construct(
        private readonly array $observers,
    ) {}

    public function onExecutionStart(AgentState $state): AgentState
    {
        foreach ($this->observers as $observer) {
            $state = $observer->onExecutionStart($state);
        }
        return $state;
    }

    // ... same for all methods
}
```

## Phase 8: Simplify AgentLoop

Refactor AgentLoop to use observer for all implementation details.

**Tests:** Existing AgentLoop tests should pass.

## Phase 9: Update AgentBuilder

```php
public function build(): AgentLoop
{
    // ... existing setup ...

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
    );
}
```

## Phase 10: Cleanup

1. Remove old `ToolUseDecision`, `StopDecision` classes (if no longer needed)
2. Remove deprecated methods
3. Update documentation

## Risk Mitigation

1. **Keep tests green** at each phase
2. **Feature flags** if needed for gradual rollout
3. **Parallel implementations** - new observer can wrap old until migration complete
4. **Backward compatibility layer** - adapter from old interface to new

## Estimated Effort

- Phase 1-3: ~2 hours (data structures)
- Phase 4-5: ~4 hours (core observer)
- Phase 6-7: ~2 hours (hook observer + composite)
- Phase 8-9: ~2 hours (AgentLoop + builder)
- Phase 10: ~1 hour (cleanup)

**Total: ~11 hours**
