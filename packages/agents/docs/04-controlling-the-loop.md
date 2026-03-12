---
title: 'Controlling the Loop'
description: 'Run agents to completion with execute() or step through with iterate() for fine-grained control'
---

# Controlling the Loop

The `AgentLoop` exposes two methods for running an agent: `execute()` for
simple run-to-completion workflows, and `iterate()` for step-by-step
observation. Both operate on an immutable `AgentState` and return the
resulting state after the agent finishes.


## execute() vs iterate()

### Running to Completion

The `execute()` method runs the full loop and returns the final state in a
single call. This is the right choice for most application code where you
simply need the agent's answer:

```php
use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Data\AgentState;

$loop = AgentLoop::default();

$state = AgentState::empty()
    ->withSystemPrompt('You are a helpful assistant.')
    ->withUserMessage('What are the three laws of thermodynamics?');

$finalState = $loop->execute($state);

echo $finalState->finalResponse()->toString();
```

Internally, `execute()` is a thin wrapper around `iterate()` -- it simply
consumes the iterator and returns the last yielded state.

### Stepping Through Execution

The `iterate()` method returns a generator that yields the state after each
completed step. This gives you the opportunity to observe progress, log
intermediate results, update a UI, or apply custom logic between steps:

```php
foreach ($loop->iterate($state) as $stepState) {
    $step = $stepState->currentStepOrLast();
    $type = $step?->stepType();

    echo sprintf(
        "Step %d: %s (%d tokens)\n",
        $stepState->stepCount(),
        $type?->value ?? 'unknown',
        $step?->usage()->total() ?? 0,
    );
}
```

Each yielded `$stepState` is a complete `AgentState` snapshot. You can
inspect messages, tool executions, errors, and token usage at every
point in the agent's run. The final yield includes the post-execution
state after the `AfterExecution` hooks have fired.

Use `execute()` for straightforward application logic. Use `iterate()` when
you need progress updates, streaming indicators, step-level logging, or any
form of real-time observation.


## Inspecting State After Execution

Once the loop finishes, the returned `AgentState` provides a comprehensive
set of accessors to understand what happened during the run.

### Execution Summary

```php
$state->status();             // ExecutionStatus::Completed
$state->stepCount();          // Number of completed steps
$state->executionDuration();  // Total wall-clock time (seconds)
$state->usage();              // Accumulated token usage across all steps
$state->executionCount();     // How many times this agent has been executed
```

### Step History

Every completed step is recorded as a `StepExecution` in the execution's
step history. You can iterate over all steps to review the full trace of
the agent's reasoning:

```php
foreach ($state->stepExecutions()->all() as $stepExecution) {
    $step = $stepExecution->step();

    echo sprintf(
        "Step [%s]: %s (%.2fs)\n",
        $step->stepType()->value,
        $step->outputMessages()->toString(),
        $stepExecution->duration(),
    );
}
```

For quick access to the most recent step:

```php
$state->lastStep();              // The last completed AgentStep
$state->lastStepType();          // AgentStepType enum value
$state->lastStepUsage();         // Token usage for the last step
$state->lastStepDuration();      // Duration of the last step (seconds)
$state->lastStepErrors();        // ErrorList from the last step
```

### Tool Execution Details

When the agent used tools during its run, you can drill into the execution
details of each tool call:

```php
$toolExec = $state->lastToolExecution();

if ($toolExec !== null) {
    echo $toolExec->name();       // Tool name, e.g. 'search_web'
    echo $toolExec->hasError();   // Whether the tool call failed
    echo $toolExec->value();      // The return value on success
}
```

To see all tool executions from the last step:

```php
foreach ($state->lastStepToolExecutions()->all() as $toolExec) {
    echo sprintf(
        "%s(%s) -> %s\n",
        $toolExec->name(),
        json_encode($toolExec->args()),
        $toolExec->hasError() ? 'ERROR: ' . $toolExec->errorMessage() : 'OK',
    );
}
```

### Stop Reason

Every execution ends for a reason. The stop reason tells you whether the
agent completed naturally, hit a limit, encountered an error, or was stopped
by an external request:

