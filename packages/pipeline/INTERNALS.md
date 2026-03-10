# Pipeline Internals

## Current Shape

The package now has one execution model:

1. start with a `CanCarryState`
2. run sequential step closures
3. run failure callbacks when a step transitions the state into failure
4. run finalizers over the final state
5. expose the result lazily through `PendingExecution`

The old split between legacy chains and the operator-stack pipeline has been removed.

## Execution Model

`PipelineBuilder` compiles three ordered lists:

- `steps`
- `failureHandlers`
- `finalizers`

Each step is normalized to a closure with the shape:

```php
Closure(CanCarryState): CanCarryState
```

The `Pipeline` class itself is intentionally small:

- iterate over steps until the state fails
- respect `ErrorStrategy`
- invoke `onFailure()` callbacks when a step fails
- always run finalizers starting from the last state

## Normalization Rules

Value-oriented steps created by `through()` and `map()` operate on `state->value()` and then normalize output:

- raw value -> `withResult(Result::from($value))`
- `Result` -> `withResult($result)`
- `CanCarryState` -> `$output->applyTo($priorState)`
- `null` -> failure for `through()` / `map()`

`tap()` does not alter the state. It exists only for side effects.

`finally()` receives the whole state and can return:

- a raw value
- a `Result`
- a `CanCarryState`

Finalizers are allowed to produce `null`.

## State Layer

`ProcessingState` remains the default immutable state object:

- `Result` holds success or failure
- tags carry metadata
- `applyTo()` merges the prior tag map into the new state

`TransformState` remains a lightweight post-processing helper around any `CanCarryState`.

## What Was Removed

The current package no longer contains:

- legacy raw/result chain implementations
- middleware stacks
- per-step hooks
- raw operator plumbing
- observation operators and pipeline-only measurement tags
- alternate null-handling strategies

That code existed to support abstractions that the repo’s real clients were not using. The remaining implementation keeps the capabilities that are actually exercised by current production callsites.
