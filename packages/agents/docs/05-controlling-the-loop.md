---
title: 'Controlling the Loop'
description: 'Run agents to completion with execute() or step through with iterate() for fine-grained control'
---

# Controlling the Loop

## execute() vs iterate()

`execute()` runs the loop to completion and returns the final state:

```php
$finalState = $loop->execute($state);
```

`iterate()` yields state after each step, giving you full control:

```php
foreach ($loop->iterate($state) as $stepState) {
    $step = $stepState->lastStep();
    echo "Step {$stepState->stepCount()}: {$step->stepType()->value}\n";

    // Access tool executions from this step
    foreach ($step->toolExecutions()->all() as $exec) {
        echo "  Tool: {$exec->name()} -> {$exec->value()}\n";
    }
}
```

## Inspecting State

After execution, query the state for results:

```php
$state->stepCount();                    // total steps executed
$state->finalResponse()->toString();    // final text response
$state->lastStepType();                 // AgentStepType enum
$state->lastStopReason();               // StopReason enum
$state->usage();                        // token usage
$state->executionDuration();            // seconds elapsed

// Access all steps
foreach ($state->steps()->all() as $step) {
    echo $step->stepType()->value . ': ';
    echo $step->outputMessages()->toString() . "\n";
}

// Last tool execution
$toolExec = $state->lastToolExecution();
$toolExec?->name();     // 'weather'
$toolExec?->value();    // '72F, sunny'
$toolExec?->hasError(); // false
```

## Listening to Events

Attach listeners to monitor execution:

```php
use Cognesy\Agents\Events\AgentStepCompleted;

$loop->onEvent(AgentStepCompleted::class, function (AgentStepCompleted $event) {
    echo "Step completed: {$event->state->stepCount()}\n";
});

// Or listen to all events
$loop->wiretap(function ($event) {
    echo get_class($event) . "\n";
});
```
