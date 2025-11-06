# Target Architecture: Clean Separation of Streaming and Attempt Handling

## Executive Summary

This document describes the target architecture after removing `CanExecuteStructuredOutput` and `IterativeToGeneratorAdapter` to achieve a clean separation between:

1. **Stream-level iteration** (`CanStreamStructuredOutputUpdates`) - Processes chunks within a single attempt
2. **Attempt-level orchestration** (`CanHandleStructuredOutputAttempts`) - Handles validation, retries, and all types of failures

## Key Architectural Insight

The new architecture explicitly separates two distinct concerns that were previously entangled:

### CanStreamStructuredOutputUpdates (Stream-level)
**Scope:** Single attempt only
**Responsibility:** Process inference chunks one at a time
- Broken connection → stream ends early
- Rate limit error → stream terminates
- Moderation error → stream terminates
- Success → stream completes normally

**Does NOT handle:**
- Validation
- Retries
- Max attempts logic
- Error recovery between attempts

### CanHandleStructuredOutputAttempts (Attempt-level)
**Scope:** Multiple attempts (orchestration)
**Responsibility:** Validate and retry as needed
- Wraps any `CanStreamStructuredOutputUpdates` implementation
- Detects when stream exhausted
- Validates final response
- Applies retry policy on failures (validation, connection, rate limit, etc.)
- Finalizes successful attempts

## Target Architecture Diagram

```
┌─────────────────────────────────────────────────────────┐
│                    Consumer Layer                        │
│  ┌─────────────────────┐    ┌──────────────────────┐   │
│  │StructuredOutputStream│    │PendingStructuredOutput│   │
│  │  (streaming API)     │    │   (sync/stream API)   │   │
│  └──────────┬───────────┘    └──────────┬───────────┘   │
└─────────────┼───────────────────────────┼───────────────┘
              │                           │
              └───────────┬───────────────┘
                          │ uses
                          ▼
              ┌───────────────────────────┐
              │  CanHandleStructured-     │
              │    OutputAttempts         │ ◄─── Attempt-level
              │                           │      orchestration
              │  Implementation:          │
              │  • AttemptIterator        │
              └───────────┬───────────────┘
                          │ wraps
                          ▼
              ┌───────────────────────────┐
              │ CanStreamStructured-      │
              │    OutputUpdates          │ ◄─── Stream-level
              │                           │      iteration
              │  Implementations:         │
              │  • SyncUpdateGenerator    │ ◄─── NEW (to create)
              │  • PartialStreaming-      │
              │    UpdateGenerator        │
              │  • StreamingUpdates-      │
              │    Generator              │
              └───────────────────────────┘
                          │ uses
                          ▼
              ┌───────────────────────────┐
              │   InferenceProvider       │
              │   (Polyglot)              │
              └───────────────────────────┘
```

## Key Design Principles

### 1. Unified Execution Model

**ALL execution goes through the same architecture:**

```php
// Streaming execution
$streamIterator = new PartialStreamingUpdateGenerator(...);
$attemptHandler = new AttemptIterator($streamIterator, ...);

// Sync execution (same pattern!)
$streamIterator = new SyncUpdateGenerator(...);  // NEW - single chunk
$attemptHandler = new AttemptIterator($streamIterator, ...);
```

**Insight:** Sync is just streaming with one chunk. Same architecture!

### 2. Composability

```php
AttemptIterator(
    streamIterator: CanStreamStructuredOutputUpdates,  // Any implementation
    responseGenerator: CanGenerateResponse,
    retryPolicy: CanDetermineRetry,  // Pluggable strategy
)
```

- Stream iterator is pluggable (sync, partials pipeline, legacy pipeline)
- Retry policy is pluggable (simple, exponential backoff, custom)
- Response generator is shared (validation + transformation)

### 3. Explicit State Management

**Ephemeral attempt state:**
```php
StructuredOutputAttemptState (non-serializable)
├── attemptPhase: AttemptPhase
├── stream: ?Iterator
├── partialIndex: int
├── streamExhausted: bool
├── lastInference: ?InferenceResponse
└── accumulatedPartials: PartialInferenceResponseList
```

**Cleared between attempts** - each retry gets fresh state.

**Persistent execution state:**
```php
StructuredOutputExecution (serializable except attemptState)
├── request
├── responseModel
├── attemptCount
├── maxRetries
├── attemptState: ?StructuredOutputAttemptState  // Ephemeral
└── ... other fields
```

### 4. Error Handling Separation

| Error Type | Handled By | Action |
|------------|-----------|--------|
| Connection broken mid-stream | Stream Iterator | Stream ends early |
| Rate limit error | Stream Iterator | Stream terminates with error |
| Moderation error | Stream Iterator | Stream terminates with error |
| Validation failure | Attempt Iterator | Applies retry policy |
| Max retries reached | Attempt Iterator | Throws or finalizes |
| Success | Attempt Iterator | Finalizes execution |

