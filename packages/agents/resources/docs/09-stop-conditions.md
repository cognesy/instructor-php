---
title: 'Stop Conditions'
description: 'Control when the agent loop stops using stop signals, continuation logic, and exceptions'
---

# Stop Conditions

## Introduction

The agent loop runs iteratively -- calling the model, executing tools, and repeating -- until something tells it to stop. Understanding the stop condition system is essential for building predictable agents that terminate gracefully under all circumstances.

Three mechanisms work together to control loop termination: **stop signals** emitted by guards or tools, **continuation overrides** that can suppress those signals, and the **AgentStopException** for immediate termination from within tool code.

<a name="default-stop-logic"></a>
## How the Loop Decides to Stop

At the end of each iteration, the loop evaluates `ExecutionState::shouldStop()`. The decision follows this priority chain:

```php
$shouldStop = match (true) {
    $continuation->shouldStop() => true,              // stop signal AND no continuation override
    $continuation->isContinuationRequested() => false, // continuation override active
    $hasToolCalls => false,                            // model requested more tool calls
    default => true,                                   // no tool calls = conversation complete
};
```

In plain terms:

1. If a stop signal has been emitted **and** no continuation override is active, the loop stops immediately.
2. If a continuation override is active, the loop continues regardless of stop signals.
3. If the model returned tool calls, the loop continues to execute them.
4. If none of the above apply (the model gave a final text response with no tool calls), the loop stops -- this is the normal completion path.

<a name="stop-signals"></a>
## Stop Signals

A `StopSignal` is an immutable value object that represents a structured request to terminate the loop. It carries a reason, a human-readable message, contextual data for debugging, and the class name of the source that created it:

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

| Property | Type | Description |
|----------|------|-------------|
| `reason` | `StopReason` | An enum value categorizing why the stop was requested |
| `message` | `string` | A human-readable description of the stop condition |
| `context` | `array` | Arbitrary diagnostic data (thresholds, counters, timestamps) for debugging and logging |
| `source` | `?string` | The fully-qualified class name of the hook or component that emitted the signal |

Signals accumulate in a `StopSignals` collection within `ExecutionContinuation`. Multiple signals can coexist — for instance, both a step limit and a token limit might trigger in the same iteration. Use `highest()` to retrieve the most authoritative signal by priority, or `first()` for the earliest-added signal.

### Displaying and Serializing Signals

Signals provide methods for display and persistence:

```php
// Human-readable string
$signal->toString();
// e.g., "steps_limit: Step limit reached: 10/10"

// Full serialization
$signal->toArray();
// ['reason' => 'steps_limit', 'message' => '...', 'context' => [...], 'source' => '...']

// Restore from serialized data
$restored = StopSignal::fromArray($data);
```

### Factory Methods

`StopSignal` provides static factories for common signal types so you don't have to construct them manually:

```php
// User-requested cancellation
$signal = StopSignal::userRequested('user pressed stop', context: ['source' => 'ui'], source: self::class);

// From a caught AgentStopException (used internally by the loop)
$signal = StopSignal::fromStopException($exception);
```

### Creating Signals from Exceptions

When an `AgentStopException` is caught by the loop, the exception is converted to a `StopSignal` using the dedicated factory method:

```php
$signal = StopSignal::fromStopException($exception);
// Creates a signal with reason StopRequested and the exception's message/context
```

### Emitting Stop Signals from Hooks

Guard hooks are the primary source of stop signals. A hook emits a signal by modifying the agent state and returning the updated context:

```php
use Cognesy\Agents\Hook\Contracts\HookInterface;
use Cognesy\Agents\Hook\Data\HookContext;

class CustomGuard implements HookInterface
{
    public function handle(HookContext $context): HookContext
    {
        if ($this->shouldStop($context->state())) {
            $state = $context->state()->withStopSignal(new StopSignal(
                reason: StopReason::StepsLimitReached,
                message: 'Custom condition met',
                source: self::class,
            ));
            return $context->withState($state);
        }

        return $context;
    }
}
```

The `withStopSignal()` method on `AgentState` appends the signal to the execution's `ExecutionContinuation` state. The loop checks `shouldStop()` after processing hooks at the end of each step.

<a name="stop-signals-collection"></a>
### The StopSignals Collection

Multiple stop signals can accumulate during execution. The `StopSignals` collection is an immutable container that manages them:

