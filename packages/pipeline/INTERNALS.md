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
- **Middleware Stack**: PipelineMiddlewareStack instance for cross-cutting concerns

Key internal methods:
- `suspendExecution()`: Wraps processors in closures for lazy evaluation
- `executeProcessor()`: Determines whether to use middleware or direct execution
- `executeProcessorDirect()`: Core processor execution with error handling
- `isEnvelopeProcessor()`: Uses reflection to detect envelope-aware processors
- `applyProcessors()`: Iterates through processor chain, short-circuiting on failures
- `createInitialEnvelope()`: Converts input values to Envelope instances
- `shouldContinueProcessing()`: Consolidated flow control logic checking envelope state
- `handleProcessorError()`: Consolidated error handling converting exceptions to failure envelopes

The Pipeline implements both modern middleware patterns and legacy hook compatibility through middleware adapters.

### 2. Envelope (The Message Container)
**File**: `src/Envelope.php`
**Responsibility**: Message wrapping, stamp management, immutability

The Envelope is an immutable container that wraps a Result with metadata (stamps). It provides separation between:
- **Payload**: The actual computation result (success/failure)
- **Stamps**: Metadata for cross-cutting concerns (timing, tracing, metrics)

Internal structure:
- `Result $payload`: The wrapped computation result
- `array $stamps`: Indexed by class name for efficient retrieval

Key internal methods:
- `indexStamps()`: Creates class-name indexed stamp arrays
- Immutability enforced by `readonly` modifier and return-new-instance pattern

The stamp indexing system allows O(1) access to stamps by type while maintaining insertion order within each type.

### 3. PendingPipelineExecution (The Lazy Evaluator)
**File**: `src/PendingPipelineExecution.php`
**Responsibility**: Lazy evaluation, result extraction, transformation chaining

This class implements lazy evaluation with memoization. It wraps a computation closure and provides multiple result extraction methods:

Internal state:
- `callable $computation`: The wrapped computation
- `bool $executed`: Execution flag for memoization
- `mixed $cachedResult`: Cached result after first execution

Key internal methods:
- `executeOnce()`: Ensures computation runs exactly once, caches result
- `getResultFromEnvelope()`: Extracts Result from various return types
- Transformation methods create new PendingPipelineExecution instances

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
process($value, $stamps) →
  PendingPipelineExecution created with computation closure →
  On first value() call: →
    getSourceValue() → 
    createInitialEnvelope($value, $stamps) →
    applyProcessors($envelope) →
      For each processor:
        executeProcessor() →
          If middleware exists: middleware.process() →
          executeProcessorDirect() →
            Reflection check for envelope vs value processor →
            Execute with error handling →
            Convert result to envelope
        Short-circuit on failure
    applyFinalizer() →
    Return final envelope
```

### 3. Middleware Chain Execution
```
middleware.process(envelope, finalProcessor) →
  array_reduce builds chain from reversed middleware array →
  Each middleware wraps next in closure →
  Execution flows: MW1 → MW2 → MW3 → finalProcessor → MW3 → MW2 → MW1
```

## Error Handling Strategy

The error handling architecture follows the Result monad pattern with clear separation of responsibilities between pipeline infrastructure and middleware.

### Exception Handling Responsibilities

**Pipeline Infrastructure Responsibilities:**
- Catch processor exceptions in `executeProcessorDirect()`
- Convert exceptions to `Result::failure($exception)`
- Create `ErrorStamp` with exception details for middleware inspection
- Ensure all outcomes are wrapped in Result instances (never throw to middleware)

**Middleware Responsibilities:**
- **Never catch processor exceptions** - they are handled by infrastructure
- Inspect envelope state via `$envelope->getResult()->isFailure()`
- Extract error details from `ErrorStamp` when needed: `$envelope->first(ErrorStamp::class)`
- Focus on cross-cutting concerns (timing, logging, metrics) based on envelope inspection

### Exception to Result Flow
```
Processor throws Exception → 
  Pipeline catches in executeProcessorDirect() →
  Creates Result::failure($exception) + ErrorStamp →
  Returns failure envelope to middleware →
  Middleware inspects envelope state (never sees raw exception)
