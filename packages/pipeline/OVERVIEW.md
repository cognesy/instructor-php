# Pipeline Package Internals Overview

## Architecture Foundation

The Pipeline package implements a **composable processing framework** built on immutable state management and middleware-driven execution. The system enables complex data transformations through layered abstraction with comprehensive observability.

## Core Interfaces & Contracts

### `CanCarryState` Interface
*Location: `packages/pipeline/src/Contracts/CanCarryState.php`*

The foundational state container contract that defines:

- **Factory Methods**: `empty()`, `with(mixed $value, array $tags)`
- **State Operations**: `withResult()`, `addTags()`, `replaceTags()`, `failWith()`
- **Result Access**: `result()`, `value()`, `isSuccess()`, `isFailure()`
- **Tag Operations**: `tagMap()`, `allTags()`, `hasTag()`, `tags()`

**Purpose**: Provides immutable state encapsulation with typed metadata support.

### `CanProcessState` Interface  
*Location: `packages/pipeline/src/Contracts/CanProcessState.php`*

The processing component contract defining:

```php
public function process(CanCarryState $state, ?callable $next = null): CanCarryState;
```

**Key Properties**:
- **Stateless Design**: Components should carry no internal state
- **Middleware Pattern**: `$next` continuation enables composition
- **Uniform API**: All processors implement identical interface

**Purpose**: Enables composable middleware-style processing chains.

## State Management System

### `ProcessingState` Implementation
*Location: `packages/pipeline/src/ProcessingState.php`*

Immutable implementation of `CanCarryState` containing:

- **Result Wrapper**: `Result<T>` monad for success/failure handling
- **Tag Storage**: `TagMapInterface` for typed metadata
- **Readonly Design**: Final readonly class prevents mutation
- **Factory Pattern**: Static constructors for common use cases

```php
// State creation and transformation
$state = ProcessingState::with($data, [$metadataTag]);
$newState = $state->addTags(new TimingTag())->withResult(Result::success($newValue));
```

### Tag System Architecture

#### `TagInterface` & Implementations
Tags provide **typed metadata attachment** for cross-cutting concerns:

- **Marker Interface**: Simple contract for type-based identification
- **Immutable Design**: Tags should be readonly data structures  
- **Built-in Types**: `ErrorTag`, `TimingTag`, `MemoryTag`, `SkipProcessingTag`

#### Tag Storage: Dual Implementation Strategy

**1. `IndexedTagMap`** *(Default)*
- **Sequential ID-based**: Generates unique IDs for each tag
- **Dual indexing**: `tagsById` + `insertionOrder` arrays
- **Memory efficient**: Lower overhead for general use
- **O(n) class lookup**: Linear search for type filtering

**2. `SimpleTagMap`** *(Type-optimized)*
- **Class-grouped storage**: `array<class-string, array<TagInterface>>`
- **O(1) class lookup**: Direct access by type
- **Higher memory usage**: Class-based indexing overhead
- **Better for type-heavy filtering**: Efficient tag queries

#### `TagQuery` Fluent API
*Location: `packages/pipeline/src/Tag/TagQuery.php`*

Provides **chainable tag operations**:

```php
$timingTags = $state->tags()
    ->ofType(TimingTag::class)
    ->filter(fn($tag) => $tag->duration > 1.0)
    ->limit(5)
    ->all();
```

**Operations**: `filter()`, `ofType()`, `only()`, `without()`, `map()`, `limit()`
**Terminals**: `all()`, `count()`, `has()`, `first()`, `last()`

## Pipeline Execution Engine

### `Pipeline` Class
*Location: `packages/pipeline/src/Pipeline.php`*

The main orchestrator implementing **four-layer architecture**:

```
Pipeline Middleware (wraps entire execution)
├── Steps (sequential processors)  
│   └── Hooks (per-step middleware)
└── Finalizers (cleanup operations)
```

#### Execution Layers

**1. Pipeline Middleware**: Applied once around entire chain
```php
$pipeline->withMiddleware(new TimingMiddleware()); // Wraps [Step1→Step2→Step3]
```

