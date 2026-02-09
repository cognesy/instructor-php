# Stop Conditions

The agent loop stops when `ExecutionState::shouldStop()` returns true. This is controlled by `ExecutionContinuation`, `StopSignals`, and `AgentStopException`.

## Default Stop Logic

The loop stops when:
1. A `StopSignal` is present **and** no continuation is requested, OR
2. There are no pending tool calls (LLM gave a final response)

```php
// ExecutionState::shouldStop()
public function shouldStop(): bool {
    return match(true) {
        $this->continuation->shouldStop() => true,       // stop signal present
        $this->continuation->isContinuationRequested() => false, // override
        $this->hasToolCalls() => false,                  // more tools to run
        default => true,                                 // no tools = done
    };
}
```

## StopSignal

A signal requesting the loop to stop, with a reason and context:

```php
use Cognesy\Agents\Core\Stop\StopSignal;
use Cognesy\Agents\Core\Stop\StopReason;

$signal = new StopSignal(
    reason: StopReason::StepsLimitReached,
    message: 'Step limit reached: 10/10',
    context: ['currentSteps' => 10, 'maxSteps' => 10],
    source: MyGuard::class,
);
```

## StopReason

```
Completed           - Normal completion
StepsLimitReached   - Step budget exhausted
TokenLimitReached   - Token budget exhausted
TimeLimitReached    - Time budget exhausted
RetryLimitReached   - Max retries exceeded
ErrorForbade        - Error prevented continuation
StopRequested       - Explicit stop via AgentStopException
FinishReasonReceived - LLM finish reason matched
UserRequested       - User-initiated stop
```

## AgentStopException

Throw from a tool to immediately stop the loop:

```php
use Cognesy\Agents\Core\Stop\AgentStopException;

class StopTool extends BaseTool
{
    public function __invoke(): never
    {
        throw new AgentStopException(
            signal: new StopSignal(
                reason: StopReason::UserRequested,
                message: 'Task complete',
            ),
        );
    }
}
```

## ExecutionContinuation

Manages the interplay between stop signals and continuation requests:

- `shouldStop()` - true if signals exist and no continuation requested
- `isContinuationRequested()` - true if a hook requested continuation
- Hooks can override stop signals by calling `$state->withExecutionContinued()`

## Emitting Stop Signals from Hooks

Guard hooks emit stop signals by modifying state:

```php
$state = $context->state()->withStopSignal(new StopSignal(
    reason: StopReason::StepsLimitReached,
    message: 'Limit reached',
));
return $context->withState($state);
```

The loop checks `shouldStop()` after each step and breaks if true.
