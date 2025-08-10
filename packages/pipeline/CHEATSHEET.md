# Pipeline Package API Cheatsheet

## Pipeline Factory Methods

```php
// Create empty pipeline
Pipeline::empty()

// Pipeline from callable source
Pipeline::from(fn() => getData())

// Pipeline with initial value
Pipeline::for($data)
```

## PipelineBuilder API

### Construction
```php
$builder = new PipelineBuilder($source, $tags);
$builder->withSource(fn() => $data);
$builder->withInitialValue($data);
$builder->withTags(...$tags);
```

### Processing Chain
```php
// Add processors
$builder->through($callable);
$builder->throughAll(...$callables);
$builder->throughProcessor($processor);

// Conditional processing
$builder->when($condition, $callback);

// Side effects (don't change result)
$builder->tap($callback);
$builder->tapWithState($stateCallback);

// Transformations
$builder->map($fn);
$builder->filter($condition, $message);
$builder->filterWithState($condition, $message);

// Finalization
$builder->finally($finalizer);
```

### Middleware & Hooks
```php
// Pipeline-level middleware
$builder->withMiddleware(...$middleware);
$builder->prependMiddleware(...$middleware);

// Per-processor hooks
$builder->beforeEach($hook);         // Execute before each processor
$builder->afterEach($hook);          // Execute after each processor  
$builder->aroundEach($middleware);   // Wrap around each processor
$builder->onFailure($handler);

// Control flow
$builder->finishWhen($condition);
$builder->failWhen($condition, $message);
```

### Execution
```php
$pending = $builder->create();
```

## PendingExecution API

### Value Access
```php
$pending->execute();           // ProcessingState
$pending->state();            // ProcessingState
$pending->result();           // Result
$pending->value();            // mixed (throws on failure)
$pending->valueOr($default);  // mixed
$pending->stream();           // Generator
```

### Status Checks
```php
$pending->isSuccess();  // bool
$pending->isFailure();  // bool
$pending->exception();  // ?Throwable
```

### Batch Processing
```php
$pending->for($value, $tags);
$pending->each($inputs, $tags);
```

## ProcessingState API

### Construction
```php
ProcessingState::empty();
ProcessingState::with($value, $tags);
```

### State Management
```php
$state->withResult($result);
$state->withTags(...$tags);
$state->failWith($cause);
```

### Value Access
```php
$state->result();              // Result
$state->value();              // mixed (throws on failure)
$state->valueOr($default);   // mixed
$state->isSuccess();          // bool
$state->isFailure();          // bool
$state->exception();          // throws on success
$state->exceptionOr($default);// mixed
```

### Transformations
```php
$state->map($fn);           // ProcessingState
$state->mapResult($fn);     // ProcessingState
$state->mapState($fn);      // ProcessingState
```

### Error Handling
```php
$state->recover($defaultValue);
$state->recoverWith($recoveryFn);
$state->failWhen($condition, $message);
```

### Conditional Operations
```php
$state->when($condition, $transformation);
$state->whenState($stateCondition, $stateTransformation);
```

### Tag Operations
```php
$state->tagMap();                    // TagMapInterface
$state->allTags($tagClass);          // TagInterface[]
$state->hasTag($tagClass);           // bool
$state->tags();                      // TagQuery

// Conditional tag operations
$state->addTagsIf($condition, ...$tags);
$state->addTagsIfSuccess(...$tags);
$state->addTagsIfFailure(...$tags);

// State merging
$state->mergeFrom($source);
$state->mergeInto($target);
$state->combine($other, $resultCombinator);
```

## Workflow API

### Construction
```php
Workflow::empty();
```

### Step Types
```php
// Sequential execution
$workflow->through($pipeline);

// Conditional execution
$workflow->when($condition, $pipeline);

// Side effects (don't affect result)
$workflow->tap($pipeline);
```

### Execution
```php
$workflow->process($state);  // ProcessingState
```

## Tag System

