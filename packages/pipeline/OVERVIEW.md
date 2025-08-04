# Pipeline Package Overview

The Pipeline package provides a sophisticated, production-ready processing framework for PHP applications. It implements composable data transformation chains with middleware support, lazy evaluation, and comprehensive observability features.

## Architecture Overview

The pipeline architecture follows a layered design with four core components working together to provide composable, observable, and fault-tolerant data processing:

```
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────┐
│ PipelineBuilder │ -> │ PendingExecution │ -> │ ProcessingState │
│  (Construction) │    │ (Lazy Evaluation)│    │  (State + Tags) │
└─────────────────┘    └──────────────────┘    └─────────────────┘
                                │
                                v
                        ┌─────────────┐
                        │   Pipeline  │
                        │ (Execution) │
                        └─────────────┘
```

## Key Concepts & Abstractions

### 1. ProcessingState - Immutable State Container

**File**: `src/ProcessingState.php`

ProcessingState is the fundamental data structure that flows through the entire pipeline. It wraps your data in a `Result<T>` monad and attaches metadata via a `TagMap`.

```php
// Creating processing state
$state = ProcessingState::with($data, [new TimingTag('start')]);

// Accessing data
$value = $state->value();           // Extract the wrapped value
$result = $state->result();         // Get the Result monad
$isSuccess = $state->isSuccess();   // Check processing status
```

**Essential Value**: Provides type-safe, immutable state management with rich metadata support. Enables clean separation between business data and observability concerns.

### 2. Pipeline - Execution Engine

**File**: `src/Pipeline.php`

The Pipeline orchestrates sequential processor execution with comprehensive middleware support and error handling.

```php
$pipeline = Pipeline::for($data)
    ->through(fn($x) => $x * 2)     // Value processor
    ->through(fn($x) => $x + 10)    // Another processor
    ->withMiddleware(new TimingMiddleware());

$result = $pipeline
    ->create()       // Create pending pipeline execution
    ->value();       // Execute and get result
```

**Essential Value**: Provides predictable, observable execution with automatic error handling, short-circuiting on failures, and performance optimization.

### 3. Processors - Data Transformers

**Directory**: `src/Processor/`

Processors are the building blocks that transform data. The pipeline supports two types:

- **Value Processors**: `fn($value) -> $newValue`
- **State Processors**: `fn(ProcessingState) -> ProcessingState`

```php
// Value processor (automatic wrapping)
$pipeline->through(fn($x) => $x * 2);

// State processor (full control)
$pipeline->through(fn(ProcessingState $state) => 
    $state->withTags(new MetricTag('processed'))
);
```

**Essential Value**: Enables composable data transformations while maintaining type safety and automatic error handling.

### 4. Middleware - Cross-Cutting Concerns

**Directory**: `src/Middleware/`
**Interface**: `CanControlStateProcessing`

Middleware provides a composable way to add cross-cutting concerns like logging, metrics, tracing, and validation.

```php
class LoggingMiddleware implements CanControlStateProcessing 
{
    public function handle(ProcessingState $state, callable $next): ProcessingState 
    {
        $this->logger->info('Processing started');
        $result = $next($state);
        $this->logger->info('Processing completed', ['success' => $result->isSuccess()]);
        return $result;
    }
}
```

**Essential Value**: Separates business logic from infrastructure concerns, enabling reusable, testable cross-cutting functionality.

### 5. Tags - Metadata System

**Directory**: `src/Tag/`
**Core**: `TagMap` class

Tags provide a type-safe, indexed metadata system for observability and middleware coordination.

```php
// Built-in tags
$state->withTags(new TimingTag('start', microtime(true)));
$state->withTags(new ErrorTag($exception));

// Custom tags
class MetricTag implements TagInterface {
    public function __construct(public readonly string $name, public readonly float $value) {}
}

// Querying tags
$timingTags = $state->allTags(TimingTag::class);
$firstError = $state->firstTag(ErrorTag::class);
```

**Essential Value**: Provides O(1) metadata access, enabling rich observability without affecting business logic performance.

### 6. PendingExecution - Lazy Evaluation

**File**: `src/PendingExecution.php`

PendingExecution implements lazy evaluation with memoization, ensuring expensive computations only run when needed.

```php
$pending = $pipeline->process($data);  // No execution yet

$value = $pending->value();           // Executes and caches
$state = $pending->state();           // Uses cached result
$stream = $pending->stream();         // Transform to generator
```

