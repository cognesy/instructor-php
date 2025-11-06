# Iterative Structured Output Execution

This document describes the new composable, stateless iteration architecture for structured output execution with streaming support.

## Overview

The new architecture separates concerns into two composable levels:

1. **Stream-level iteration**: Processes chunks within a single attempt
2. **Attempt-level iteration**: Orchestrates attempts with validation and retry logic

Both levels use the same contract pattern, making them fully composable.

## Architecture

```
Consumer (StructuredOutputStream, user code)
    │
    ▼
AttemptIterator (CanHandleStructuredOutputAttempts)
    ├── Validates responses
    ├── Applies retry policy
    └── Wraps stream iterator
        │
        ▼
    Stream Iterator (CanStreamStructuredOutputUpdates)
        ├── PartialStreamingUpdateGenerator (new Partials pipeline)
        └── StreamingUpdatesGenerator (legacy pipeline)
```

## Core Components

### Contracts

#### `CanStreamStructuredOutputUpdates`
Stream-level iterator for processing chunks within a single attempt.

**Location**: `packages/instructor/src/Contracts/CanStreamStructuredOutputUpdates.php`

**Methods**:
- `hasNext(StructuredOutputExecution): bool` - Check if more chunks available
- `nextChunk(StructuredOutputExecution): StructuredOutputExecution` - Process next chunk

**Responsibility**: Process streaming chunks only (NO validation, NO retries)

#### `CanHandleStructuredOutputAttempts`
Attempt-level orchestrator for managing validation and retries.

**Location**: `packages/instructor/src/Contracts/CanHandleStructuredOutputAttempts.php`

**Methods**:
- `hasNext(StructuredOutputExecution): bool` - Check if more attempts possible
- `nextUpdate(StructuredOutputExecution): StructuredOutputExecution` - Process next update

**Responsibility**: Orchestrate attempts, validate, apply retry policy

#### `CanDetermineRetry`
Pluggable retry policy interface (DDD policy object).

**Location**: `packages/instructor/src/Contracts/CanDetermineRetry.php`

**Methods**:
- `shouldRetry(execution, validationResult): bool` - Decide if should retry
- `recordFailure(execution, result, inference, partials): StructuredOutputExecution` - Record failed attempt
- `prepareRetry(execution): StructuredOutputExecution` - Prepare for next retry
- `finalizeOrThrow(execution, result): mixed` - Finalize or throw exception

### State Management

#### `StructuredOutputAttemptState`
Ephemeral state for an in-flight attempt (sync or streaming), non-serializable.

**Location**: `packages/instructor/src/Data/StructuredOutputAttemptState.php`

**Fields**:
- `attemptPhase: AttemptPhase` - Current phase (Init, Streaming, Validating, Done)
- `stream: ?Iterator` - Active stream (Generator or Iterator)
- `partialIndex: int` - Count of processed chunks
- `streamExhausted: bool` - Whether stream is exhausted
- `lastInference: ?InferenceResponse` - Last aggregated inference
- `accumulatedPartials: PartialInferenceResponseList` - Accumulated partials

**Key Methods**:
- `hasMoreChunks(): bool` - Check if stream has more data
- `withNextChunk(inference, partials): self` - Update after processing chunk
- `withExhausted(): self` - Mark stream as exhausted

**Important**: This state is **ephemeral** and **non-serializable**. It exists only during active streaming and is cleared between attempts.

#### `StructuredOutputExecution`
Main execution state (persistent, serializable except streamingState).

**Location**: `packages/instructor/src/Data/StructuredOutputExecution.php`

**New Fields**:
- `attemptState: ?StructuredOutputAttemptState` - Ephemeral attempt state

**New Methods**:
- `attemptState(): ?StructuredOutputAttemptState` - Get attempt state
- `withAttemptState(?StructuredOutputAttemptState): self` - Update attempt state
- `isAttemptActive(): bool` - Check if an attempt is in progress
- `isCurrentlyStreaming(): bool` - Backward-compatible alias for `isAttemptActive()`

### Implementations

#### `PartialStreamingUpdateGenerator`
Stream-level iterator using the new Partials pipeline.

**Location**: `packages/instructor/src/Executors/Partials/PartialStreamingUpdateGenerator.php`