```php
use Cognesy\Agents\Continuation\StopReason;

$reason = $state->lastStopReason(); // StopReason enum

match ($reason) {
    StopReason::Completed           => 'Agent finished naturally',
    StopReason::FinishReasonReceived=> 'LLM signaled completion',
    StopReason::StepsLimitReached   => 'Hit the maximum step count',
    StopReason::TokenLimitReached   => 'Exceeded token budget',
    StopReason::TimeLimitReached    => 'Exceeded time limit',
    StopReason::RetryLimitReached   => 'Hit the maximum retry count',
    StopReason::StopRequested       => 'A hook requested a stop',
    StopReason::ErrorForbade        => 'An error prevented continuation',
    StopReason::UserRequested       => 'The user requested a stop',
    default                         => 'Unknown reason',
};
```

You can also retrieve the full stop signal for additional context:

```php
$signal = $state->lastStopSignal();

$signal->reason;   // StopReason enum
$signal->message;  // Human-readable explanation
$signal->context;  // Array of contextual data
$signal->source;   // The class that emitted the signal
```


## Reading the Response

`AgentState` provides two convenience methods for extracting the agent's
output, each suited to different situations.

### finalResponse()

Returns the output messages from the last step, but only if that step was
a `FinalResponse` (the model answered without requesting tool calls). If
the execution ended mid-tool-use or with an error, this returns an empty
`Messages` collection:

```php
if ($state->hasFinalResponse()) {
    echo $state->finalResponse()->toString();
}
```

### currentResponse()

Returns the most recent visible output regardless of step type. It first
checks for a final response; if none exists, it falls back to the output
of the current or last step. This is useful during `iterate()` loops where
you want to show the latest output even if the agent has not finished:

```php
echo $state->currentResponse()->toString();
```

A typical pattern after execution combines both:

```php
$text = $state->hasFinalResponse()
    ? $state->finalResponse()->toString()
    : $state->currentResponse()->toString();
```


## Listening to Events

The `AgentLoop` dispatches events at every significant point in the
execution lifecycle. You can subscribe to specific event types or listen
to all events with a wiretap.

### Subscribing to Specific Events

Use `onEvent()` to register a listener for a particular event class. The
listener receives the fully-typed event object:

```php
use Cognesy\Agents\Events\AgentStepCompleted;

$loop->onEvent(AgentStepCompleted::class, function (AgentStepCompleted $event) {
    echo sprintf(
        "Step %d: %d tokens, finish=%s (%.2fms)\n",
        $event->stepNumber,
        $event->usage->total(),
        $event->finishReason?->value ?? 'n/a',
        $event->durationMs,
    );
});
```

### Wiretap (All Events)

Use `wiretap()` to observe every event the loop dispatches. This is
invaluable for debugging and logging:

```php
$loop->wiretap(function (object $event) {
    echo get_class($event) . "\n";
});
```

### Available Events

The loop emits the following events during execution:

| Event | When |
|---|---|
| `AgentExecutionStarted` | The loop begins a new execution |
| `AgentStepStarted` | A new step is about to begin |
| `InferenceRequestStarted` | An LLM request is being sent |
| `InferenceResponseReceived` | An LLM response has arrived |
| `ToolCallStarted` | A tool call is about to execute |
| `ToolCallCompleted` | A tool call has finished |
| `ToolCallBlocked` | A hook blocked a tool call |
| `AgentStepCompleted` | A step has finished (includes usage and timing) |
| `ContinuationEvaluated` | The loop evaluated whether to continue |
| `StopSignalReceived` | A stop signal was emitted |
| `TokenUsageReported` | Token usage was recorded for a step |
| `AgentExecutionStopped` | The loop is stopping (includes stop reason) |
| `AgentExecutionCompleted` | The execution has fully finished |
| `AgentExecutionFailed` | The execution ended with an error |

Events are dispatched through the loop's `EventDispatcher`. If you are using
the `AgentBuilder`, the builder can wire a parent event handler so that
events propagate up to your application's event system.


## Debugging Execution

For quick diagnostics, `AgentState` provides a `debug()` method that returns
an array summarizing the execution:

```php
$info = $state->debug();

// [
//     'status'         => ExecutionStatus::Completed,
//     'executionCount' => 1,
//     'hasExecution'   => true,
//     'executionId'    => '550e8400-e29b-41d4-a716-446655440000',
//     'steps'          => 3,
//     'continuation'   => 'No Stop Signals; Continuation Requested: No',
//     'hasErrors'      => false,
//     'errors'         => ErrorList(...),
//     'usage'          => ['input' => 150, 'output' => 42],
// ]
```

This is particularly useful when logging or when you need a quick overview
of what happened without drilling into individual steps.
