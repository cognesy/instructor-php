# Pipeline Package

Small immutable pipelines for value transformations with `Result`-backed state.

## What It Is

The package now exposes one pipeline implementation:

- value-oriented steps via `through()` and `throughAll()`
- side effects via `tap()` and `onFailure()`
- final shaping via `finally()`
- lazy execution via `PendingExecution`
- immutable state via `ProcessingState`
- post-processing helpers via `TransformState`

## Basic Usage

```php
use Cognesy\Pipeline\Pipeline;
use Cognesy\Pipeline\ProcessingState;

$result = Pipeline::builder()
    ->through(fn(string $value) => trim($value))
    ->through(fn(string $value) => strtoupper($value))
    ->create()
    ->executeWith(ProcessingState::with(' hello '))
    ->value();
```

## Failure Handling

```php
use Cognesy\Pipeline\StateContracts\CanCarryState;
use Cognesy\Utils\Result\Result;

$result = Pipeline::builder()
    ->through(fn() => throw new RuntimeException('broken'))
    ->onFailure(fn(CanCarryState $state) => report($state->exception()))
    ->finally(fn(CanCarryState $state) => match ($state->isSuccess()) {
        true => $state->result(),
        false => Result::failure('pipeline failed'),
    })
    ->create()
    ->executeWith(ProcessingState::with('input'))
    ->result();
```

## State Helpers

`ProcessingState` stores:

- a `Result`
- ordered tags

`TransformState` is a small helper for mapping, recovery, and tag-preserving state transformations after pipeline execution.
