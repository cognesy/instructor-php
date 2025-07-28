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

### Exception Capture
- Processor exceptions caught in `executeProcessorDirect()`
- Converted to Failure Result with ErrorStamp
- Pipeline continues with failure envelope (no further processing)

### Failure Propagation
- Result.isFailure() checked before each processor
- Failure envelopes passed through without processing
- Stamps preserved through failure states

### Middleware Error Handling
- Middleware exceptions bubble up to processor level
- Individual middleware can catch and handle their own errors
- CallBeforeMiddleware example shows local error recovery

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