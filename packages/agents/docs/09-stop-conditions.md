---
title: 'Stop Conditions'
description: 'Control when the agent loop stops using stop signals, continuation logic, and exceptions'
---

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
        $this->continuation->shouldStop() => true,       // stop signal present AND no continuation override
        $this->continuation->isContinuationRequested() => false, // continuation override active
        $this->hasToolCalls() => false,                  // more tools to run
        default => true,                                 // no tools = done
    };
}
```

## StopSignal

A signal requesting the loop to stop, with a reason and context:

```php
use Cognesy\Agents\Continuation\StopReason;
use Cognesy\Agents\Continuation\StopSignal;

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
Unknown             - Unclassified stop reason
```

## AgentStopException

Throw from a tool to immediately stop the loop. The loop catches this exception and converts it to a `StopSignal` with reason `StopRequested` via `StopSignal::fromStopException()`:

```php
use Cognesy\Agents\Continuation\AgentStopException;
use Cognesy\Agents\Continuation\StopReason;
use Cognesy\Agents\Continuation\StopSignal;

class StopTool extends BaseTool
{
    public function __invoke(): never
    {
        throw new AgentStopException(
            signal: new StopSignal(
                reason: StopReason::StopRequested,
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
