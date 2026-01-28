# Pipeline Package Cheatsheet

## Core Interfaces

### `CanCarryState` - State Container
```php
// Factory
ProcessingState::empty(): self
ProcessingState::with(mixed $value, array $tags = []): self

// State Operations
->withResult(Result $result): self
->addTags(TagInterface ...$tags): self
->replaceTags(TagInterface ...$tags): self
->failWith(string|Throwable $cause): self

// Access
->result(): Result
->value(): mixed
->valueOr(mixed $default): mixed
->isSuccess(): bool
->isFailure(): bool
->exception(): Throwable
->exceptionOr(mixed $default): mixed

// Tags
->tagMap(): TagMapInterface
->allTags(?string $tagClass = null): array
->hasTag(string $tagClass): bool
->tags(): TagQuery
->transform(): TransformState
```

### `CanProcessState` - Processor Contract
```php
public function process(CanCarryState $state, ?callable $next = null): CanCarryState;
```

## Pipeline Construction & Execution

### `Pipeline::builder()` - Fluent Builder
```php
Pipeline::builder()
    // Core Processing
    ->through(callable|CanProcessState $processor)
    
    // Middleware (wraps entire chain)
    ->withMiddleware(CanProcessState $middleware)
    
    // Hooks (wraps each step)
    ->beforeEach(callable|CanProcessState $hook)
    ->afterEach(callable|CanProcessState $hook)
    ->aroundEach(callable|CanProcessState $hook)
    
    // Finalizers (always run)
    ->finally(callable|CanProcessState $finalizer)
    
    // Error Strategy
    ->onError(ErrorStrategy::FailFast|ContinueWithFailure)
    
    // Build
    ->create(): Pipeline
```

### Pipeline Execution
```php
$pipeline = Pipeline::builder()->through($processor)->create();

// Execute
->executeWith(CanCarryState $state): PendingExecution

// Direct processing
->process(CanCarryState $state, ?callable $next = null): CanCarryState
```

### `PendingExecution` - Lazy Evaluator
```php
// Result Access
->execute(): CanCarryState
->state(): CanCarryState  
->result(): Result
->value(): mixed
->valueOr(mixed $default): mixed

// Status
->isSuccess(): bool
->isFailure(): bool
->exception(): ?Throwable

// Batch Processing
->for(mixed $value, array $tags = []): self
->each(iterable $inputs, array $tags = []): Generator
->stream(): Generator
```

## State Management

### `ProcessingState` - Immutable State

```php
// Creation
ProcessingState::empty()
ProcessingState::with($data, [$tag1, $tag2])

// Transformation (returns new instance)
$state->withResult(Result::success($newValue))
$state->addTags(new TimingTag(), new MetricTag())
$state->replaceTags(new ErrorTag($error))
$state->withFailure('Error message')
```

## Tag System

### Tag Query API
```php
$state->tags()
    // Filtering
    ->filter(callable $predicate)
    ->ofType(string $class)
    ->only(string ...$classes)
    ->without(string ...$classes)
    ->limit(int $count)
    ->skip(int $count)
    
    // Transformation
    ->map(callable $callback)
    ->mapTo(callable $transformer): array
    
    // Terminals
    ->all(): array
    ->first(): ?TagInterface
    ->last(): ?TagInterface
    ->count(): int
    ->has(string|TagInterface $tag): bool
    ->hasAll(string|TagInterface ...$tags): bool
    ->hasAny(string|TagInterface ...$tags): bool
    ->isEmpty(): bool
    ->classes(): array
```

### Built-in Tags
```php
new ErrorTag(string $error, array $context = [])
new SkipProcessingTag()

// Observation Tags
new TimingTag(float $start, float $end, float $duration)
new MemoryTag(int $start, int $end, int $peak)
new StepTimingTag(string $step, float $duration)
new StepMemoryTag(string $step, int $usage)
```

## TagMap Implementations