**Stream iterators never retry** - they just report failures.
**Attempt iterator decides** - retry or throw.

## Target Component Inventory

### Components to CREATE

#### 1. SyncUpdateGenerator
**Path:** `packages/instructor/src/Executors/Sync/SyncUpdateGenerator.php`

```php
final readonly class SyncUpdateGenerator implements CanStreamStructuredOutputUpdates {
    public function __construct(
        private InferenceProvider $inferenceProvider,
    ) {}

    public function hasNext(StructuredOutputExecution $execution): bool {
        $state = $execution->attemptState();

        // Not started yet - can make request
        if ($state === null) {
            return true;
        }

        // Sync = single chunk, done after one iteration
        return false;
    }

    public function nextChunk(StructuredOutputExecution $execution): StructuredOutputExecution {
        // Make single inference request (non-streaming)
        $inference = $this->inferenceProvider->getInference($execution)->response();

        // Mark as exhausted immediately (single chunk)
        $state = StructuredOutputAttemptState::empty()
            ->withPhase(AttemptPhase::Done)
            ->withNextChunk($inference, PartialInferenceResponseList::empty(), true);

        return $execution
            ->withAttemptState($state)
            ->withCurrentAttempt(
                inferenceResponse: $inference,
                partialInferenceResponses: PartialInferenceResponseList::empty(),
                errors: $execution->currentErrors(),
            );
    }
}
```

### Components to MODIFY

#### 1. StructuredOutputStream
**Path:** `packages/instructor/src/StructuredOutputStream.php`

**Changes:**
- Replace `CanExecuteStructuredOutput` with `CanHandleStructuredOutputAttempts`
- Update internal iteration from generator to while loop
- Keep public API identical (generator-based convenience)

**Before:**
```php
private CanExecuteStructuredOutput $requestHandler;

private function streamWithoutCaching(StructuredOutputExecution $execution): Generator {
    $executionUpdates = $this->requestHandler->nextUpdate($execution);
    foreach ($executionUpdates as $chunk) {
        yield $chunk;
    }
}
```

**After:**
```php
private CanHandleStructuredOutputAttempts $attemptHandler;

private function streamWithoutCaching(StructuredOutputExecution $execution): Generator {
    while ($this->attemptHandler->hasNext($execution)) {
        $execution = $this->attemptHandler->nextUpdate($execution);
        yield $execution;
    }
    $this->execution = $execution;
}
```

#### 2. PendingStructuredOutput
**Path:** `packages/instructor/src/PendingStructuredOutput.php`

**Changes:**
- Replace `CanExecuteStructuredOutput` with `CanHandleStructuredOutputAttempts`
- Update internal iteration from generator to while loop

**Before:**
```php
private readonly CanExecuteStructuredOutput $requestHandler;

foreach ($this->requestHandler->nextUpdate($this->execution) as $exec) {
    $this->execution = $exec;
}
```

**After:**
```php
private readonly CanHandleStructuredOutputAttempts $attemptHandler;

while ($this->attemptHandler->hasNext($this->execution)) {
    $this->execution = $this->attemptHandler->nextUpdate($this->execution);
}
```

#### 3. ExecutorFactory
**Path:** `packages/instructor/src/ExecutorFactory.php`

**Changes:**
- Update return type: `CanExecuteStructuredOutput` → `CanHandleStructuredOutputAttempts`
- Remove adapter wrapping (lines 234, 277)
- Update sync handler to use new `SyncUpdateGenerator`
- Simplify configuration (deprecate old driver options)

**Before:**
```php
public function makeExecutor(StructuredOutputExecution $execution): CanExecuteStructuredOutput {
    return match(true) {
        $execution->isStreamed() => $this->makeStreamingHandler(...),
        default => $this->makeSyncHandler(...),
    };
}

private function makeIterativeTransducerHandler(...): CanExecuteStructuredOutput {
    $attemptIterator = new AttemptIterator(...);
    return new IterativeToGeneratorAdapter($attemptIterator);  // REMOVE THIS
}
```

**After:**
```php
public function makeExecutor(StructuredOutputExecution $execution): CanHandleStructuredOutputAttempts {
    // Both streaming and sync use same pattern!
    $streamIterator = match(true) {
        $execution->isStreamed() => $this->makeStreamingIterator($execution),
        default => $this->makeSyncIterator(),
    };

    return new AttemptIterator(
        streamIterator: $streamIterator,
        responseGenerator: $this->makeResponseProcessor(),
        retryPolicy: $this->makeRetryPolicy(),
    );
}

private function makeSyncIterator(): CanStreamStructuredOutputUpdates {
    return new SyncUpdateGenerator(
        inferenceProvider: $this->makeInferenceProvider(),
    );
}

private function makeStreamingIterator(StructuredOutputExecution $execution): CanStreamStructuredOutputUpdates {
    return match($execution->config()->streamingDriver) {
        'partials' => $this->makePartialStreamingIterator(),
        'generator' => $this->makeLegacyStreamingIterator($execution),
        default => $this->makePartialStreamingIterator(),  // Default to partials
    };
}
```