### Built-in Tags
```php
new TimingTag($start, $end, $duration, $name, $success);
new StepTimingTag($stepName, $start, $end, $duration, $success);
new MemoryTag($startMem, $endMem, $used, $startPeak, $endPeak, $peakUsed, $name);
new StepMemoryTag($stepName, $startMem, $endMem, $used);
new ErrorTag($error, $timestamp, $context);
new SkipProcessingTag();
```

### Tag Operations
```php
// Factory
TagMapFactory::create($tags);
TagMapFactory::empty();

// Query API
$tagMap->query()
  ->only($tagClass)
  ->all();

// Map operations
$tagMap->has($tagClass);
$tagMap->isEmpty();
$tagMap->merge($other);
$tagMap->with(...$tags);
```

## Contracts & Interfaces

### Core Interfaces
- `CanProcessState` - Process ProcessingState objects
- `CanControlStateProcessing` - Middleware interface
- `CanFinalizeProcessing` - Finalizer interface
- `TagInterface` - Tag implementation interface
- `TagMapInterface` - Tag storage interface

### Common Patterns
```php
// Value processor (auto-wrapped)
fn($value) => $transformedValue

// State processor (full control)
fn(ProcessingState $state) => ProcessingState

// Middleware
class MyMiddleware implements CanControlStateProcessing {
    public function handle(ProcessingState $state, callable $next): ProcessingState {
        // Pre-processing
        $result = $next($state);
        // Post-processing
        return $result;
    }
}
```

## Timing & Memory Tracking

### Pipeline-Level Monitoring
```php
// Capture entire pipeline timing
Pipeline::for($data)
    ->withMiddleware(Timing::capture('operation'))
    ->withMiddleware(Memory::capture('operation'))
    ->through($processor)
    ->create()
    ->execute();

// Extract data
$timings = $result->allTags(TimingTag::class);
$memory = $result->allTags(MemoryTag::class);
```

### Step-Level Monitoring  
```php
// Capture timing/memory for each processor
Pipeline::for($data)
    ->aroundEach(StepTiming::capture('processing'))    // Applied to ALL processors
    ->aroundEach(StepMemory::capture('processing'))    // Applied to ALL processors  
    ->through($validateProcessor)
    ->through($heavyProcessor)
    ->create()
    ->execute();

// Extract step data - one entry per processor
$stepTimings = $result->allTags(StepTimingTag::class);  // 2 entries
$stepMemory = $result->allTags(StepMemoryTag::class);   // 2 entries
```

### Monitoring Data Access
```php
// TimingTag methods
$timing->durationMs();                // Duration in milliseconds
$timing->durationFormatted();         // Human-readable (e.g., "125ms")
$timing->startDateTime();             // DateTime object
$timing->toArray();                   // Serializable array

// MemoryTag methods  
$memory->memoryUsedMB();             // Memory used in MB
$memory->memoryUsedFormatted();      // Human-readable (e.g., "2.5MB")
$memory->isMemoryFreed();            // true if negative delta
```

## Error Handling Patterns

### Result Pattern
```php
if ($result->isFailure()) {
    $error = $result->errorMessage();
}
```

### State-based Error Handling
```php
if ($state->isFailure()) {
    $errorTag = $state->allTags(ErrorTag::class)[0] ?? null;
}
```

### Recovery Patterns
```php
$state->recover($defaultValue);
$state->recoverWith(fn($failedState) => $recoveryLogic);
```

## Performance Optimization

### Lazy Evaluation
```php
$pending = $pipeline->create();  // No execution
$value = $pending->value();      // Execute when needed
```

### Middleware Placement
```php
// Efficient: pipeline-level timing
$builder->withMiddleware(Timing::capture('operation'));
$builder->withMiddleware(Memory::capture('operation'));

// Step-level timing (around each processor)
$builder->aroundEach(StepTiming::capture('step-name'));
$builder->aroundEach(StepMemory::capture('step-name'));
```

### Condition Optimization
```php
// Cheap conditions first
$workflow->when($cheapCheck, $expensivePipeline);
```