**Essential Value**: Optimizes performance by deferring execution and caching results, enabling efficient pipeline composition.

## Processor, Middleware & Hook Relationships

### Execution Hierarchy

```
Pipeline Middleware (wraps entire chain)
    ├── Processor 1
    │   └── Per-Processor Hooks (wrap individual processor)
    ├── Processor 2
    │   └── Per-Processor Hooks
    └── Processor N
        └── Per-Processor Hooks
```

### Dual Middleware Architecture

**Pipeline Middleware** - Applied once around the entire processor chain:
```php
$pipeline->withMiddleware(new TimingMiddleware());
// Wraps: [P1 → P2 → P3] as a unit
```

**Per-Processor Hooks** - Applied individually to each processor:
```php
$pipeline->beforeEach(fn($state) => $state->withTags(new TraceTag()));
// Wraps: [P1], [P2], [P3] separately
```

### Error Handling Architecture

The pipeline uses a **dual error tracking system**:

1. **Result::failure()** - Monadic error handling for business logic
2. **ErrorTag** - Rich error metadata for observability

```php
// Business logic check
if ($result->isFailure()) {
    return new ErrorResponse($result->errorMessage());
}

// Observability analysis
$errorTag = $state->firstTag(ErrorTag::class);
$logger->error('Pipeline failed', [
    'timestamp' => $errorTag->timestamp,
    'context' => $errorTag->context
]);
```

**Key Principle**: Middleware operates on Results, never raw exceptions. The infrastructure ensures exceptions are always converted to Results before reaching middleware.

## Typical Use Cases

### 1. Data Processing Pipelines

Transform and validate data through multiple stages:

```php
$pipeline = Pipeline::for($rawData)
    ->through(fn($data) => $this->validate($data))
    ->through(fn($data) => $this->normalize($data))
    ->through(fn($data) => $this->enrich($data))
    ->withMiddleware(new TimingMiddleware())
    ->withMiddleware(new LoggingMiddleware());
```

### 2. API Request Processing

Handle HTTP requests with validation, authentication, and response formatting:

```php
$pipeline = Pipeline::for($request)
    ->through(fn($req) => $this->authenticate($req))
    ->through(fn($req) => $this->validateInput($req))
    ->through(fn($req) => $this->processBusinessLogic($req))
    ->through(fn($req) => $this->formatResponse($req))
    ->withMiddleware(new RateLimitMiddleware())
    ->withMiddleware(new MetricsMiddleware());
```

### 3. Workflow Orchestration

Coordinate complex business processes with conditional execution:

```php
$workflow = Workflow::empty()
    ->through($orderValidationPipeline)
    ->when(fn($order) => $order['requires_inventory_check'])
        ->through($inventoryPipeline)
    ->through($paymentPipeline)
    ->when(fn($order) => $order['payment_status'] === 'success')
        ->through($fulfillmentPipeline);
```

### 4. ETL (Extract, Transform, Load) Operations

Process large datasets with observability:

```php
$etlPipeline = Pipeline::for($sourceData)
    ->through(fn($data) => $this->extract($data))
    ->through(fn($data) => $this->transform($data))
    ->through(fn($data) => $this->load($data))
    ->withMiddleware(new MemoryMonitorMiddleware())
    ->withMiddleware(new ProgressTrackingMiddleware());
```

## Component Necessity & Value

### Why ProcessingState?
- **Immutability**: Prevents accidental state mutations
- **Type Safety**: Compile-time guarantees about data structure
- **Metadata Separation**: Business data separate from observability concerns
- **Result Monad**: Predictable error handling without exceptions

### Why Middleware?
- **Cross-Cutting Concerns**: Logging, metrics, tracing without code duplication
- **Composability**: Mix and match concerns as needed
- **Testability**: Each middleware can be tested in isolation
- **Reusability**: Write once, use across multiple pipelines

### Why Lazy Evaluation?
- **Performance**: Expensive computations only run when needed
- **Memory Efficiency**: Results computed on-demand
- **Composability**: Chain operations without intermediate execution
- **Caching**: Automatic memoization prevents duplicate work

### Why Dual Error Tracking?
- **Clean APIs**: Simple Result for business logic decisions
- **Rich Observability**: Detailed ErrorTag for monitoring and debugging
- **Middleware Power**: Cross-cutting concerns access rich error context
- **Independent Evolution**: Each mechanism enhanced without affecting the other