### Components to DELETE

#### 1. IterativeToGeneratorAdapter
**Path:** `packages/instructor/src/Executors/IterativeToGeneratorAdapter.php`

Complete removal - no longer needed after consumers are updated.

#### 2. CanExecuteStructuredOutput (deprecated)
**Path:** `packages/instructor/src/Contracts/CanExecuteStructuredOutput.php`

**Option A:** Mark as `@deprecated`, remove after transition
**Option B:** Delete immediately since it's internal

#### 3. Legacy Handlers (all 3)
**Path:**
- `packages/instructor/src/Executors/Partials/PartialStreamingRequestHandler.php`
- `packages/instructor/src/Executors/Streaming/StreamingRequestHandler.php`
- `packages/instructor/src/Executors/Sync/SyncRequestHandler.php`

**Rationale:**
- All have embedded retry logic (while loop with validation)
- Retry logic now extracted to `AttemptIterator` + `DefaultRetryPolicy`
- Streaming logic now in `PartialStreamingUpdateGenerator` / `StreamingUpdatesGenerator`
- Sync logic now in `SyncUpdateGenerator`

**Complete replacement:**

| Old Handler | Replacement |
|-------------|-------------|
| `SyncRequestHandler` | `SyncUpdateGenerator` + `AttemptIterator` |
| `PartialStreamingRequestHandler` | `PartialStreamingUpdateGenerator` + `AttemptIterator` |
| `StreamingRequestHandler` | `StreamingUpdatesGenerator` + `AttemptIterator` |

#### 4. RetryHandler (legacy)
**Path:** `packages/instructor/src/Core/RetryHandler.php`

Replaced by `DefaultRetryPolicy` (implements `CanDetermineRetry`).

## Migration Benefits

### 1. Single Execution Path
- No more branching between sync/streaming at executor level
- All execution goes through `AttemptIterator`
- Simpler reasoning about behavior

### 2. Explicit Attempt Tracking
- No hidden retry logic in generator functions
- Clear state management (`StructuredOutputAttemptState`)
- Obvious when attempts start/end

### 3. Better Error Handling
- Stream errors don't mix with validation errors
- Rate limits, connection issues, moderation handled at right level
- Retry policy sees all error types

### 4. Testability
- Stream iterators testable in isolation (no retry logic)
- Attempt orchestrator testable separately
- Retry policies unit testable (pure functions on execution state)

### 5. Composability
- Any stream iterator works with any retry policy
- Easy to add new streaming approaches (just implement interface)
- Easy to add custom retry strategies (just implement interface)

### 6. DDD Alignment
- Clear bounded contexts (streaming vs attempts)
- Policy objects (retry policy)
- Immutable value objects (execution states)
- No control flow mixed with domain logic

## Configuration Simplification

**Current config:**
```php
'streamingDriver' => 'partials' | 'generator' | 'partials-iterative' | 'generator-iterative'
```

**Target config:**
```php
'streamingPipeline' => 'partials' | 'legacy'  // Default: 'partials'
```

**Rationale:**
- All execution is now "iterative" (no distinction needed)
- Only choice: which streaming pipeline to use
- Simpler configuration surface

## Non-Goals

### Keep Generator-Based Public APIs
`StructuredOutputStream` public methods remain generator-based for convenience:
```php
public function partials(): Generator { ... }
public function responses(): Generator { ... }
public function sequence(): Generator { ... }
```

This is just a presentation layer - internally uses while loop.

### Backward Compatibility
This is an internal refactoring. Public APIs (`StructuredOutput`, `StructuredOutputStream`) remain unchanged.

## Migration Risks

| Risk | Impact | Mitigation |
|------|--------|------------|
| Behavioral differences | Medium | Comprehensive integration tests |
| Performance regression | Low | Benchmark before/after |
| Test failures | Medium | Update tests incrementally |
| Missed edge cases | Medium | Review legacy handlers carefully |

## Success Criteria

- ✅ All tests pass
- ✅ No performance regression (< 5% variance)
- ✅ Public APIs unchanged
- ✅ No `CanExecuteStructuredOutput` usage
- ✅ No `IterativeToGeneratorAdapter` usage
- ✅ All execution through `AttemptIterator`
- ✅ Clean separation: streaming vs attempts

## Next Steps

See `REFACTORING_PLAN.md` for detailed implementation phases.
