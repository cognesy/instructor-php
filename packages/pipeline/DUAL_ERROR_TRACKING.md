# Dual Error Tracking Architecture

The Pipeline package employs a dual error tracking system that separates business logic concerns from observability requirements. This document explains the model and architectural decisions.

## The Two Mechanisms

### Result::failure() - The Monadic Layer

```php
$result->isSuccess()           // Business logic: did computation succeed?
$result->exception()           // Original exception for debugging  
$result->errorMessage()        // Human-readable error for users
$result->map(fn($x) => $x * 2) // Functional composition
```

### ErrorTag - The Metadata Layer
```php
$state->firstTag(ErrorTag::class)  // Rich error context for middleware
$errorTag->timestamp               // When did error occur?
$errorTag->metadata['trace']       // Debugging information
$errorTag->context                 // Additional error context
```

## Architectural Separation

These mechanisms address **fundamentally different concerns**:

| Aspect | Result::failure()                        | ErrorTag |
|--------|------------------------------------------|----------|
| **Purpose** | Business logic flow control              | Observability & metadata |
| **Performance** | O(1) success checks                      | O(n) tag lookups |
| **Type Safety** | `Result<T>` with compile-time guarantees | `mixed` metadata |
| **Composition** | Mapping operations                       | Tag-based querying |
| **Consumer** | Application business logic               | Middleware & monitoring |

## Why Dual Tracking is Necessary

### 1. Clean Business Logic APIs
```php
// Simple success/failure decisions
$result = $pipeline->process($data);
if ($result->isFailure()) {
    return new ErrorResponse($result->errorMessage());
}
return new SuccessResponse($result->value());
```

### 2. Rich Observability Context
```php
// Detailed middleware analysis
$state = $result->state();
foreach ($state->allTags(ErrorTag::class) as $error) {
    $logger->error('Pipeline error', [
        'timestamp' => $error->timestamp,
        'category' => $error->category,
        'context' => $error->context,
    ]);
}
```

### 3. Performance Optimization
```php
// Fast path for business logic
if ($result->isSuccess()) {  // O(1) check
    return $this->processValue($result->value());
}

// Rich analysis only when needed
if ($this->isDebugging) {    // Expensive O(n) operations
    $errors = $state->allTags(ErrorTag::class);
    $this->analyzeErrors($errors);
}
```

## Implementation Invariants

### Required Invariant
**If `Result::isFailure()` is true, an `ErrorTag` must exist in the state.**

```php
// Pipeline ensures this relationship
private function handleProcessorError(ProcessingState $state, Exception $error): ProcessingState {
    return $state
        ->withResult(Result::failure($error))
        ->withTags(new ErrorTag($error, ['processor' => $this->currentProcessor]));
}
```

### Optional Pattern
**ErrorTag can exist without `Result::failure()` for warnings or debug info.**

```php
// Non-fatal warnings
$state = $state->withTags(new ErrorTag($warning, ['severity' => 'warning']));
// Result remains successful, but metadata captured
```

## Alternative Architecture Comparison

### Proposed Single-Error Model
```php
class ProcessingState {
    public function __construct(
        private mixed $value,           // Type safety lost
        private TagMapInterface $tags            // Performance cost for every check
    ) {}
    
    public function isSuccess(): bool {
        return !$this->tags->has(ErrorTag::class); // O(n) operation
    }
    
    public function asResult(): Result { // Dynamic generation - consistency risk
        return $this->isSuccess() 
            ? Result::success($this->value)
            : Result::failure($this->getError());
    }
}
```

**Problems:**
- **Performance Regression**: Every success check becomes O(n)
- **Type Safety Loss**: `mixed $value` eliminates compile-time guarantees  
- **Consistency Risk**: Dynamic Result generation may vary between calls
- **SRP Violation**: ProcessingState handles data, metadata, and business logic

### Current Dual Model
```php
class ProcessingState {
    public function __construct(
        private Result $result,         // Type-safe, O(1) operations
        private TagMapInterface $tags           // Rich metadata when needed
    ) {}
    
    public function isSuccess(): bool {
        return $this->result->isSuccess(); // O(1) operation
    }
    
    public function result(): Result {
        return $this->result;          // Direct access, no generation
    }
}
```

**Benefits:**
- **Performance**: O(1) business logic operations
- **Type Safety**: `Result<T>` maintains compile-time guarantees
- **Consistency**: Immutable Result state
- **Clean Separation**: Business logic vs. observability concerns

## Usage Guidelines

### Use Result::failure() for:
```php
// Business logic decisions
$result = $pipeline->process($data);
if ($result->isFailure()) {
    return $this->handleBusinessError($result);
}

// Functional composition
$transformed = $result
    ->map(fn($data) => $this->transform($data))
    ->map(fn($data) => $this->validate($data));
```

### Use ErrorTag for:
```php
// Observability and debugging
$state = $result->state();
$errorTag = $state->firstTag(ErrorTag::class);
if ($errorTag) {
    $this->metrics->incrementErrorCount($errorTag->category);
    $this->logger->error('Processing failed', $errorTag->context);
}

// Middleware coordination
class CircuitBreakerMiddleware {
    public function handle(ProcessingState $state, callable $next): ProcessingState {
        $result = $next($state);
        if ($result->hasTag(ErrorTag::class)) {
            $this->circuitBreaker->recordFailure();
        }
        return $result;
    }
}
```

## Industry Pattern Parallels

This dual approach follows established patterns:

**HTTP Responses:**
- Status Code (like Result): 200 OK, 404 Not Found, 500 Error
- Response Body (like ErrorTag): Detailed error context, debugging info

**Event Sourcing:**
- Event Outcome (like Result): Success/Failure for business logic
- Event Metadata (like ErrorTag): Timestamps, correlation IDs, observability context

## Key Principles

1. **Result focuses on "what happened"** for business logic decisions
2. **ErrorTag focuses on "how/why/when"** for observability systems
3. **Performance matters**: Business logic gets O(1) operations
4. **Type safety matters**: Compile-time guarantees prevent runtime errors
5. **Separation of concerns**: Independent evolution of business and observability logic

The dual error tracking system provides both the simplicity needed for business logic and the richness required for production observability systems, without compromising performance or type safety.