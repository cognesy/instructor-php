# Pipeline Package API Cheatsheet

## Quick Start - Clean API Pattern

```php
// 1. Build pipeline (stateless)
$pipeline = Pipeline::builder()
    ->through(fn($x) => $x * 2)
    ->withMiddleware($middleware)
    ->create();

// 2. Execute with data and optional tags
$result = $pipeline->executeWith($data, $tag1, $tag2);

// 3. Access results
$value = $result->value();                 // Extract value
$state = $result->state();                 // Get processing state
$tags = $state->allTags(CustomTag::class); // Query tags
```


## Pipeline Factory Methods

```php
// Create empty pipeline builder (only available method)
Pipeline::builder()  // Returns PipelineBuilder
```

## PipelineBuilder API

### Construction
```php
$builder = new PipelineBuilder();  // Clean constructor - no parameters
// Note: withSource(), withInitialValue(), withTags() methods removed
// Values and tags are provided at execution time via executeWith()
```

### Processing Chain
```php
// Add processors
$builder->through($callable);
$builder->throughAll(...$callables);
$builder->throughOperator($processor);

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
$builder->withOperator(...$operator);
$builder->prependOperator(...$operator);

// Per-processor hooks
$builder->beforeEach($operator);         // Execute before each processor
$builder->afterEach($operator);          // Execute after each processor  
$builder->aroundEach($operator);   // Wrap around each processor
$builder->onFailure($operator);

// Control flow
$builder->finishWhen($condition);
$builder->failWhen($condition, $message);
```

### Execution
```php
$pipeline = $builder->create();  // Returns Pipeline instance
```

## Pipeline Execution API

```php
// Execute with data and optional tags
$pending = $pipeline->executeWith($data);                    // PendingExecution
$pending = $pipeline->executeWith($data, $tag1, $tag2);      // With tags
$pending = $pipeline->executeWith($data, ...$tagArray);      // With tag array

// Process state directly (middleware interface)
$state = $pipeline->process($processingState, $nextCallable);  // ProcessingState
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
$workflow->executeWith($data);  // PendingExecution
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
- `CanProcessState` - Process ProcessingState objects (unified interface)
- `TagInterface` - Tag implementation interface
- `TagMapInterface` - Tag storage interface

### Common Patterns
```php
// Value processor (auto-wrapped)
fn($value) => $transformedValue

// State processor (full control)
fn(ProcessingState $state) => ProcessingState

// Middleware (unified interface)
class MyMiddleware implements CanProcessState {
    public function process(ProcessingState $state, ?callable $next = null): ProcessingState {
        // Pre-processing
        $result = $next ? $next($state) : $state;
        // Post-processing
        return $result;
    }
}
```

## Timing & Memory Tracking

### Pipeline-Level Monitoring
```php
// Capture entire pipeline timing
$pipeline = Pipeline::builder()
    ->withMiddleware(Timing::capture('operation'))
    ->withMiddleware(Memory::capture('operation'))
    ->through($processor)
    ->create();

$result = $pipeline->executeWith($data);

// Extract data from execution state
$state = $result->state();
$timings = $state->allTags(TimingTag::class);
$memory = $state->allTags(MemoryTag::class);
```

### Step-Level Monitoring  
```php
// Capture timing/memory for each processor
$pipeline = Pipeline::builder()
    ->aroundEach(StepTiming::capture('processing'))    // Applied to ALL processors
    ->aroundEach(StepMemory::capture('processing'))    // Applied to ALL processors  
    ->through($validateProcessor)
    ->through($heavyProcessor)
    ->create();

$result = $pipeline->executeWith($data);

// Extract step data - one entry per processor
$state = $result->state();
$stepTimings = $state->allTags(StepTimingTag::class);  // 2 entries
$stepMemory = $state->allTags(StepMemoryTag::class);   // 2 entries
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
    $error = $result->exception()->getMessage();
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
$pending = $pipeline->executeWith($data);  // No execution yet (lazy)
$value = $pending->value();                 // Execute when needed
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