```

### Key Architectural Principle
**Middleware operates on Results, not exceptions.** The infrastructure ensures middleware always receives envelopes with Results, maintaining the monadic error handling pattern.

### Example: Correct Middleware Pattern
```php
public function handle(Envelope $envelope, callable $next): Envelope {
    $result = $next($envelope); // Never throws - returns envelope
    
    // Inspect result state
    $success = $result->getResult()->isSuccess();
    
    // Extract error details if needed
    if (!$success) {
        $errorStamp = $result->first(ErrorStamp::class);
        $errorMessage = $errorStamp?->getMessage() ?? 'Unknown error';
    }
    
    // Create appropriate stamps based on inspection
    return $result->with(new MyStamp($success, $errorMessage));
}
```

### Failure Propagation
- `shouldContinueProcessing()` checks `Result.isFailure()` before each processor
- Failure envelopes passed through without processing
- Stamps preserved through failure states
- Short-circuit behavior maintains performance

## Dual Error Tracking Architecture

The pipeline employs two complementary error tracking mechanisms that address different architectural concerns. Understanding their distinction is crucial for proper usage.

### The Two Mechanisms

**1. Result::failure($error) - The Monadic Layer**
```php
$result->isFailure()           // Business logic: did computation succeed?
$result->exception()           // Original exception for debugging  
$result->errorMessage()        // Human-readable error for users
```

**2. ErrorStamp - The Metadata Layer**
```php
$envelope->first(ErrorStamp::class)  // Rich error context for middleware
$errorStamp->getTimestamp()          // When did error occur?
$errorStamp->getStackTrace()         // Debugging information
$errorStamp->getContext()            // Additional error context
```

### Independent Architectural Concerns

These mechanisms are **not redundant** - they address fundamentally different aspects:

**Result::failure() Addresses:**
- **Monadic Composition**: Enables `map()`, `flatMap()`, railway-oriented programming
- **Business Logic Flow**: "Should processing continue or stop?"
- **Consumer API**: Simple success/failure check for pipeline users
- **Type Safety**: Compiler/IDE can enforce error handling

**ErrorStamp Addresses:**
- **Observability**: Rich context for logging, monitoring, debugging
- **Middleware Coordination**: Cross-cutting concerns can inspect/react to errors
- **Multi-Error Scenarios**: Multiple errors can occur, multiple stamps can exist
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

**Use ErrorStamp when:**
```php
// Observability and middleware coordination
$envelope = $result->envelope();

// Rich error analysis for logging
foreach ($envelope->all(ErrorStamp::class) as $error) {
    $logger->error('Pipeline error', [
        'timestamp' => $error->getTimestamp(),
        'processor' => $error->getProcessorName(),
        'context' => $error->getContext(),
    ]);
}