## Key Takeaways for Developers

### 1. Embrace Immutability
```php
// ✅ Correct - creates new state
$newState = $state->withTags(new CustomTag());

// ❌ Incorrect - attempting mutation
$state->tags[] = new CustomTag(); // Won't work - readonly
```

### 2. Use Appropriate Processor Types
```php
// ✅ Value processor for simple transformations
$pipeline->through(fn($x) => $x * 2);

// ✅ State processor for metadata manipulation
$pipeline->through(fn($state) => $state->withTags(new MetricTag()));
```

### 3. Leverage Middleware for Cross-Cutting Concerns
```php
// ✅ Correct - separate concerns
$pipeline
    ->through($businessLogic)                    // Core logic
    ->withMiddleware(new TimingMiddleware())     // Observability
    ->withMiddleware(new LoggingMiddleware());   // Diagnostics
```

### 4. Handle Errors via Result Pattern
```php
// ✅ Check result status
$result = $pipeline->process($data);
if ($result->isFailure()) {
    $this->handleError($result->errorMessage());
}

// ✅ Access rich error context via tags
$errorTag = $result->state()->firstTag(ErrorTag::class);
```

### 5. Optimize Performance with Lazy Evaluation
```php
// ✅ Defer execution until needed
$pending = $pipeline->process($data);  // Fast - no execution

// Execute only when result is needed
if ($condition) {
    $value = $pending->value();  // Now executes
}
```

### 6. Use Workflows for Complex Orchestration
```php
// ✅ For complex conditional flows
$workflow = Workflow::empty()
    ->through($alwaysRun)
    ->when($condition, $conditionalPipeline)
    ->through($finalStep);
```

### 7. Monitor Performance and Behavior
```php
// ✅ Add observability without changing business logic
$pipeline
    ->withMiddleware(new TimingMiddleware())
    ->withMiddleware(new MemoryMonitorMiddleware())
    ->withMiddleware(new MetricsCollectionMiddleware());
```

## Essential Patterns

1. **Builder Pattern**: Use `Pipeline::for()` and `PipelineBuilder` for fluent construction
2. **Chain of Responsibility**: Middleware and processors form execution chains
3. **Result Monad**: All operations return Results for predictable error handling
4. **Lazy Evaluation**: Computations deferred until results needed
5. **Immutable State**: All state changes create new instances
6. **Tag-Based Metadata**: Type-safe, indexed metadata system

The Pipeline package transforms complex data processing workflows into composable, observable, and maintainable code while providing production-ready error handling and performance optimization.

## Advanced Composition: Workflows

### Overview

While Pipeline handles linear processing chains, **Workflow** orchestrates multiple pipelines into complex processing graphs with conditional execution, branching logic, and sophisticated control flow.

### Workflow Architecture

**File**: `src/Workflow/Workflow.php`

```
┌─────────────┐    ┌─────────────┐    ┌─────────────┐
│  Pipeline A │ -> │ Condition?  │ -> │  Pipeline B │
└─────────────┘    └─────────────┘    └─────────────┘
                           │
                           v
                   ┌─────────────┐
                   │  Pipeline C │ (always)
                   └─────────────┘
                           │
                           v
                   ┌─────────────┐
                   │ Tap Pipeline│ (side effect)
                   └─────────────┘
```

### Core Workflow Operations

#### 1. Sequential Execution (`through`)

Execute pipelines in sequence, passing state between them:

```php
$workflow = Workflow::empty()
    ->through($validationPipeline)
    ->through($transformationPipeline)
    ->through($persistencePipeline);
```

#### 2. Conditional Execution (`when`)

Execute pipelines based on runtime conditions:

```php
$workflow = Workflow::empty()
    ->through($orderValidation)
    ->when(
        fn($state) => $state->value()['total'] > 1000,
        $premiumProcessingPipeline
    )
    ->when(
        fn($state) => $state->value()['requires_approval'],
        $approvalWorkflow
    );
```

#### 3. Side Effects (`tap`)

Execute pipelines for side effects without affecting main flow:

```php
$workflow = Workflow::empty()
    ->through($businessLogicPipeline)
    ->tap($auditLoggingPipeline)     // Audit - doesn't affect result
    ->tap($metricsCollectionPipeline) // Metrics - doesn't affect result
    ->through($responseFormattingPipeline);
```