**Responsibilities**:
- Initialize Partials pipeline stream
- Process AggregationState chunks
- Update execution with partial responses
- Signal when stream exhausted

#### `StreamingUpdatesGenerator`
Stream-level iterator using the legacy streaming pipeline.

**Location**: `packages/instructor/src/Executors/Streaming/StreamingUpdatesGenerator.php`

**Responsibilities**:
- Initialize legacy partials stream
- Process PartialInferenceResponse chunks
- Build aggregate inference from partials
- Update execution with partial responses
- Signal when stream exhausted

#### `AttemptIterator`
Attempt-level orchestrator that wraps stream iterators.

**Location**: `packages/instructor/src/Executors/AttemptIterator.php`

**Responsibilities**:
- Delegate chunk processing to stream iterator
- Detect when stream is exhausted
- Validate final responses
- Apply retry policy on failures
- Finalize successful attempts

**Composition**: Wraps any `CanStreamStructuredOutputUpdates` implementation.

#### `DefaultRetryPolicy`
Default implementation of retry policy.

**Location**: `packages/instructor/src/Core/DefaultRetryPolicy.php`

**Strategy**:
- Simple max retries check
- Records failures with event dispatch
- No modifications on retry (can be extended)
- Throws exception when retries exhausted

## Usage Examples

### Basic Usage (Partials Pipeline)

```php
use Cognesy\Instructor\Core\AttemptIterator;use Cognesy\Instructor\Core\DefaultRetryPolicy;use Cognesy\Instructor\Executors\Partials\PartialStreamingUpdateGenerator;

// Build stream iterator
$streamIterator = new PartialStreamingUpdateGenerator(
    inferenceProvider: $inferenceProvider,
    partials: $partialsFactory,
);

// Build retry policy
$retryPolicy = new DefaultRetryPolicy($events);

// Wrap in attempt iterator
$controller = new AttemptIterator(
    streamIterator: $streamIterator,
    responseGenerator: $responseGenerator,
    retryPolicy: $retryPolicy,
);

// Execute iteration
$execution = /* initial execution */;

while ($controller->hasNext($execution)) {
    $execution = $controller->nextUpdate($execution);

    // Yield to consumer for real-time updates
    yield $execution;
}

// Final execution has the result
$result = $execution->inferenceResponse()->value();
```

### Legacy Streaming Pipeline

```php
use Cognesy\Instructor\Executors\Streaming\StreamingUpdatesGenerator;

$streamIterator = new StreamingUpdatesGenerator(
    inferenceProvider: $inferenceProvider,
    partialsGenerator: $partialsGenerator,
);

// Rest is the same...
$controller = new AttemptIterator(
    streamIterator: $streamIterator,
    responseGenerator: $responseGenerator,
    retryPolicy: $retryPolicy,
);
```

### Custom Retry Policy

```php
use Cognesy\Instructor\Contracts\CanDetermineRetry;

class ExponentialBackoffRetryPolicy implements CanDetermineRetry {
    public function __construct(
        private CanDetermineRetry $basePolicy,
        private int $baseDelayMs = 1000,
    ) {}

    public function prepareRetry(StructuredOutputExecution $execution): StructuredOutputExecution {
        // Add exponential backoff delay
        $attemptNumber = $execution->attemptCount();
        $delayMs = $this->baseDelayMs * pow(2, $attemptNumber - 1);
        usleep((int)($delayMs * 1000));

        return $this->basePolicy->prepareRetry($execution);
    }

    // Delegate other methods to basePolicy...
}

// Usage
$basePolicy = new DefaultRetryPolicy($events);
$retryPolicy = new ExponentialBackoffRetryPolicy($basePolicy);

$controller = new AttemptIterator(
    streamIterator: $streamIterator,
    responseGenerator: $responseGenerator,
    retryPolicy: $retryPolicy,
);
```

## Key Design Principles

### 1. Composability
Both stream and attempt iterators use compatible contracts, allowing composition:
- Stream iterator focuses on chunk processing
- Attempt iterator wraps stream iterator and adds validation/retry

### 2. Separation of Concerns
- **Stream iteration** ≠ **Attempt iteration** (different scopes, both composable)
- Stream iterators: chunk-by-chunk processing
- Attempt iterators: validation + retry orchestration

### 3. Pluggable Retry Logic
`CanDetermineRetry` interface allows custom retry strategies:
- Simple max retries (DefaultRetryPolicy)
- Exponential backoff
- Prompt modification on retry
- Custom error handling