// Middleware reactions
if ($envelope->has(ErrorStamp::class)) {
    $circuitBreaker->recordFailure();
    $metrics->incrementErrorCount();
}
```

### Industry Pattern Parallels

This dual approach follows established patterns:

**HTTP Responses:**
- Status Code (like Result): 200 OK, 404 Not Found, 500 Error
- Response Body (like ErrorStamp): Detailed error context, debugging info

**Event Sourcing:**
- Event Outcome (like Result): Success/Failure for business logic
- Event Metadata (like ErrorStamp): Timestamps, correlation IDs, observability context

### Architectural Invariants

**Key Principle**: Result focuses on "what happened" for business logic, ErrorStamp focuses on "how/why/when" for observability.

**Required Invariant**: If `Result::isFailure()` is true, an `ErrorStamp` must exist in the envelope.

**Optional Pattern**: `ErrorStamp` can exist without `Result::failure()` for warnings, debug information, or non-fatal issues.

### Benefits of Dual Approach

1. **Clean APIs**: Simple Result for business logic, rich ErrorStamp for observability
2. **Middleware Power**: Cross-cutting concerns access rich error context without affecting business logic
3. **Monadic Composition**: Result enables functional programming patterns
4. **Structured Observability**: ErrorStamp provides serializable telemetry data
5. **Independent Evolution**: Each mechanism can be enhanced without affecting the other

This design provides both the simplicity needed for business logic and the richness required for production observability systems.

## Processor Type Detection

The pipeline supports two processor types:
1. **Value processors**: `fn($value) -> $result`
2. **Envelope processors**: `fn(Envelope $env) -> Envelope`

Detection uses ReflectionFunction:
```php
private function isEnvelopeProcessor(callable $processor): bool {
    $reflection = new ReflectionFunction($processor);
    $firstParam = $reflection->getParameters()[0] ?? null;
    return $firstParam?->getType()?->getName() === Envelope::class;
}
```

## Null Handling Strategy

NullStrategy enum controls null value behavior:
- **Allow**: Wrap null in success Result
- **Fail**: Convert null to failure Result  
- **Skip**: Return envelope unchanged

Implemented in `asResult()` and `handleNullResult()` methods.

## Stamp System Architecture

### Indexing Strategy
Stamps indexed by class name for O(1) access:
```php
[
    'TimestampStamp' => [TimestampStamp('start'), TimestampStamp('end')],
    'MetricsStamp' => [MetricsStamp('cpu', 85.2)],
]
```

### Query Methods
- `all()`: Returns flat array of all stamps or filtered by class
- `first()/last()`: Get first/last stamp of specific type
- `has()`: Check existence of stamp type
- `count()`: Count stamps (total or by type)

### Immutability Implementation
All stamp operations return new Envelope instances:
- `with()`: Adds stamps to copy
- `without()`: Removes stamp types from copy
- `withMessage()`: Changes Result but preserves stamps

## Hook-to-Middleware Adaptation

Legacy hook methods converted to middleware:
- `beforeEach()` → `CallBeforeMiddleware`
- `afterEach()` → `CallAfterMiddleware`  
- `withStamp()` → `AddStampsMiddleware`
- `finishWhen()` → `ConditionalMiddleware`
- `onFailure()` → `CallOnFailureMiddleware`

This provides backward compatibility while enabling modern middleware patterns.

## Memory and Performance Considerations

### Lazy Evaluation Benefits
- Computations only run when results needed
- Pipeline construction is cheap (just configuration)
- Multiple result extractions use cached values

### Immutability Costs
- Each envelope operation creates new instance
- Stamp arrays copied on modification
- Memory usage scales with stamp count

### Middleware Chain Performance
- Chain built once, reused for all processors
- Closure creation overhead per processor
- Reflection cost for processor type detection

## Thread Safety

The pipeline is designed for single-threaded use but is stateless after construction:
- All state changes create new instances (immutability)
- No shared mutable state between pipeline instances
- PendingPipelineExecution memoization is instance-local

## Code Organization and Refactoring Insights

### Logic Consolidation Patterns

The Pipeline architecture employs consolidated logic methods to maintain clean separation of concerns:

**Flow Control Consolidation:**
- `shouldContinueProcessing(Envelope $envelope): bool` - Single decision point for processor chain continuation
- Replaces scattered `$envelope->getResult()->isFailure()` checks throughout the codebase
- Enables easy modification of flow control logic in one location

**Error Handling Consolidation:**  
- `handleProcessorError(Envelope $envelope, mixed $error): Envelope` - Unified error processing
- Consolidates exception-to-Result conversion, ErrorStamp creation, and envelope wrapping
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
    public function handle(Envelope $envelope, callable $next): Envelope {
        // Pre-processing
        $result = $next($envelope);  
        // Post-processing
        return $result;
    }
}
```

### Custom Stamps
Implement `StampInterface` (marker interface):
```php
class CustomStamp implements StampInterface {
    public function __construct(public readonly mixed $data) {}
}
```

### Processor Patterns
Both value and envelope processors supported:
```php
// Value processor
$pipeline->through(fn($x) => $x * 2);

// Envelope processor  
$pipeline->through(fn(Envelope $env) => $env->with(new Stamp()));
```

This architecture provides a flexible, observable, and maintainable processing pipeline suitable for complex data transformation workflows.