### Workflow Step Types

#### ThroughStep
**File**: `src/Workflow/ThroughStep.php`

Executes a pipeline and passes its result to the next step:

```php
class ThroughStep implements CanProcessState 
{
    public function process(ProcessingState $state): ProcessingState {
        return $this->step->process($state);
    }
}
```

#### ConditionalStep
**File**: `src/Workflow/ConditionalStep.php`

Executes a pipeline only if condition is met:

```php
class ConditionalStep implements CanProcessState 
{
    public function process(ProcessingState $state): ProcessingState {
        return match(true) {
            ($this->condition)($state) => $this->step->process($state),
            default => $state, // Skip execution
        };
    }
}
```

#### TapStep
**File**: `src/Workflow/TapStep.php`

Executes a pipeline for side effects, ignoring its result:

```php
class TapStep implements CanProcessState 
{
    public function process(ProcessingState $state): ProcessingState {
        $this->step->process($state); // Execute but ignore result
        return $state; // Return original state
    }
}
```

### Complex Processing Graph Examples

#### 1. E-commerce Order Processing

**Example**: `examples/WorkflowExample.php`

```php
$orderWorkflow = Workflow::empty()
    // Always validate
    ->through($validationPipeline)
    
    // Check inventory only if validation passed
    ->when(
        fn($state) => $state->isSuccess(),
        $inventoryPipeline
    )
    
    // Process payment only for orders > $50
    ->when(
        fn($state) => $state->isSuccess() && $state->value()['total'] > 50,
        $paymentPipeline
    )
    
    // Create order record
    ->through($fulfillmentPipeline)
    
    // Always audit, regardless of outcome
    ->tap($auditPipeline);

$result = $orderWorkflow->process($orderData);
```

#### 2. Content Processing Pipeline

```php
$contentWorkflow = Workflow::empty()
    // Basic content validation
    ->through($contentValidationPipeline)
    
    // Image processing (only if content has images)
    ->when(
        fn($state) => !empty($state->value()['images']),
        $imageProcessingPipeline
    )
    
    // Video processing (only if content has videos)
    ->when(
        fn($state) => !empty($state->value()['videos']),
        $videoProcessingPipeline
    )
    
    // SEO optimization
    ->through($seoOptimizationPipeline)
    
    // Analytics tracking (side effect)
    ->tap($analyticsTrackingPipeline)
    
    // Content publishing
    ->through($publishingPipeline);
```

#### 3. API Request Processing Graph

```php
$apiWorkflow = Workflow::empty()
    // Authentication (always required)
    ->through($authenticationPipeline)
    
    // Rate limiting check
    ->when(
        fn($state) => $state->isSuccess(),
        $rateLimitingPipeline
    )
    
    // Input validation
    ->through($inputValidationPipeline)
    
    // Business logic (different pipelines for different endpoints)
    ->when(
        fn($state) => $state->value()['endpoint'] === 'users',
        $userManagementPipeline
    )
    ->when(
        fn($state) => $state->value()['endpoint'] === 'orders',
        $orderManagementPipeline
    )
    
    // Response formatting
    ->through($responseFormattingPipeline)
    
    // Logging (side effect)
    ->tap($requestLoggingPipeline);
```

### Workflow vs Pipeline: When to Use What

#### Use Pipeline When:
- **Linear processing**: Sequential transformations
- **Simple flow**: No conditional branching needed
- **Single concern**: Processing one type of data
- **Performance critical**: Minimal overhead needed

```php
// ✅ Perfect for Pipeline
$dataTransformation = Pipeline::for($rawData)
    ->through($normalize)
    ->through($validate)
    ->through($transform)
    ->through($serialize);
```

#### Use Workflow When:
- **Complex orchestration**: Multiple pipelines coordination
- **Conditional logic**: Branching based on runtime data
- **Multiple concerns**: Different processing paths
- **Enterprise workflows**: Business process automation

```php
// ✅ Perfect for Workflow
$businessProcess = Workflow::empty()
    ->through($dataValidation)
    ->when($needsApproval, $approvalProcess)
    ->when($isHighValue, $specialHandling)
    ->tap($auditLogging)
    ->through($finalProcessing);
```

### Error Handling in Workflows

Workflows implement **fail-fast semantics**:

1. **Sequential Steps**: If any `through` step fails, remaining steps are skipped
2. **Conditional Steps**: Failed conditions simply skip their pipelines
3. **Tap Steps**: Always execute, but failures don't affect main flow
4. **Error Propagation**: Original error context preserved through workflow execution

```php
$robustWorkflow = Workflow::empty()
    ->through($criticalValidation)      // Fail here stops everything
    ->when($condition, $optionalStep)   // Skip if condition fails
    ->tap($alwaysRunLogging)           // Runs even if main flow failed
    ->through($finalProcessing);       // Only runs if no prior failures
```

### Performance Considerations

#### Workflow Optimization Strategies:

1. **Condition Placement**: Put expensive conditions after cheap ones
2. **Pipeline Reuse**: Cache and reuse pipeline instances
3. **Lazy Evaluation**: Use PendingExecution for deferred computation
4. **Middleware Efficiency**: Apply middleware at pipeline level, not workflow level

```php
// ✅ Optimized workflow structure
$optimizedWorkflow = Workflow::empty()
    ->through($cheapValidationPipeline)     // Fast validation first
    ->when($cheapCondition, $expensiveStep) // Cheap condition first
    ->tap($efficientLoggingPipeline)        // Minimal overhead logging
    ->through($finalProcessingPipeline);
```

### Testing Workflows

#### Unit Testing Individual Steps:

```php
class OrderWorkflowTest extends TestCase 
{
    public function testValidationStep() {
        $validationPipeline = $this->createValidationPipeline();
        $result = $validationPipeline->process($invalidOrder);
        
        $this->assertTrue($result->isFailure());
        $this->assertStringContains('validation', $result->errorMessage());
    }
    
    public function testConditionalExecution() {
        $workflow = Workflow::empty()
            ->when(fn($state) => $state->value()['total'] > 100, $expensivePipeline);
            
        $cheapOrder = ProcessingState::with(['total' => 50]);
        $result = $workflow->process($cheapOrder);
        
        // Expensive pipeline should not have executed
        $this->assertFalse($result->hasTag(ExpensiveProcessingTag::class));
    }
}
```

#### Integration Testing Complete Workflows:

```php
public function testCompleteOrderWorkflow() {
    $order = $this->createValidOrder();
    $result = $this->orderWorkflow->process($order);
    
    $this->assertTrue($result->isSuccess());
    $this->assertTrue($result->value()['validated']);
    $this->assertTrue($result->value()['payment_processed']);
    $this->assertNotEmpty($result->allTags(TimingTag::class));
}
```

### Best Practices for Workflow Design

#### 1. Design for Observability
```php
$workflow = Workflow::empty()
    ->through($businessLogic)
    ->tap($metricsCollection)    // Always measure
    ->tap($auditLogging);        // Always audit
```

#### 2. Fail Fast, Recover Gracefully
```php
$workflow = Workflow::empty()
    ->through($criticalValidation)  // Fail fast on critical errors
    ->when($canRecover, $recoveryPipeline)  // Attempt recovery
    ->tap($errorNotification);     // Always notify on issues
```

#### 3. Compose Reusable Workflows
```php
$baseOrderProcessing = Workflow::empty()
    ->through($validation)
    ->through($inventory);

$premiumOrderWorkflow = clone $baseOrderProcessing
    ->through($premiumServices)
    ->through($expeditedShipping);

$standardOrderWorkflow = clone $baseOrderProcessing
    ->through($standardServices);
```

#### 4. Optimize for Maintainability
```php
// ✅ Clear, self-documenting workflow
$ecommerceWorkflow = Workflow::empty()
    ->through($this->createValidationStage())
    ->when($this->requiresInventoryCheck(), $this->createInventoryStage())
    ->when($this->requiresPayment(), $this->createPaymentStage())
    ->through($this->createFulfillmentStage())
    ->tap($this->createAuditStage());
```

### Key Workflow Takeaways

1. **Orchestration Layer**: Workflows coordinate multiple pipelines
2. **Conditional Execution**: Runtime decisions determine execution paths
3. **Side Effect Management**: Tap steps for non-affecting operations
4. **Fail-Fast Semantics**: Errors stop main flow but preserve context
5. **Pipeline Composition**: Reuse existing pipelines as building blocks
6. **Enterprise Ready**: Supports complex business process automation

Workflows enable sophisticated processing graphs while maintaining the Pipeline package's core principles of immutability, observability, and predictable error handling.