```php
use Cognesy\Agents\Continuation\StopSignals;

$signals = StopSignals::empty();
$signals = $signals->withSignal($stepLimitSignal);
$signals = $signals->withSignal($tokenLimitSignal);

$signals->hasAny();     // true
$signals->first();      // Returns the first signal added (insertion order)
$signals->highest();    // Returns the most authoritative signal by priority
$signals->toString();   // "steps_limit: Step limit reached: 10/10 | token_limit: Token limit reached"
```

Each `withSignal()` call returns a new instance. The collection supports full serialization through `toArray()` and `fromArray()`.

<a name="stop-reason"></a>
## StopReason

The `StopReason` enum categorizes every possible reason for stopping the agent loop. Each reason has a string value for serialization and a numeric priority for comparison:

| Reason | Value | Priority | Description |
|--------|-------|----------|-------------|
| `ErrorForbade` | `error` | 0 (highest) | An error prevented continuation |
| `StopRequested` | `stop_requested` | 1 | Explicit stop via `AgentStopException` |
| `StepsLimitReached` | `steps_limit` | 2 | Step budget exhausted |
| `TokenLimitReached` | `token_limit` | 3 | Token budget exhausted |
| `TimeLimitReached` | `time_limit` | 4 | Wall-clock time budget exhausted |
| `RetryLimitReached` | `retry_limit` | 5 | Maximum retries exceeded |
| `FinishReasonReceived` | `finish_reason` | 6 | LLM finish reason matched a stop condition |
| `UserRequested` | `user_requested` | 2 | External cancellation requested by the caller |
| `Completed` | `completed` | 8 | Normal, successful completion |
| `Unknown` | `unknown` | 9 (lowest) | Unclassified stop reason |

### Priority and Comparison

Each `StopReason` has a numeric priority that determines its severity. Lower numbers indicate more urgent reasons -- `ErrorForbade` (0) takes precedence over `Completed` (8). This ordering is used when evaluating multiple signals:

```php
$reason->priority();        // Returns the numeric priority (0-9)
$reason->compare($other);   // Spaceship comparison using <=> operator
```

### Distinguishing Graceful Stops from Forced Stops

The `wasForceStopped()` method is particularly useful for determining how the agent finished after execution. Natural endings return `false`, while all resource limits, errors, and explicit stops return `true`:

```php
StopReason::Completed->wasForceStopped();            // false -- natural completion
StopReason::FinishReasonReceived->wasForceStopped();  // false -- model signaled completion
StopReason::StepsLimitReached->wasForceStopped();     // true  -- resource limit hit
StopReason::StopRequested->wasForceStopped();         // true  -- explicit tool stop
StopReason::ErrorForbade->wasForceStopped();          // true  -- error prevented continuation
```

<a name="agent-stop-exception"></a>
## AgentStopException

When a tool determines that the agent's task is complete (or that execution should not continue), it can throw an `AgentStopException`. The loop catches this exception, converts it to a `StopSignal` with reason `StopRequested`, and terminates cleanly.

`AgentStopException` extends `RuntimeException` and is a control-flow exception -- it is not an error condition, but an intentional mechanism for tools to signal completion:

```php
use Cognesy\Agents\Continuation\AgentStopException;
use Cognesy\Agents\Continuation\StopReason;
use Cognesy\Agents\Continuation\StopSignal;
use Cognesy\Agents\Tool\Tools\BaseTool;

class SubmitAnswerTool extends BaseTool
{
    public function __invoke(string $answer): never
    {
        // Store the answer, then stop the loop
        throw new AgentStopException(
            signal: new StopSignal(
                reason: StopReason::StopRequested,
                message: "Answer submitted: {$answer}",
            ),
        );
    }
}
```

The exception carries several properties for rich diagnostic context:

| Property | Type | Description |
|----------|------|-------------|
| `signal` | `StopSignal` | The stop signal to emit when the exception is caught |
| `step` | `?AgentStep` | An optional reference to the current step for diagnostic purposes |
| `context` | `array` | Additional context data passed through to `StopSignal::fromStopException()` |
| `source` | `?string` | The class that threw the exception, for traceability |

The exception message is resolved automatically from the signal's message, the exception's own message, or the stop reason value (in that priority order):

```php
throw new AgentStopException(
    signal: new StopSignal(
        reason: StopReason::Completed,
        message: 'All tasks finished',
    ),
    context: ['tasks_completed' => 5],
    source: self::class,
);
```

### Common Use Cases for AgentStopException

**Task completion tool** -- Let the model signal that it has finished its task:

