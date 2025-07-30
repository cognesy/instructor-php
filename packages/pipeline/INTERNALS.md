# Pipeline Internals

## Architecture Overview

The Pipeline package implements a sophisticated processing chain using the middleware pattern combined with lazy evaluation. It consists of four main components working together to provide composable, observable, and fault-tolerant data processing.

## Core Components

### 1. Pipeline (The Orchestrator)
**File**: `src/Pipeline.php`
**Responsibility**: Configuration, coordination, and execution management

The Pipeline class serves as the main entry point and orchestrator. It maintains:
- **Source**: Callable or value that provides initial data
- **Processors**: Array of transformation functions
- **Finalizer**: Optional post-processing function  
- **Middleware Stack**: PipelineMiddlewareStack for pipeline-level cross-cutting concerns
- **Hook Stack**: PipelineMiddlewareStack for per-processor step-level concerns

Key internal methods:
- `suspendExecution()`: Wraps processors in closures for lazy evaluation
- `executeProcessor()`: Determines whether to use middleware or direct execution
- `executeProcessorDirect()`: Core processor execution with error handling
- `isComputationProcessor()`: Uses reflection to detect computation-aware processors
- `applyProcessors()`: Iterates through processor chain, short-circuiting on failures
- `createInitialComputation()`: Converts input values to Computation instances
- `shouldContinueProcessing()`: Consolidated flow control logic checking computation state
- `handleProcessorError()`: Consolidated error handling converting exceptions to failure computations

The Pipeline implements a dual middleware architecture:
- **Pipeline middleware** (`withMiddleware()`) wraps the entire processor chain
- **Per-processor hooks** (`beforeEach()`, `afterEach()`, etc.) wrap individual processors

### 2. Computation (The Message Container)
**File**: `src/Computation.php`
**Responsibility**: Message wrapping, tag management, immutability

The Computation is an immutable container that wraps a Result with metadata (tags). It provides separation between:
- **Value**: The actual computation result (success/failure)
- **Tags**: Metadata for cross-cutting concerns (timing, tracing, metrics)

Internal structure:
- `Result $result`: The wrapped computation result
- `TagMap $tags`: Indexed by class name for efficient retrieval

Key internal methods:
- Immutability enforced by `readonly` modifier and return-new-instance pattern
- TagMap handles indexing and provides O(1) access to tags by type

The tag indexing system allows O(1) access to tags by type while maintaining insertion order within each type.

### 3. PendingComputation (The Lazy Evaluator)
**File**: `src/PendingComputation.php`
**Responsibility**: Lazy evaluation, result extraction, transformation chaining

This class implements lazy evaluation with memoization. It wraps a computation closure and provides multiple result extraction methods:

Internal state:
- `Closure $deferred`: The wrapped computation
- `bool $executed`: Execution flag for memoization
- `mixed $cachedOutput`: Cached result after first execution

Key internal methods:
- `executeOnce()`: Ensures computation runs exactly once, caches result
- `getResultFromOutput()`: Extracts Result from various return types
- Transformation methods create new PendingComputation instances

The lazy evaluation ensures expensive computations only run when needed, while memoization prevents repeated execution.

### 4. PipelineMiddlewareStack (The Chain Builder)
**File**: `src/PipelineMiddlewareStack.php`
**Responsibility**: Middleware orchestration and chain construction

The middleware stack implements the classic chain of responsibility pattern:

Internal structure:
- `array $middleware`: Ordered list of middleware instances

Key internal method:
- `process()`: Uses `array_reduce` with array reversal to build execution chain

The chain building is crucial - middleware array is reversed so that first-added middleware executes outermost in the chain, creating the expected execution order.

## Data Flow

### 1. Pipeline Construction
```
Pipeline::for($value) → 
  Creates Pipeline with source callable → 
  Processors added via through/when/tap → 
  Middleware added via withMiddleware/hooks → 
  Ready for execution
```

### 2. Execution Flow
```
process($value, $tags) →
  PendingComputation created with computation closure →
  On first value() call: →
    getSourceValue() → 
    createInitialComputation($value, $tags) →
    Pipeline middleware wraps entire chain:
      middleware.process(computation, applyProcessors) →
        applyProcessors($computation) →
          For each processor:
            executeProcessor() →
              If per-processor hooks exist: hooks.process() →
              executeProcessorDirect() →
                Reflection check for computation vs value processor →
                Execute with error handling →
                Convert result to computation
            Short-circuit on failure
        Return processed computation
      Return wrapped computation
    applyFinalizer() →
    Return final computation
```

### 3. Middleware Chain Execution

**Pipeline Middleware (wraps entire chain):**
```
pipeline.middleware.process(computation, applyProcessors) →
  array_reduce builds chain from reversed middleware array →
  Each middleware wraps next in closure →
  Execution flows: MW1 → MW2 → MW3 → [P1→P2→P3] → MW3 → MW2 → MW1
```

**Per-Processor Hooks (wraps individual processors):**
```
hooks.process(computation, processor) →
  array_reduce builds chain from reversed hook array →
  Each hook wraps next in closure →
  Execution flows: H1 → H2 → H3 → processor → H3 → H2 → H1
  Applied separately for each processor: [P1], [P2], [P3]
```