### `IndexedTagMap` (Default)
```php
IndexedTagMap::create(array $tags): self
IndexedTagMap::empty(): self

->add(TagInterface ...$tags): self
->replace(TagInterface ...$tags): self
->merge(TagMapInterface $other): self
->has(string $tagClass): bool
->getAllInOrder(): array
->query(): TagQuery
```

### `SimpleTagMap` (Type-optimized)
```php
SimpleTagMap::create(array $tags): self
SimpleTagMap::empty(): self
// Same methods as IndexedTagMap but with O(1) class lookups
```

## Middleware Patterns

### Pipeline Middleware (wraps entire chain)
```php
class CustomMiddleware implements CanProcessState {
    public function process(CanCarryState $state, ?callable $next = null): CanCarryState {
        // Before processing
        $result = $next ? $next($state) : $state;
        // After processing
        return $result;
    }
}

$pipeline->withMiddleware(new CustomMiddleware());
```

### Step Hooks (wraps individual steps)
```php
$pipeline->aroundEach(function(CanCarryState $state, ?callable $next = null) {
    // Before step
    $result = $next ? $next($state) : $state;
    // After step
    return $result;
});
```

## Built-in Operators

### Core Operators
```php
// Direct calls
Call::with(callable $processor)
CallBefore::with(callable $processor) 
CallAfter::with(callable $processor)
RawCall::with(callable $processor)

// Conditional
ConditionalCall::when(callable $condition, CanProcessState $processor)
FailWhen::condition(callable $condition, string $message = '')

// Control Flow
Skip::processing()
Fail::with(string $message)
Terminal::value(mixed $value)
NoOp::create()

// Side Effects
Tap::with(callable $processor)
TapOnFailure::with(callable $processor)

// Lifecycle
Finalize::with(callable $processor)
```

### Observation Operators
```php
// Pipeline-level timing
StepTiming::capture(string $name = 'step')
StepMemory::capture(string $name = 'step')

// Memory tracking  
TrackMemory::during(string $name = 'operation')
TrackTime::during(string $name = 'operation')
```

## Quick Examples

### Basic Pipeline
```php
$result = Pipeline::builder()
    ->through(fn($x) => $x * 2)
    ->through(fn($x) => $x + 10)
    ->create() // creates Pipeline instance
    ->executeWith(ProcessingState::with(5))
    ->value(); // 20
```

### With Middleware
```php
$pipeline = Pipeline::builder()
    ->withMiddleware(new TimingMiddleware())
    ->through($businessLogic)
    ->aroundEach(new StepLogger())
    ->finally($cleanup)
    ->create(); // creates Pipeline instance
```

### Error Handling
```php
$result = $pipeline->executeWith($data);
if ($result->isFailure()) {
    $error = $result->exception();
    $errorTags = $result->state()->allTags(ErrorTag::class);
}
```

### Tag Operations
```php
$state = ProcessingState::with($data, [new MetricTag('start')])
    ->addTags(new TimingTag($start, $end, $duration))
    ->addTags(new CustomTag($metadata));

$timings = $state->tags()->ofType(TimingTag::class)->all();
$hasErrors = $state->hasTag(ErrorTag::class);
```

### Conditional Processing
```php
Pipeline::builder()
    ->through($validation)
    ->when(fn($state) => $state->value()['premium'], $premiumFeatures)
    ->through($standardProcessing)
    ->create();
```

## Error Strategies
```php
ErrorStrategy::ContinueWithFailure  // Default - capture errors, continue
ErrorStrategy::FailFast             // Throw on first error
```

## Performance Tips
- Use `IndexedTagMap` for general use, `SimpleTagMap` for heavy tag filtering
- Pipeline middleware wraps entire chain (1 call), hooks wrap each step (N calls)
- `PendingExecution` caches results - safe to call `->value()` multiple times
- Prefer immutable operations - all state changes return new instances