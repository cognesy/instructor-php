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

`iterate()` yields state after each step:

```php
foreach ($loop->iterate($state) as $stepState) {
    $step = $stepState->lastStep();
    echo "Step {$stepState->stepCount()}: {$step->stepType()->value}\n";
}
```

Use `execute()` for normal application code.
Use `iterate()` when you need progress updates or step-level inspection.

## Inspecting State

```php
$state->stepCount();
$state->lastStepType();
$state->lastStopReason();
$state->usage();
$state->executionDuration();

foreach ($state->steps()->all() as $step) {
    echo $step->outputMessages()->toString() . "\n";
}

$toolExec = $state->lastToolExecution();
$toolExec?->name();
$toolExec?->hasError();
```

## Reading the Response

`AgentState` exposes two response helpers:

- `finalResponse()` returns the last natural assistant answer
- `currentResponse()` returns the latest visible output

```php
$state->hasFinalResponse();
$state->finalResponse()->toString();
$state->currentResponse()->toString();
```

A simple pattern is:

```php
if ($state->hasFinalResponse()) {
    $text = $state->finalResponse()->toString();
} else {
    $text = $state->currentResponse()->toString();
}
```

## Listening to Events

```php
use Cognesy\Agents\Events\AgentStepCompleted;

$loop->onEvent(AgentStepCompleted::class, function (AgentStepCompleted $event) {
    echo "Step {$event->stepNumber} completed\n";
});

$loop->wiretap(function (object $event) {
    echo get_class($event) . "\n";
});
```