**2. Processing Steps**: Core business logic sequence
```php
$pipeline->through(fn($x) => $x * 2)->through(fn($x) => $x + 10);
```

**3. Step Hooks**: Applied to each individual step
```php
$pipeline->beforeEach(fn($state) => $state->addTags(new TraceTag()));
```

**4. Finalizers**: Always execute regardless of success/failure
```php
$pipeline->finally(fn($state) => $this->cleanup($state));
```

#### Middleware Composition Mechanism

The pipeline uses **recursive middleware composition** with continuation passing:

```php
private function applyStepsWithMiddleware(CanCarryState $state, OperatorStack $middleware, OperatorStack $steps, OperatorStack $hooks): CanCarryState 
{
    return $middleware->callStack(fn($state) => $this->applySteps($state, $steps, $hooks))($state);
}
```

**Key Features**:
- **Nested Composition**: Middleware wraps steps, hooks wrap individual processors
- **Short-circuiting**: Failures halt processing but preserve state
- **Error Strategy**: Configurable failure handling (`ContinueWithFailure`, `FailFast`)

### `PendingExecution` Lazy Evaluator
*Location: `packages/pipeline/src/PendingExecution.php`*

Provides **deferred execution with memoization**:

```php
$pending = $pipeline->executeWith($data);  // No execution yet
$value = $pending->value();                // Executes and caches result
$sameValue = $pending->value();            // Uses cached result
```

**Features**:
- **Lazy Evaluation**: Computation deferred until result needed
- **Result Caching**: Single execution with multiple access methods
- **Batch Processing**: `each()` method for iterable input processing
- **Stream Interface**: Generator-based result iteration

## Middleware System Deep Dive

### Middleware as CanProcessState Wrappers

The pipeline system enables **two-level middleware composition**:

#### 1. Pipeline-Level Middleware
Wraps the **entire processing chain** as a single unit:

```php
class TimingMiddleware implements CanProcessState 
{
    public function process(CanCarryState $state, ?callable $next = null): CanCarryState 
    {
        $start = microtime(true);
        $result = $next ? $next($state) : $state;
        $duration = microtime(true) - $start;
        return $result->addTags(new TimingTag($duration));
    }
}

$pipeline->withMiddleware(new TimingMiddleware()); // Times entire pipeline
```

#### 2. Step-Level Hooks
Wraps **individual processing steps**:

```php
$pipeline->aroundEach(StepTiming::capture()); // Times each step individually
```

### Middleware Use Cases & Examples

#### Cross-Cutting Concerns Implementation

**Observability Middleware**:
```php
class ObservabilityMiddleware implements CanProcessState
{
    public function process(CanCarryState $state, ?callable $next = null): CanCarryState 
    {
        $this->logger->info('Pipeline started');
        $this->metrics->increment('pipeline.started');
        
        $result = $next ? $next($state) : $state;
        
        $status = $result->isSuccess() ? 'success' : 'failure';
        $this->logger->info("Pipeline completed: {$status}");
        $this->metrics->increment("pipeline.{$status}");
        
        return $result;
    }
}
```

**Security Middleware**:
```php
class AuthenticationMiddleware implements CanProcessState
{
    public function process(CanCarryState $state, ?callable $next = null): CanCarryState 
    {
        $request = $state->value();
        if (!$this->isAuthenticated($request['token'])) {
            return $state->failWith('Authentication failed');
        }
        return $next ? $next($state) : $state;
    }
}
```

**Rate Limiting Middleware**:
```php
class RateLimitMiddleware implements CanProcessState
{
    public function process(CanCarryState $state, ?callable $next = null): CanCarryState 
    {
        $clientId = $state->value()['client_id'];
        if ($this->rateLimiter->isExceeded($clientId)) {
            return $state->failWith('Rate limit exceeded');
        }
        return $next ? $next($state) : $state;
    }
}
```

## Advanced Composition Patterns

### Conditional Processing
```php
$pipeline = Pipeline::builder()
    ->through($validation)
    ->when(
        fn($state) => $state->value()['requires_premium'],
        fn($state) => $this->premiumProcessing($state)
    )
    ->through($standardProcessing);
```

