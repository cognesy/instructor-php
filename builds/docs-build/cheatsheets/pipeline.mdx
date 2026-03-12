---
title: Pipeline
description: Data processing pipelines — builder, error strategies, processors, and pipe composition
package: pipeline
---

# Pipeline Cheatsheet

## Core Types

### `Pipeline`

```php
Pipeline::builder(ErrorStrategy $onError = ErrorStrategy::ContinueWithFailure): PipelineBuilder

->executeWith(CanCarryState $state): PendingExecution
->process(CanCarryState $state): CanCarryState
```

### `PipelineBuilder`

```php
->through(callable $operation)
->throughAll(callable ...$operations)
->map(callable $operation)
->when(callable $condition, callable $then, ?callable $otherwise = null)
->tap(callable $operation)
->filter(callable $condition, string $message = 'Value filter condition failed')
->onFailure(callable $operation)
->finally(callable $operation)
->create(): Pipeline
->executeWith(CanCarryState $state): PendingExecution
```

### `PendingExecution`

```php
->execute(): CanCarryState
->state(): CanCarryState
->result(): Result
->value(): mixed
->valueOr(mixed $default): mixed
->isSuccess(): bool
->isFailure(): bool
->exception(): ?Throwable
->for(mixed $value, array $tags = []): self
->each(iterable $inputs, array $tags = []): Generator
->stream(): Generator
```

### `ProcessingState`

```php
ProcessingState::empty()
ProcessingState::with(mixed $value, array $tags = [])

->withResult(Result $result): self
->failWith(string|Throwable $cause): self
->addTags(TagInterface ...$tags): self
->replaceTags(TagInterface ...$tags): self
->result(): Result
->value(): mixed
->valueOr(mixed $default): mixed
->isSuccess(): bool
->isFailure(): bool
->exception(): Throwable
->exceptionOr(mixed $default): mixed
->tagMap(): TagMapInterface
->allTags(?string $tagClass = null): array
->hasTag(string $tagClass): bool
->tags(): TagQuery
->applyTo(CanCarryState $priorState): CanCarryState
->transform(): TransformState
```

### `TransformState`

```php
TransformState::with(CanCarryState $state)

->state(): CanCarryState
->result(): Result
->value(): mixed
->valueOr(mixed $default): mixed
->isSuccess(): bool
->isFailure(): bool
->exception(): Throwable
->exceptionOr(mixed $default): mixed
->tagMap(): TagMapInterface
->tags(): TagQuery
->hasTag(string $tagClass): bool
->allTags(): array
->recover(mixed $defaultValue): CanCarryState
->recoverWith(callable $recovery): CanCarryState
->when(callable $conditionFn, callable $transformationFn): self
->whenState(callable $stateConditionFn, callable $stateTransformationFn): self
->addTagsIf(callable $condition, TagInterface ...$tags): self
->addTagsIfSuccess(TagInterface ...$tags): self
->addTagsIfFailure(TagInterface ...$tags): self
->mergeFrom(CanCarryState $source): self
->mergeInto(CanCarryState $target): self
->combine(CanCarryState $other, ?callable $resultCombinator = null): self
->failWhen(callable $conditionFn, string $errorMessage = 'Failure condition met'): self
->map(callable $fn): self
->mapResult(callable $fn): self
->mapState(callable $fn): self
```

## Step Output Normalization

Value steps may return:

- a raw value
- a `Result`
- a `CanCarryState`

Normalization rules:

- raw value -> wrapped into `Result::success(...)`
- `Result` -> replaces the current state result
- `CanCarryState` -> applied onto the prior state via `applyTo()`
- `null` from `through()` / `map()` -> failure with `Null value encountered`
- `null` from `finally()` / `when()` branches -> success with `null`

## Quick Examples

### Simple transform

```php
$value = Pipeline::builder()
    ->through(fn(int $x) => $x * 2)
    ->through(fn(int $x) => $x + 1)
    ->create()
    ->executeWith(ProcessingState::with(5))
    ->value();
```

### Failure shaping

```php
$result = Pipeline::builder()
    ->through(fn() => throw new RuntimeException('boom'))
    ->onFailure(fn($state) => logger()->error($state->exception()->getMessage()))
    ->finally(fn($state) => $state->isSuccess()
        ? $state->result()
        : Result::failure('wrapped'))
    ->create()
    ->executeWith(ProcessingState::with('input'))
    ->result();
```