```php
class TaskCompleteTool extends BaseTool
{
    public function __invoke(string $summary): never
    {
        throw new AgentStopException(
            signal: new StopSignal(
                reason: StopReason::StopRequested,
                message: "Task completed: {$summary}",
                context: ['summary' => $summary],
            ),
            source: self::class,
        );
    }
}
```

**Error-driven stop** -- Halt when a tool encounters an unrecoverable error:

```php
class CriticalOperationTool extends BaseTool
{
    public function __invoke(string $operation): mixed
    {
        try {
            return $this->performOperation($operation);
        } catch (\Exception $e) {
            throw new AgentStopException(
                signal: new StopSignal(
                    reason: StopReason::ErrorForbade,
                    message: "Critical failure: {$e->getMessage()}",
                ),
                previous: $e,
            );
        }
    }
}
```

<a name="execution-continuation"></a>
## ExecutionContinuation

`ExecutionContinuation` is the state object that manages the interplay between stop signals and continuation requests. It holds two independent pieces of state:

- **`StopSignals`** -- the collection of accumulated stop signals
- **`isContinuationRequested`** -- a boolean flag that overrides stop signals when `true`

The key method is `shouldStop()`, which returns `true` only when signals exist **and** no continuation has been requested:

```php
use Cognesy\Agents\Continuation\ExecutionContinuation;

$continuation = ExecutionContinuation::fresh();
// No signals, no continuation request

$continuation->shouldStop();               // false (no signals present)
$continuation->isContinuationRequested();   // false
$continuation->stopSignals()->hasAny();     // false
```

### Modifying Continuation State

`ExecutionContinuation` is immutable. All modifications return new instances:

```php
// Add a stop signal
$continuation = $continuation->withNewStopSignal($signal);

// Request continuation (overrides stop signals)
$continuation = $continuation->withContinuationRequested(true);

// Replace all stop signals at once
$continuation = $continuation->withStopSignals($newSignals);
```

### Overriding Stop Signals with Continuation

In some scenarios, you may want the loop to continue even after a stop signal has been emitted. For example, a summarization hook might intercept a step-limit signal, summarize the conversation to free up context space, and request continuation:

```php
$hook = new CallableHook(function (HookContext $ctx): HookContext {
    $state = $ctx->state();

    // Check if we're being stopped due to step limit
    $signals = $state->execution()?->continuation()->stopSignals();
    if (!$signals?->hasAny()) {
        return $ctx;
    }

    // Summarize and request continuation
    $state = $state->withExecutionContinued();
    return $ctx->withState($state);
});
```

The `withExecutionContinued()` method on `AgentState` sets the continuation flag to `true`, which causes `shouldStop()` to return `false` even though stop signals are present. This gives hooks the power to implement recovery strategies before allowing the loop to terminate.

> **Caution:** Overriding stop signals should be done carefully. If a continuation hook resets the signal but the underlying condition persists (e.g., the token limit is still exceeded after summarization), the guard hook will re-emit the signal on the next step, potentially creating an infinite loop. Always ensure the override resolves the root cause.

### Diagnostic Output

The `explain()` method produces a human-readable summary of the continuation state, useful for logging and debugging:

```php
$continuation->explain();
// "Stop Signals: steps_limit: Step limit reached: 10/10; Continuation Requested: No"
// or
// "No Stop Signals; Continuation Requested: No"
```

<a name="inspecting-after"></a>
## Inspecting Stop Reasons After Execution

After the loop completes, you can inspect why it stopped through the agent state:

```php
$state = $agent->run($state);

$execution = $state->execution();
$continuation = $execution->continuation();

if ($continuation->stopSignals()->hasAny()) {
    $signal = $continuation->stopSignals()->highest(); // most authoritative by priority
    echo "Stopped: {$signal->reason->value} - {$signal->message}\n";
    echo "Was force-stopped: " . ($signal->reason->wasForceStopped() ? 'yes' : 'no') . "\n";
}

// Or get a human-readable explanation
echo $continuation->explain();
// "Stop Signals: steps_limit: Step limit reached: 10/10; Continuation Requested: No"
```

<a name="serialization"></a>
## Serialization

All stop condition components support full serialization for persistence and debugging:

```php
// StopSignal
$data = $signal->toArray();
$signal = StopSignal::fromArray($data);

// StopSignals collection
$data = $signals->toArray();
$signals = StopSignals::fromArray($data);

// ExecutionContinuation
$data = $continuation->toArray();
$continuation = ExecutionContinuation::fromArray($data);
```

