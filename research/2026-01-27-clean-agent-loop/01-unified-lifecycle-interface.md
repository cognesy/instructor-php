# Unified Lifecycle Interface

## Current Interface (Non-Uniform)

```php
interface CanObserveAgentLifecycle
{
    // Uniform: (AgentState): AgentState
    public function beforeExecution(AgentState $state): AgentState;
    public function afterExecution(AgentState $state): AgentState;
    public function onError(AgentState $state, AgentException $exception): AgentState;
    public function beforeStep(AgentState $state): AgentState;
    public function afterStep(AgentState $state): AgentState;

    // Non-uniform: extra params, different return types
    public function beforeToolUse(ToolCall $toolCall, AgentState $state): ToolUseDecision;
    public function afterToolUse(ToolExecution $execution, AgentState $state): ToolExecution;
    public function beforeStopDecision(AgentState $state, StopReason $reason): StopDecision;
}
```

## Proposed Interface (Uniform)

```php
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

All methods: `(AgentState): AgentState`

## Transient Data in State

Extend `CurrentExecution` or create new `TransientExecution` to hold tool-level data:

```php
final readonly class CurrentExecution
{
    public function __construct(
        public int $stepNumber,
        public DateTimeImmutable $startedAt,
        public string $id,
        // NEW: Tool-level transient data
        public ?ToolCall $pendingToolCall = null,
        public ?ToolExecution $pendingExecution = null,
        public ?AgentException $error = null,
        public ?StopReason $stopReason = null,
        public ?ExecutionDecision $decision = null,
    ) {}
}
```

## Decision Encoding

Create an enum for execution decisions:

```php
enum ExecutionDecision: string
{
    case Proceed = 'proceed';
    case Block = 'block';
    case Stop = 'stop';
}
```

Add decision methods to `AgentState`:

```php
class AgentState
{
    public function withPendingToolCall(ToolCall $toolCall): self {
        return $this->withCurrentExecution(
            $this->currentExecution->with(pendingToolCall: $toolCall)
        );
    }

    public function withToolCallBlocked(string $reason): self {
        return $this->withCurrentExecution(
            $this->currentExecution->with(
                decision: ExecutionDecision::Block,
                // Could also store reason
            )
        );
    }

    public function isToolCallBlocked(): bool {
        return $this->currentExecution?->decision === ExecutionDecision::Block;
    }

    public function pendingToolCall(): ?ToolCall {
        return $this->currentExecution?->pendingToolCall;
    }

    public function withPendingExecution(ToolExecution $execution): self {
        return $this->withCurrentExecution(
            $this->currentExecution->with(pendingExecution: $execution)
        );
    }

    public function pendingExecution(): ?ToolExecution {
        return $this->currentExecution?->pendingExecution;
    }

    public function withStopPrevented(string $reason): self {
        return $this->withCurrentExecution(
            $this->currentExecution->with(decision: ExecutionDecision::Proceed)
        );
    }

    public function isStopPrevented(): bool {
        return $this->currentExecution?->decision === ExecutionDecision::Proceed;
    }
}
```

## Usage in AgentLoop

Before (current):
```php
private function useTools(AgentState $state): AgentState {
    // ...
    $decision = $this->observer->beforeToolUse($toolCall, $state);
    if ($decision->isBlocked()) {
        // handle block
    }
    $toolCall = $decision->toolCall();
    // ...
}
```

After (unified):
```php
private function useTools(AgentState $state): AgentState {
    // ...
    $state = $state->withPendingToolCall($toolCall);
    $state = $this->observer->onBeforeToolUse($state);

    if ($state->isToolCallBlocked()) {
        // handle block
        return $state;
    }

    $toolCall = $state->pendingToolCall();
    // ...
}
```

## Benefits

1. **Uniform contract** - All observers implement the same pattern
2. **Composable** - Easy to chain/wrap observers
3. **State is the single source of truth** - No separate decision objects
4. **Simpler testing** - Just verify state transformations
5. **Natural fit with hooks** - Hook contexts already wrap state, this aligns the patterns

## Alternative: Keep Decisions Separate

If we want to avoid polluting `AgentState` with decision logic, we could:

1. Return a tuple/result object: `(AgentState, ?Decision)`
2. Use a separate `ExecutionContext` that wraps state + transient data

But given `CurrentExecution` already exists for transient data, extending it is cleaner.
