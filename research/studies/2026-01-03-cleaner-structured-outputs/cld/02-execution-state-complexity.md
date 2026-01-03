# P1: Execution State Complexity

## Problem Statement

`StructuredOutputExecution` is an overloaded immutable state container that mixes:
- Request metadata
- Configuration
- Response model
- Attempt tracking
- Streaming state
- Success/failure tracking

This creates a "God Object" that everything depends on and constantly mutates (immutably).

## Evidence

### 1. Constructor Complexity

```php
// StructuredOutputExecution.php - 12 constructor parameters
public function __construct(
    ?StructuredOutputRequest $request = null,
    ?StructuredOutputConfig $config = null,
    ?ResponseModel $responseModel = null,
    ?StructuredOutputAttemptList $attempts = null,
    ?StructuredOutputAttempt $currentAttempt = null,
    ?bool $isFinalized = null,
    ?StructuredOutputAttemptState $attemptState = null,
    ?string $id = null,
    ?int $step = null,
    ?DateTimeImmutable $createdAt = null,
    ?DateTimeImmutable $updatedAt = null,
)
```

### 2. State Nesting Depth

```
StructuredOutputExecution
├── StructuredOutputRequest
├── StructuredOutputConfig
├── ResponseModel
│   └── OutputFormat
│   └── StructuredOutputConfig (duplicate!)
├── StructuredOutputAttemptList
│   └── StructuredOutputAttempt[]
│       └── InferenceExecution
│           └── InferenceAttempt
│               └── InferenceResponse
│               └── PartialInferenceResponse
├── StructuredOutputAttempt (current)
│   └── InferenceExecution
│       └── ... (same nesting)
└── StructuredOutputAttemptState
    └── Generator (stream)
    └── PartialInferenceResponse
    └── InferenceResponse
```

### 3. Mutation Methods Explosion

The class has 8+ `with*` mutator methods, each creating a new instance:

```php
public function withStreamed(bool $isStreamed = true) : self
public function withCurrentAttempt(...) : self
public function withFailedAttempt(...) : self
public function withSuccessfulAttempt(...) : self
public function with(...) : self  // 7 optional parameters!
public function withAttemptState(?StructuredOutputAttemptState $state): self
```

### 4. Mixed Responsibilities

The class handles:
1. **Request tracking** - `request()`, `responseModel()`, `config()`, `outputMode()`
2. **Attempt management** - `attempts()`, `currentAttempt()`, `attemptCount()`
3. **State queries** - `isFinalized()`, `isStreamed()`, `isAttemptActive()`, `maxRetriesReached()`
4. **Error aggregation** - `errors()`, `currentErrors()`
5. **Usage tracking** - `usage()`
6. **Success/failure** - `isSuccessful()`, `isFinalFailed()`
7. **Streaming state** - `attemptState()`, `isCurrentlyStreaming()`

## Impact

- **Difficult to reason about** - What state is the execution in? Need to check multiple flags
- **Mutation bugs** - Easy to forget to propagate state correctly
- **Tight coupling** - Everything depends on this class
- **Large interface** - 30+ public methods

## Root Cause

Trying to use a single immutable object to represent an inherently stateful process (streaming with retries).

## Proposed Solution

### Option A: Split by Lifecycle Phase (Recommended)

Create separate state objects for each phase:

```php
// Phase 1: Pre-execution (immutable)
final readonly class StructuredOutputSpec {
    public function __construct(
        public StructuredOutputRequest $request,
        public StructuredOutputConfig $config,
        public ResponseModel $responseModel,
    ) {}
}

// Phase 2: During execution (mutable session)
final class StreamingSession {
    private Generator $stream;
    private PartialInferenceResponse $accumulated;
    private bool $exhausted = false;

    public function next(): ?PartialInferenceResponse { ... }
    public function isExhausted(): bool { ... }
}

// Phase 3: Result (immutable)
final readonly class ExecutionResult {
    public function __construct(
        public InferenceResponse $response,
        public mixed $value,
        public array $errors,
        public int $attemptCount,
    ) {}
}
```

### Option B: State Machine Pattern

Model execution as explicit states:

```php
interface ExecutionState {}

final readonly class NotStarted implements ExecutionState {
    public function __construct(
        public StructuredOutputSpec $spec,
    ) {}
}

final readonly class Streaming implements ExecutionState {
    public function __construct(
        public StructuredOutputSpec $spec,
        public StreamingSession $session,
        public int $attempt,
    ) {}
}

final readonly class AwaitingRetry implements ExecutionState {
    public function __construct(
        public StructuredOutputSpec $spec,
        public array $previousAttempts,
    ) {}
}

final readonly class Completed implements ExecutionState {
    public function __construct(
        public StructuredOutputSpec $spec,
        public ExecutionResult $result,
    ) {}
}
```

### Option C: Reduce Nesting (Quick Win)

Flatten the state without full restructure:

1. Remove duplicate `StructuredOutputConfig` from `ResponseModel`
2. Inline `InferenceExecution` into `StructuredOutputAttempt`
3. Remove `StructuredOutputAttemptList` - use simple array
4. Combine `attemptState` and `currentAttempt` into single concept

## File Impact

### Option A Files

```
Data/
├── StructuredOutputSpec.php (new)
├── StreamingSession.php (new)
├── ExecutionResult.php (new)
├── StructuredOutputExecution.php (significantly simplified)
```

### Files Needing Updates

- `PendingStructuredOutput.php`
- `StructuredOutputStream.php`
- `AttemptIterator.php`
- `ModularUpdateGenerator.php`
- `SyncUpdateGenerator.php`
- `DefaultRetryPolicy.php`

## Migration Path

### Phase 1: Introduce New Types

1. Create `StructuredOutputSpec` as extraction from current class
2. Use both old and new in parallel
3. Gradually migrate callers to new types

### Phase 2: Simplify Execution

1. Replace nested attempt tracking with simpler structure
2. Remove duplicate state
3. Update consumers

### Phase 3: Remove Old Structure

1. Delete deprecated nested types
2. Simplify remaining `StructuredOutputExecution`

## Risk Assessment

- **Medium risk** - Core type that everything depends on
- **Requires careful migration** - Can't change all at once
- **Testing crucial** - Need comprehensive test coverage before changes

## Estimated Effort

- Option A: 16-24 hours
- Option B: 24-40 hours
- Option C: 8-12 hours

**Recommendation**: Start with Option C for quick wins, then move to Option A.

## Success Metrics

- Reduce `StructuredOutputExecution` from ~345 lines to <150 lines
- Reduce nesting depth from 5+ levels to 2 levels
- Clear lifecycle phases that are easy to reason about