### Error Recovery Patterns
```php
$resilientPipeline = Pipeline::builder()
    ->through($primaryProcessor)
    ->onFailure($fallbackProcessor)
    ->withMiddleware(new CircuitBreakerMiddleware())
    ->withMiddleware(new RetryMiddleware(attempts: 3));
```

### Parallel Processing Coordination
```php
$orchestrator = Pipeline::builder()
    ->through($dataPreparation)
    ->split([
        'validation' => $validationPipeline,
        'enrichment' => $enrichmentPipeline,
        'transformation' => $transformationPipeline
    ])
    ->merge($resultCombiner);
```

## Production Use Cases

### 1. API Request Processing
```php
$apiPipeline = Pipeline::builder()
    ->withMiddleware(new AuthenticationMiddleware())
    ->withMiddleware(new RateLimitMiddleware())
    ->withMiddleware(new TimingMiddleware())
    ->through($inputValidation)
    ->through($businessLogic)
    ->through($responseFormatting)
    ->finally($cleanupResources);
```

### 2. Data ETL Operations  
```php
$etlPipeline = Pipeline::builder()
    ->through($dataExtraction)
    ->through($dataValidation)
    ->through($dataTransformation)
    ->through($dataLoad)
    ->withMiddleware(new ProgressTrackingMiddleware())
    ->withMiddleware(new MemoryMonitoringMiddleware())
    ->aroundEach(StepTiming::capture());
```

### 3. Event Stream Processing
```php
$eventProcessor = Pipeline::builder()
    ->through($eventValidation)
    ->through($eventEnrichment)
    ->when($shouldRoute, $eventRouting)
    ->tap($eventAudit)  // Side effect - doesn't affect main flow
    ->through($eventPersistence);
```

### 4. Machine Learning Pipeline
```php
$mlPipeline = Pipeline::builder()
    ->through($featureExtraction)
    ->through($dataPreprocessing)
    ->through($modelPrediction)
    ->through($resultPostprocessing)
    ->withMiddleware(new ModelPerformanceMiddleware())
    ->withMiddleware(new DataQualityMiddleware());
```

## Performance & Design Considerations

### Immutability Benefits
- **Thread Safety**: No shared mutable state
- **Predictable Behavior**: No side effects from state changes  
- **Debugging**: Clear state transitions
- **Testing**: Isolated, reproducible test scenarios

### Middleware Composition Efficiency  
- **Lazy Construction**: Middleware chains built once, executed many times
- **Memory Efficiency**: Shared instances across pipeline executions
- **Short-circuiting**: Failed steps prevent unnecessary downstream processing

### Tag System Performance
- **Indexed Access**: O(1) or O(log n) tag retrieval based on implementation
- **Type Safety**: Compile-time guarantees prevent runtime type errors
- **Memory Overhead**: Minimal metadata storage cost

## Key Integration Points

### Framework Integration
```php
// Laravel Service Provider
$this->app->singleton(PipelineFactory::class, function() {
    return new PipelineFactory([
        'observability' => new ObservabilityMiddleware(),
        'security' => new SecurityMiddleware(),
    ]);
});
```

### Testing Strategy
```php
class PipelineTest extends TestCase 
{
    public function testMiddlewareComposition() {
        $mockMiddleware = $this->createMock(CanProcessState::class);
        $pipeline = Pipeline::builder()
            ->withMiddleware($mockMiddleware)
            ->through($processor);
            
        $result = $pipeline->executeWith(ProcessingState::with($data));
        // Assert middleware was called...
    }
}
```

## Summary

The Pipeline package provides a **production-ready processing framework** that enables:

- **Composable Architecture**: Middleware and processors as building blocks
- **Rich Metadata System**: Type-safe observability through tags
- **Immutable State Management**: Predictable data flow with no side effects  
- **Flexible Error Handling**: Monadic result pattern with detailed error context
- **Performance Optimization**: Lazy evaluation with automatic memoization
- **Enterprise Patterns**: Authentication, monitoring, rate limiting, circuit breaking

The middleware wrapping capability transforms individual processing steps and entire pipelines into **observable, controllable, and maintainable** data processing systems suitable for complex enterprise applications.