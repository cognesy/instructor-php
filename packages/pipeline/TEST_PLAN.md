# Pipeline Package Test Plan

## Classes to Test

### 1. Pipeline.php
**Public Methods:**
- `make()` - Static factory
- `from(callable)` - Static factory with source
- `for(mixed)` - Static factory with value
- `through(callable, NullStrategy)` - Add processor
- `when(callable, callable)` - Conditional processor
- `tap(callable)` - Side effect
- `then(?callable)` - Set finalizer
- `process(mixed, array)` - Execute pipeline (returns PendingPipelineExecution)
- `stream(iterable)` - Process stream
- `withMiddleware(PipelineMiddlewareInterface...)` - Add middleware
- `prependMiddleware(PipelineMiddlewareInterface...)` - Prepend middleware
- `withStamp(StampInterface...)` - Add stamps
- `beforeEach(callable)` - Hook before processors
- `afterEach(callable)` - Hook after processors
- `finishWhen(callable)` - Early termination condition
- `onFailure(callable)` - Failure handler

### 2. Envelope.php
**Public Methods:**
- `__construct(Result, array)` - Constructor
- `wrap(mixed, array)` - Static factory
- `getResult()` - Get wrapped Result
- `with(StampInterface...)` - Add stamps
- `withMessage(Result)` - Replace Result
- `without(string...)` - Remove stamps by class
- `all(?string)` - Get stamps (all or filtered)
- `last(string)` - Get last stamp of type
- `first(string)` - Get first stamp of type
- `has(string)` - Check if has stamp type
- `count(?string)` - Count stamps

### 3. PendingPipelineExecution.php
**Public Methods:**
- `__construct(callable)` - Constructor
- `value()` - Get unwrapped value
- `result()` - Get Result object
- `envelope()` - Get full Envelope
- `stream()` - Get as generator
- `success()` - Check if successful
- `failure()` - Get failure reason
- `mapEnvelope(callable)` - Transform envelope
- `map(callable)` - Transform value
- `then(callable)` - Chain computation

## Test Strategy

### Unit Tests
Focus on individual class methods in isolation:

1. **Pipeline Unit Tests** (`PipelineTest.php`)
   - Factory method behavior
   - Processor addition and execution
   - Middleware integration
   - Hook system functionality
   - Error handling

2. **Envelope Unit Tests** (`EnvelopeTest.php`)
   - Construction and wrapping
   - Stamp management (add, remove, query)
   - Immutability
   - Result message handling

3. **PendingPipelineExecution Unit Tests** (`PendingPipelineExecutionTest.php`)
   - Lazy execution behavior
   - Result extraction methods
   - Transformation operations
   - Error scenarios

### Feature Tests
Test complete workflows and integration:

1. **Pipeline Feature Tests** (`PipelineFeatureTest.php`)
   - End-to-end pipeline execution
   - Complex middleware chains
   - Stream processing
   - Real-world scenarios with multiple processors

2. **Envelope Feature Tests** (`EnvelopeFeatureTest.php`)
   - Stamp lifecycle through pipeline
   - Complex stamp interactions
   - Message transformation with stamps

3. **PendingExecution Feature Tests** (`PendingExecutionFeatureTest.php`)
   - Complex transformation chains
   - Integration with Pipeline execution
   - Stream processing scenarios

## Test Priorities

**High Priority:**
- Core Pipeline execution flow
- Envelope stamp management
- PendingExecution result extraction
- Error handling and failure scenarios

**Medium Priority:**
- Middleware integration
- Hook system compatibility
- Stream processing
- Complex transformation chains

**Lower Priority:**
- Edge cases with null values
- Performance characteristics
- Memory usage patterns

## Mock/Stub Strategy

- Use simple test stamps for stamp-related tests
- Create minimal middleware implementations for middleware tests
- Use callable objects for processor testing
- Mock external dependencies where needed