## Error Handling Strategy

The error handling architecture follows the Result monad pattern with clear separation of responsibilities between pipeline infrastructure and middleware.

### Exception Handling Responsibilities

**Pipeline Infrastructure Responsibilities:**
- Catch processor exceptions in `executeProcessorDirect()`
- Convert exceptions to `Result::failure($exception)`
- Create `ErrorTag` with exception details for middleware inspection
- Ensure all outcomes are wrapped in Result instances (never throw to middleware)

**Middleware Responsibilities:**
- **Never catch processor exceptions** - they are handled by infrastructure
- Inspect computation state via `$computation->getResult()->isFailure()`
- Extract error details from `ErrorTag` when needed: `$computation->first(ErrorTag::class)`
- Focus on cross-cutting concerns (timing, logging, metrics) based on computation inspection

### Exception to Result Flow
```
Processor throws Exception → 
  Pipeline catches in executeProcessorDirect() →
  Creates Result::failure($exception) + ErrorTag →
  Returns failure computation to middleware →
  Middleware inspects computation state (never sees raw exception)
```

### Key Architectural Principle
**Middleware operates on Results, not exceptions.** The infrastructure ensures middleware always receives computations with Results, maintaining the monadic error handling pattern.

### Example: Correct Middleware Pattern
```php
public function handle(Computation $computation, callable $next): Computation {
    $result = $next($computation); // Never throws - returns computation
    
    // Inspect result state
    $success = $result->getResult()->isSuccess();
    
    // Extract error details if needed
    if (!$success) {
        $errorTag = $result->first(ErrorTag::class);
        $errorMessage = $errorTag?->getMessage() ?? 'Unknown error';
    }
    
    // Create appropriate tags based on inspection
    return $result->with(new MyTag($success, $errorMessage));
}
```

### Failure Propagation
- `shouldContinueProcessing()` checks `Result.isFailure()` before each processor
- Failure computations passed through without processing
- Tags preserved through failure states
- Short-circuit behavior maintains performance

## Dual Error Tracking Architecture

The pipeline employs two complementary error tracking mechanisms that address different architectural concerns. Understanding their distinction is crucial for proper usage.

### The Two Mechanisms

**1. Result::failure($error) - The Monadic Layer**

```php
$result->exception()           // Business logic: did computation succeed?
$result->exception()           // Original exception for debugging  
$result->errorMessage()        // Human-readable error for users
```

**2. ErrorTag - The Metadata Layer**
```php
$computation->first(ErrorTag::class)  // Rich error context for middleware
$errorTag->timestamp               // When did error occur?
$errorTag->metadata['trace']       // Debugging information
$errorTag->context                 // Additional error context
```

### Independent Architectural Concerns

These mechanisms are **not redundant** - they address fundamentally different aspects:

**Result::failure() Addresses:**
- **Monadic Composition**: Enables `map()`, `flatMap()`, railway-oriented programming
- **Business Logic Flow**: "Should processing continue or stop?"
- **Consumer API**: Simple success/failure check for pipeline users
- **Type Safety**: Compiler/IDE can enforce error handling

**ErrorTag Addresses:**
- **Observability**: Rich context for logging, monitoring, debugging
- **Middleware Coordination**: Cross-cutting concerns can inspect/react to errors
- **Multi-Error Scenarios**: Multiple errors can occur, multiple tags can exist
- **Structured Telemetry**: Serializable error metadata for external systems

### Usage Guidelines

**Use Result::failure() when:**
```php
// Business logic decisions
$result = $pipeline->process($data);
if ($result->isFailure()) {
    return new ErrorResponse($result->errorMessage());
}

// Monadic operations
$transformed = $result->map(fn($data) => transform($data));
```

**Use ErrorTag when:**
```php
// Observability and middleware coordination
$computation = $result->computation();

// Rich error analysis for logging
foreach ($computation->all(ErrorTag::class) as $error) {
    $logger->error('Pipeline error', [
        'timestamp' => $error->timestamp,
        'category' => $error->category,
        'context' => $error->context,
    ]);
}

// Middleware reactions
if ($computation->has(ErrorTag::class)) {
    $circuitBreaker->recordFailure();
    $metrics->incrementErrorCount();
}
```

### Industry Pattern Parallels

This dual approach follows established patterns:

**HTTP Responses:**
- Status Code (like Result): 200 OK, 404 Not Found, 500 Error
- Response Body (like ErrorTag): Detailed error context, debugging info

**Event Sourcing:**
- Event Outcome (like Result): Success/Failure for business logic
- Event Metadata (like ErrorTag): Timestamps, correlation IDs, observability context

### Architectural Invariants

**Key Principle**: Result focuses on "what happened" for business logic, ErrorTag focuses on "how/why/when" for observability.

**Required Invariant**: If `Result::isFailure()` is true, an `ErrorTag` must exist in the computation.

**Optional Pattern**: `ErrorTag` can exist without `Result::failure()` for warnings, debug information, or non-fatal issues.