This makes it straightforward to persist the complete stop state alongside agent state when saving executions to a database or transferring them across process boundaries.

<a name="combining-guards"></a>
## Combining Guards and Stop Tools

A typical agent setup combines guard hooks (to enforce resource limits) with a stop tool (to allow the model to signal task completion):

```php
use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Capability\Core\UseGuards;
use Cognesy\Agents\Capability\Core\UseTools;

$agent = AgentBuilder::base()
    ->withCapability(new UseGuards(
        maxSteps: 20,
        maxTokens: 16000,
        maxExecutionTime: 60.0,
    ))
    ->withCapability(new UseTools(new SubmitAnswerTool()))
    ->build();
```

In this configuration, the agent will stop when any of these conditions is met:

1. The model calls `SubmitAnswerTool`, which throws `AgentStopException`
2. The step count reaches 20
3. Cumulative token usage exceeds 16,000
4. Wall-clock time exceeds 60 seconds
5. The model produces a final response with no tool calls (natural completion)

<a name="cooperative-cancellation"></a>
## Cooperative Cancellation

The `UseCooperativeCancellation` capability lets external code request that a running agent stop — without subclassing `AgentLoop` or writing custom hook logic.

Cancellation is **cooperative and checkpoint-based**: the loop checks for a signal at `BeforeExecution` and `BeforeStep`. It will not interrupt an in-flight LLM call or tool execution mid-stream. If the agent is between steps when the request arrives, it stops cleanly on the next checkpoint.

### Basic Usage

```php
use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Capability\Cancellation\InMemoryCancellationSource;
use Cognesy\Agents\Capability\Cancellation\UseCooperativeCancellation;

$source = new InMemoryCancellationSource();

$agent = AgentBuilder::base()
    ->withCapability(new UseCooperativeCancellation($source))
    ->build();

// Cancel from a signal handler, timeout, or concurrent request:
$source->cancel('user pressed stop');

$result = $agent->execute($state);
// $result->stopReason() === StopReason::UserRequested
```

`InMemoryCancellationSource` also exposes `reset()` and `isCancellationRequested()` for inspection and reuse across executions.

### Custom Cancellation Sources

Implement `CanProvideCancellationSignal` to integrate any external cancel mechanism — a Redis key, database flag, HTTP endpoint, or PHP signal handler:

```php
use Cognesy\Agents\Capability\Cancellation\CanProvideCancellationSignal;
use Cognesy\Agents\Continuation\StopSignal;
use Cognesy\Agents\Data\AgentState;

class RedisCancellationSource implements CanProvideCancellationSignal
{
    public function cancellationSignal(AgentState $state): ?StopSignal
    {
        $key = "agent:cancel:{$state->agentId()}";
        return $this->redis->exists($key)
            ? StopSignal::userRequested('cancelled via redis', source: self::class)
            : null;
    }
}
```

The method receives the full `AgentState`, so you can scope cancellation to a specific agent ID, execution ID, or session.

### Cancellation vs. Hard Interruption

Unlike thread-based cancellation tokens (e.g. `CancellationToken` in .NET or `context.Context` in Go), cooperative cancellation only stops the loop at safe checkpoints. An ongoing HTTP request to the LLM or a running tool will complete before the loop checks for the signal.

If you need to cancel mid-request, that requires interrupting the underlying HTTP transport — which is outside the scope of this capability.

<a name="quick-reference"></a>
## Quick Reference

| I want to... | Use... |
|--------------|--------|
| Stop after N steps | `UseGuards(maxSteps: N)` or register `StepsLimitHook` directly |
| Stop after N tokens | `UseGuards(maxTokens: N)` or register `TokenUsageLimitHook` directly |
| Stop after N seconds | `UseGuards(maxExecutionTime: N)` or register `ExecutionTimeLimitHook` directly |
| Stop on LLM finish reason | `UseGuards(finishReasons: [...])` or register `FinishReasonHook` directly |
| Stop from inside a tool | Throw `AgentStopException` with a `StopSignal` |
| Stop from a custom hook | Emit a `StopSignal` via `$state->withStopSignal()` |
| Cancel from outside the loop | `UseCooperativeCancellation` + `CanProvideCancellationSignal` |
| Override a stop signal | Call `$state->withExecutionContinued()` in a hook |
| Check why the agent stopped | Inspect `$state->executionContinuation()->stopSignals()` |
| Check if stop was forced | Call `$signal->reason->wasForceStopped()` |
| Get human-readable stop info | Call `$continuation->explain()` |