### 4. Non-Determinism Awareness
- LLM streams cannot be replayed (non-deterministic)
- Each retry = fresh inference stream
- No replay/resume assumptions in design

### 5. Stateless from Consumer Perspective
- All state stored in immutable `StructuredOutputExecution`
- Each `nextUpdate()` returns new execution instance
- Controllers can be stateful internally (hold ephemeral stream state)

### 6. DDD Alignment
- `RetryPolicy` is a domain policy object (not a handler)
- Clear boundaries between data, logic, and policies

## State Lifecycle

### Stream-Level State (`StructuredOutputAttemptState`)
```
null → empty() → withStream() → Streaming → withNextChunk() × N → withExhausted() → null
      ↑                                                                              ↓
      └──────────────────────────── (retry clears state) ─────────────────────────┘
```

### Attempt-Level State (`StructuredOutputExecution`)
```
Initial → withCurrentAttempt() × N → [Validate] → Success: withSuccessfulAttempt() → Finalized
                                               ↓
                                        Failure: withFailedAttempt() → (retry or throw)
```

## Comparison: Old vs New

| Aspect | Old (Generator) | New (Iterative) |
|--------|----------------|-----------------|
| **Control Flow** | Internal (generator) | External (CPS loop) |
| **State Management** | Generator position | Explicit state objects |
| **Composability** | Limited | Fully composable |
| **Retry Policy** | Hardcoded | Pluggable interface |
| **Testing** | Complex (generators) | Simple (pure functions) |
| **Debugging** | Harder (yield) | Easier (explicit state) |
| **Pause/Resume** | Not possible | Not possible (by design) |

## Migration Path

### Phase 1: Foundation ✅ COMPLETE
- ✅ `StructuredOutputAttemptState` with helper methods
- ✅ Integration with `StructuredOutputExecution`
- ✅ `CanDetermineRetry` interface
- ✅ `DefaultRetryPolicy` implementation

### Phase 2: Iterators ✅ COMPLETE
- ✅ `PartialStreamingUpdateGenerator`
- ✅ `StreamingUpdatesGenerator`
- ✅ `AttemptIterator`

### Phase 3: Integration ✅ COMPLETE
- ✅ Update `ExecutorFactory` to wire new components
- ✅ Unified execution pattern (both sync and streaming use AttemptIterator)
- ✅ Backward compatibility via config mapping

### Phase 4: Testing ✅ COMPLETE
- ✅ Unit tests for stream iterators
- ✅ Integration tests for attempt orchestrator
- ✅ End-to-end tests with retry scenarios

### Phase 5: Migration ✅ COMPLETE
- ✅ Migrate consumers to new iterators (StructuredOutputStream, PendingStructuredOutput)
- ✅ Deprecate old generator-based handlers (marked with @deprecated and #[Deprecated])
- ⏳ Remove deprecated code after transition period (manual cleanup later)

## Status: COMPLETE ✅

The refactoring to the new iterative architecture is complete:
- ✅ All consumers migrated to `CanHandleStructuredOutputAttempts`
- ✅ `IterativeToGeneratorAdapter` removed
- ✅ Legacy handlers marked as deprecated (will be removed manually later)
- ✅ Tests updated and passing
- ✅ Unified execution pattern implemented

## Remaining Work (Manual Cleanup)

After sufficient testing period, manually remove:
1. `SyncRequestHandler` (deprecated)
2. `PartialStreamingRequestHandler` (deprecated)
3. `StreamingRequestHandler` (deprecated)
4. `CanExecuteStructuredOutput` interface (deprecated)
5. Deprecated methods in `ExecutorFactory`

## References

- Original generator-based handlers:
  - `packages/instructor/src/Executors/Partials/PartialStreamingRequestHandler.php`
  - `packages/instructor/src/Executors/Streaming/StreamingRequestHandler.php`

- Related contracts:
  - `packages/instructor/src/Contracts/CanExecuteStructuredOutput.php` (generator-based)
  - `packages/instructor/src/Contracts/CanIterateStructuredOutput.php` (original proposal, superseded)

- Core infrastructure:
  - `packages/instructor/src/Core/RetryHandler.php` (legacy, being replaced by `DefaultRetryPolicy`)