### Benefits of Dual Approach

1. **Clean APIs**: Simple Result for business logic, rich ErrorTag for observability
2. **Middleware Power**: Cross-cutting concerns access rich error context without affecting business logic
3. **Monadic Composition**: Result enables functional programming patterns
4. **Structured Observability**: ErrorTag provides serializable telemetry data
5. **Independent Evolution**: Each mechanism can be enhanced without affecting the other

This design provides both the simplicity needed for business logic and the richness required for production observability systems.

## Processor Type Detection

The pipeline supports two processor types:
1. **Value processors**: `fn($value) -> $result`
2. **Computation processors**: `fn(Computation $computation) -> Computation`

Detection uses ReflectionFunction:
```php
private function isComputationProcessor(callable $processor): bool {
    $reflection = new ReflectionFunction($processor);
    $firstParam = $reflection->getParameters()[0] ?? null;
    return $firstParam?->getType()?->getName() === Computation::class;
}
```

## Null Handling Strategy

NullStrategy enum controls null value behavior:
- **Allow**: Wrap null in success Result
- **Fail**: Convert null to failure Result  
- **Skip**: Return computation unchanged

Implemented in `asResult()` and `handleNullResult()` methods.

## Tag System Architecture

### Indexing Strategy
Tags indexed by class name for O(1) access:
```php
[
    'TimestampTag' => [TimestampTag('start'), TimestampTag('end')],
    'MetricsTag' => [MetricsTag('cpu', 85.2)],
]
```

### Query Methods
- `all()`: Returns flat array of all tags or filtered by class
- `first()/last()`: Get first/last tag of specific type
- `has()`: Check existence of tag type
- `count()`: Count tags (total or by type)

### Immutability Implementation
All tag operations return new Computation instances:
- `with()`: Adds tags to copy
- `without()`: Removes tag types from copy
- `withResult()`: Changes Result but preserves tags

## Hook-to-Middleware Adaptation

Legacy hook methods converted to middleware:
- `beforeEach()` → `CallBeforeMiddleware`
- `afterEach()` → `CallAfterMiddleware`  
- `withTag()` → `AddTagsMiddleware`
- `finishWhen()` → `ConditionalMiddleware`
- `onFailure()` → `CallOnFailureMiddleware`

This provides backward compatibility while enabling modern middleware patterns.

## Memory and Performance Considerations

### Lazy Evaluation Benefits
- Computations only run when results needed
- Pipeline construction is cheap (just configuration)
- Multiple result extractions use cached values

### Immutability Costs
- Each computation operation creates new instance
- Tag arrays copied on modification
- Memory usage scales with tag count

### Middleware Chain Performance
- Chain built once, reused for all processors
- Closure creation overhead per processor
- Reflection cost for processor type detection

## Thread Safety

The pipeline is designed for single-threaded use but is stateless after construction:
- All state changes create new instances (immutability)
- No shared mutable state between pipeline instances
- PendingComputation memoization is instance-local

## Code Organization and Refactoring Insights

### Logic Consolidation Patterns

The Pipeline architecture employs consolidated logic methods to maintain clean separation of concerns:

**Flow Control Consolidation:**
- `shouldContinueProcessing(Computation $computation): bool` - Single decision point for processor chain continuation
- Replaces scattered `$computation->getResult()->isFailure()` checks throughout the codebase
- Enables easy modification of flow control logic in one location

**Error Handling Consolidation:**  
- `handleProcessorError(Computation $computation, mixed $error): Computation` - Unified error processing
- Consolidates exception-to-Result conversion, ErrorTag creation, and computation wrapping
- Eliminates code duplication across multiple error handling locations

### 80/20 Refactoring Principle

The architecture demonstrates effective application of the 80/20 rule:
- **20% effort**: Extracting scattered logic into focused private methods
- **80% benefit**: Dramatically improved readability, maintainability, and testability

### Architectural Coherence

The consolidation maintains high coherence through:
- **Single Responsibility**: Each method handles one specific concern
- **Clear Naming**: Method names describe the decision being made
- **Zero Behavior Change**: Refactoring preserves all existing functionality
- **Minimal Surface Area**: Changes only affect private implementation methods

This design enables future enhancements (custom flow controllers, pluggable error handlers) without breaking existing functionality.

## Extension Points

### Custom Middleware
Implement `PipelineMiddlewareInterface`:
```php
class CustomMiddleware implements PipelineMiddlewareInterface {
    public function handle(Computation $computation, callable $next): Computation {
        // Pre-processing
        $result = $next($computation);  
        // Post-processing
        return $result;
    }
}
```

### Custom Tags
Implement `TagInterface` (marker interface):
```php
class CustomTag implements TagInterface {
    public function __construct(public readonly mixed $data) {}
}
```

### Processor Patterns
Both value and computation processors supported:
```php
// Value processor
$pipeline->through(fn($x) => $x * 2);

// Computation processor  
$pipeline->through(fn(Computation $computation) => $computation->with(new Tag()));
```

This architecture provides a flexible, observable, and maintainable processing pipeline suitable for complex data transformation workflows.