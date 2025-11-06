# Refactoring Plan: Transition to Clean Architecture

This document provides a step-by-step refactoring plan to achieve the target architecture described in `TARGET_ARCHITECTURE.md`.

## Overview

**Goal:** Remove `CanExecuteStructuredOutput` and `IterativeToGeneratorAdapter`, achieving clean separation between streaming and attempt handling.

**Strategy:** Incremental refactoring with tests passing at each phase.

## Pre-Flight Checklist

- [x] New contracts created (`CanStreamStructuredOutputUpdates`, `CanHandleStructuredOutputAttempts`, `CanDetermineRetry`)
- [x] New stream iterators created (`PartialStreamingUpdateGenerator`, `StreamingUpdatesGenerator`)
- [x] Attempt orchestrator created (`AttemptIterator`)
- [x] Default retry policy created (`DefaultRetryPolicy`)
- [x] Adapter in place (`IterativeToGeneratorAdapter`)
- [x] Attempt state infrastructure (`StructuredOutputAttemptState`)

## Phase 1: Foundation (NEW WORK)

### Step 1.1: Create SyncUpdateGenerator

**File:** `packages/instructor/src/Executors/Sync/SyncUpdateGenerator.php`

**Implementation:**

```php
<?php declare(strict_types=1);

namespace Cognesy\Instructor\Executors\Sync;

use Cognesy\Instructor\Contracts\CanStreamStructuredOutputUpdates;
use Cognesy\Instructor\Core\InferenceProvider;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Data\StructuredOutputAttemptState;
use Cognesy\Instructor\Enums\AttemptPhase;
use Cognesy\Polyglot\Inference\Collections\PartialInferenceResponseList;

/**
 * Stream iterator for synchronous (non-streaming) execution.
 *
 * Scope: Single attempt only (does NOT handle validation or retries)
 * Pattern: Makes one inference request, yields one update
 *
 * Responsibility:
 * - Make non-streaming inference request
 * - Return single update (no actual streaming)
 * - Signal exhaustion immediately
 */
final readonly class SyncUpdateGenerator implements CanStreamStructuredOutputUpdates
{
    public function __construct(
        private InferenceProvider $inferenceProvider,
    ) {}

    #[\Override]
    public function hasNext(StructuredOutputExecution $execution): bool {
        $state = $execution->attemptState();

        // Not started yet - can make request
        if ($state === null) {
            return true;
        }

        // Already made request - no more updates (sync = single chunk)
        return !$state->isStreamExhausted();
    }

    #[\Override]
    public function nextChunk(StructuredOutputExecution $execution): StructuredOutputExecution {
        $state = $execution->attemptState();

        // Should not be called if already exhausted
        if ($state !== null && $state->isStreamExhausted()) {
            return $execution;
        }

        // Make single synchronous inference request
        $inference = $this->inferenceProvider->getInference($execution)->response();

        // Create attempt state marked as exhausted (single chunk pattern)
        $attemptState = StructuredOutputAttemptState::fromSingleChunk(
            inference: $inference,
            partials: PartialInferenceResponseList::empty(),
        );

        // Update execution with inference and mark stream exhausted
        return $execution
            ->withAttemptState($attemptState)
            ->withCurrentAttempt(
                inferenceResponse: $inference,
                partialInferenceResponses: PartialInferenceResponseList::empty(),
                errors: $execution->currentErrors(),
            );
    }
}
```

**Tests:** `packages/instructor/tests/Unit/SyncUpdateGeneratorTest.php`

```php
<?php declare(strict_types=1);

use Cognesy\Instructor\Core\InferenceProvider;
use Cognesy\Instructor\Data\StructuredOutputExecution;
use Cognesy\Instructor\Executors\Sync\SyncUpdateGenerator;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;

it('yields single update for sync execution', function () {
    $mockInference = InferenceResponse::empty();
    $mockProvider = Mockery::mock(InferenceProvider::class);
    $mockProvider->shouldReceive('getInference')
        ->once()
        ->andReturn((object)['response' => fn() => $mockInference]);

    $generator = new SyncUpdateGenerator($mockProvider);
    $execution = StructuredOutputExecution::create(/* ... */);

    // First call: hasNext returns true
    expect($generator->hasNext($execution))->toBeTrue();

    // Get first (and only) update
    $updated = $generator->nextChunk($execution);
    expect($updated->inferenceResponse())->toBe($mockInference);
    expect($updated->attemptState()->isStreamExhausted())->toBeTrue();

    // Second call: hasNext returns false (exhausted)
    expect($generator->hasNext($updated))->toBeFalse();
});

it('marks stream as exhausted immediately', function () {
    $mockInference = InferenceResponse::empty();
    $mockProvider = Mockery::mock(InferenceProvider::class);
    $mockProvider->shouldReceive('getInference')
        ->once()
        ->andReturn((object)['response' => fn() => $mockInference]);

    $generator = new SyncUpdateGenerator($mockProvider);
    $execution = StructuredOutputExecution::create(/* ... */);

    $updated = $generator->nextChunk($execution);

    expect($updated->isCurrentlyStreaming())->toBeFalse();
    expect($updated->attemptState()->isStreamExhausted())->toBeTrue();
});
```

**Acceptance:** Tests pass, `SyncUpdateGenerator` works with `AttemptIterator`.

---

### Step 1.2: Add ResponseNormalizer to SyncUpdateGenerator

**Context:** Current `SyncRequestHandler` uses `ResponseNormalizer` to handle mode-specific normalization.

**File:** Check if `ResponseNormalizer` is needed or if it's handled elsewhere.

**Action:** Review `packages/instructor/src/Executors/Sync/ResponseNormalizer.php` and integrate if needed.

**Note:** May need to move normalization to `InferenceProvider` or add to `SyncUpdateGenerator`.

---

## Phase 2: Update Consumers

### Step 2.1: Update StructuredOutputStream

**File:** `packages/instructor/src/StructuredOutputStream.php`

**Changes:**

```diff
- use Cognesy\Instructor\Contracts\CanExecuteStructuredOutput;
+ use Cognesy\Instructor\Contracts\CanHandleStructuredOutputAttempts;

 class StructuredOutputStream
 {
-    private CanExecuteStructuredOutput $requestHandler;
+    private CanHandleStructuredOutputAttempts $attemptHandler;

     public function __construct(
         StructuredOutputExecution $execution,
-        CanExecuteStructuredOutput $requestHandler,
+        CanHandleStructuredOutputAttempts $attemptHandler,
         EventDispatcherInterface $events,
     ) {
-        $this->requestHandler = $requestHandler;
+        $this->attemptHandler = $attemptHandler;
         // ...
     }

     private function streamWithoutCaching(StructuredOutputExecution $execution): Generator {
-        $executionUpdates = $this->requestHandler->nextUpdate($execution);
-        $last = null;
-        foreach ($executionUpdates as $chunk) {
-            $last = $chunk;
-            yield $chunk;
-        }
-        if ($last !== null) {
-            $this->execution = $last;
+        while ($this->attemptHandler->hasNext($execution)) {
+            $execution = $this->attemptHandler->nextUpdate($execution);
+            yield $execution;
         }
+        $this->execution = $execution;
     }

     private function buildAndCacheStream(StructuredOutputExecution $execution): Generator {
         $this->cachedResponseStream = [];
-        $executionUpdates = $this->requestHandler->nextUpdate($execution);
-        $last = null;
-        foreach ($executionUpdates as $chunk) {
-            $this->cachedResponseStream[] = $chunk;
-            $last = $chunk;
-            yield $chunk;
-        }
-        if ($last !== null) {
-            $this->execution = $last;
+        while ($this->attemptHandler->hasNext($execution)) {
+            $execution = $this->attemptHandler->nextUpdate($execution);
+            $this->cachedResponseStream[] = $execution;
+            yield $execution;
         }
+        $this->execution = $execution;
     }
 }
```

**Tests:** Run existing tests in:
- `packages/instructor/tests/Unit/StructuredOutputStreamTest.php`
- `packages/instructor/tests/Unit/StructuredOutputStreamSequenceTest.php`

**Acceptance:** All existing tests pass with new implementation.

---

### Step 2.2: Update PendingStructuredOutput

**File:** `packages/instructor/src/PendingStructuredOutput.php`

**Changes:**

```diff
- use Cognesy\Instructor\Contracts\CanExecuteStructuredOutput;
+ use Cognesy\Instructor\Contracts\CanHandleStructuredOutputAttempts;

 class PendingStructuredOutput
 {
-    private readonly CanExecuteStructuredOutput $requestHandler;
+    private readonly CanHandleStructuredOutputAttempts $attemptHandler;

     public function __construct(
         StructuredOutputExecution $execution,
         ExecutorFactory $executorFactory,
         CanHandleEvents $events,
     ) {
-        $this->requestHandler = $executorFactory->makeExecutor($execution);
+        $this->attemptHandler = $executorFactory->makeExecutor($execution);
         // ...
     }

     private function getResponse(): InferenceResponse {
         $this->events->dispatch(new StructuredOutputStarted([...]));

         if (!$this->cacheProcessedResponse) {
-            foreach ($this->requestHandler->nextUpdate($this->execution) as $exec) {
-                $this->execution = $exec;
+            while ($this->attemptHandler->hasNext($this->execution)) {
+                $this->execution = $this->attemptHandler->nextUpdate($this->execution);
             }
             // ...
         }

         if ($this->cachedResponse === null) {
-            foreach ($this->requestHandler->nextUpdate($this->execution) as $exec) {
-                $this->execution = $exec;
+            while ($this->attemptHandler->hasNext($this->execution)) {
+                $this->execution = $this->attemptHandler->nextUpdate($this->execution);
             }
             // ...
         }
         // ...
     }
 }
```

**Tests:** Run existing tests that use `PendingStructuredOutput`.

**Acceptance:** All tests pass.

---

## Phase 3: Update ExecutorFactory

### Step 3.1: Change Return Type

**File:** `packages/instructor/src/ExecutorFactory.php`

**Changes:**

```diff
- public function makeExecutor(StructuredOutputExecution $execution): CanExecuteStructuredOutput {
+ public function makeExecutor(StructuredOutputExecution $execution): CanHandleStructuredOutputAttempts {
```

**Impact:** Compile error in all call sites until they're updated (already done in Phase 2).

---

### Step 3.2: Simplify Streaming Handler Factory

**File:** `packages/instructor/src/ExecutorFactory.php`

**Before:**
```php
private function makeStreamingHandler(...): CanExecuteStructuredOutput {
    return match($config->streamingDriver) {
        'partials' => $this->makeTransducerHandler(...),
        'generator' => $this->makeGeneratorHandler(...),
        'partials-iterative' => $this->makeIterativeTransducerHandler(...),
        'generator-iterative' => $this->makeIterativeGeneratorHandler(...),
    };
}
```

**After:**
```php
public function makeExecutor(StructuredOutputExecution $execution): CanHandleStructuredOutputAttempts {
    // Unified: both streaming and sync use same pattern
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
    $pipeline = $execution->config()->streamingPipeline ?? 'partials';

    return match($pipeline) {
        'partials' => $this->makePartialStreamingIterator(),
        'legacy' => $this->makeLegacyStreamingIterator($execution),
        default => $this->makePartialStreamingIterator(),
    };
}

private function makePartialStreamingIterator(): CanStreamStructuredOutputUpdates {
    $partialsFactory = new PartialStreamFactory(
        deserializer: $this->responseDeserializer,
        validator: $this->partialResponseValidator,
        transformer: $this->responseTransformer,
        events: $this->events,
    );

    return new PartialStreamingUpdateGenerator(
        inferenceProvider: $this->makeInferenceProvider(),
        partials: $partialsFactory,
    );
}

private function makeLegacyStreamingIterator(StructuredOutputExecution $execution): CanStreamStructuredOutputUpdates {
    $partialsGenerator = match($execution->outputMode()) {
        OutputMode::Tools => new GeneratePartialsFromToolCalls(
            $this->responseDeserializer,
            $this->partialResponseValidator,
            $this->responseTransformer,
            $this->events,
        ),
        default => new GeneratePartialsFromJson(
            $this->responseDeserializer,
            $this->partialResponseValidator,
            $this->responseTransformer,
            $this->events,
        ),
    };

    return new StreamingUpdatesGenerator(
        inferenceProvider: $this->makeInferenceProvider(),
        partialsGenerator: $partialsGenerator,
    );
}
```

**Delete old methods:**
- `makeSyncHandler()`
- `makeStreamingHandler()`
- `makeGeneratorHandler()`
- `makeTransducerHandler()`
- `makeIterativeTransducerHandler()`
- `makeIterativeGeneratorHandler()`

**Acceptance:** Simpler factory with unified execution path.

---

### Step 3.3: Update Configuration

**File:** `packages/instructor/src/Config/StructuredOutputConfig.php` (inferred)

**Change:**
```diff
- public string $streamingDriver = 'partials-iterative';  // 'partials' | 'generator' | 'partials-iterative' | 'generator-iterative'
+ public string $streamingPipeline = 'partials';  // 'partials' | 'legacy'
```

**Migration guide:**
```php
// Old config
'streamingDriver' => 'partials-iterative'  → 'streamingPipeline' => 'partials'
'streamingDriver' => 'generator-iterative' → 'streamingPipeline' => 'legacy'
'streamingDriver' => 'partials'           → 'streamingPipeline' => 'partials'
'streamingDriver' => 'generator'          → 'streamingPipeline' => 'legacy'
```

**Acceptance:** Configuration simplified.

---

## Phase 4: Remove Legacy Components

### Step 4.1: Delete IterativeToGeneratorAdapter

**File:** `packages/instructor/src/Executors/IterativeToGeneratorAdapter.php`

**Action:** Delete entire file.

**Verify:** No remaining references:
```bash
rg "IterativeToGeneratorAdapter" packages/instructor/
```

---

### Step 4.2: Deprecate CanExecuteStructuredOutput

**File:** `packages/instructor/src/Contracts/CanExecuteStructuredOutput.php`

**Option A - Deprecate:**
```php
/**
 * @deprecated Use CanHandleStructuredOutputAttempts instead
 */
interface CanExecuteStructuredOutput { ... }
```

**Option B - Delete:**
Delete the file entirely.

**Recommendation:** Delete (it's internal, no external usage).

---

### Step 4.3: Delete Legacy Handlers

**Files to delete:**
1. `packages/instructor/src/Executors/Partials/PartialStreamingRequestHandler.php`
2. `packages/instructor/src/Executors/Streaming/StreamingRequestHandler.php`
3. `packages/instructor/src/Executors/Sync/SyncRequestHandler.php`

**Verify no usage:**
```bash
rg "PartialStreamingRequestHandler|StreamingRequestHandler|SyncRequestHandler" packages/instructor/
```

**Keep only test references** - update those tests to use new architecture.

---

### Step 4.4: Delete RetryHandler (legacy)

**File:** `packages/instructor/src/Core/RetryHandler.php`

**Replaced by:** `DefaultRetryPolicy`

**Verify no usage:**
```bash
rg "RetryHandler" packages/instructor/ --type php
```

**Action:** Delete if no remaining usage.

---

## Phase 5: Update Tests

### Step 5.1: Update Unit Tests

**Files:**
- `packages/instructor/tests/Unit/StructuredOutputStreamTest.php`
- `packages/instructor/tests/Unit/StructuredOutputStreamSequenceTest.php`
- `packages/instructor/tests/Unit/SyncUpdateGeneratorTest.php` (new)
- `packages/instructor/tests/Unit/PartialStreamingUpdateGeneratorTest.php`
- `packages/instructor/tests/Unit/StreamingUpdatesGeneratorTest.php`
- `packages/instructor/tests/Unit/AttemptIteratorTest.php`
- `packages/instructor/tests/Unit/DefaultRetryPolicyTest.php`

**Actions:**
- Update mocks to use `CanHandleStructuredOutputAttempts`
- Ensure all iterators tested in isolation
- Ensure `AttemptIterator` composition tested

---

### Step 5.2: Update Integration Tests

**Files:**
- `packages/instructor/tests/Feature/StructuredOutputSmokeTest.php`
- `packages/instructor/tests/Feature/MockHttpStreamingSequenceSmokeTest.php`
- `packages/instructor/tests/Regression/PartialUpdatesStreamingTest.php`

**Actions:**
- Verify end-to-end flows work
- Test both sync and streaming execution
- Test retry scenarios

---

### Step 5.3: Update Test Helpers

**Files:**
- `packages/instructor/tests/Support/FakeInferenceDriver.php`
- `packages/instructor/tests/Support/TestHelpers.php`

**Actions:**
- Update to work with new architecture
- Provide helpers for creating stream iterators
- Provide helpers for creating attempt handlers

---

## Phase 6: Documentation & Cleanup

### Step 6.1: Update Documentation

**Files to update:**
- `packages/instructor/README.md`
- `packages/instructor/OVERVIEW.md`
- `packages/instructor/INTERNALS.md`
- `packages/instructor/ITERATIVE_EXECUTION.md` (mark as complete)

**Actions:**
- Remove references to old architecture
- Document new execution flow
- Update diagrams

---

### Step 6.2: Update CLAUDE.md

**File:** `/home/ddebowczyk/projects/instructor-php/CLAUDE.md`

**Actions:**
- Document new architecture principles
- Update development patterns

---

### Step 6.3: Clean Up Configuration

**Files:**
- `packages/instructor/src/Config/StructuredOutputConfig.php`
- Environment/config documentation

**Actions:**
- Remove deprecated config options
- Document new config structure

---

## Phase 7: Performance & Validation

### Step 7.1: Benchmark Performance

**Tool:** PHPBench or custom benchmarks

**Metrics:**
- Sync execution time
- Streaming execution time
- Memory usage
- CPU usage

**Acceptance:** < 5% variance from baseline (before refactoring).

---

### Step 7.2: Run Full Test Suite

```bash
cd packages/instructor
composer test
composer phpstan
composer psalm
```

**Acceptance:** All tests pass, no static analysis errors.

---

### Step 7.3: Manual Testing

**Scenarios:**
1. Simple sync extraction
2. Complex streaming extraction
3. Retry on validation failure
4. Retry on connection error
5. Max retries reached
6. Sequence streaming
7. Partial streaming with validation

**Acceptance:** All scenarios work as expected.

---

## Rollback Plan

If issues arise, rollback is straightforward since this is internal refactoring:

1. **Revert commits** from latest back to before Phase 1
2. **Restore adapter** - `IterativeToGeneratorAdapter` acts as compatibility layer
3. **Restore legacy handlers** - Still coexist with new architecture
4. **Restore old ExecutorFactory config** - Support both modes

**Key:** Iterative commits with working tests at each phase enable safe rollback.

---

## Estimated Timeline

| Phase | Effort | Risk |
|-------|--------|------|
| Phase 1: Foundation | 4-6 hours | Low |
| Phase 2: Update Consumers | 2-3 hours | Low |
| Phase 3: Update ExecutorFactory | 3-4 hours | Medium |
| Phase 4: Remove Legacy | 1-2 hours | Low |
| Phase 5: Update Tests | 6-8 hours | Medium |
| Phase 6: Documentation | 2-3 hours | Low |
| Phase 7: Validation | 2-4 hours | Low |
| **Total** | **20-30 hours** | **Low-Medium** |

---

## Success Metrics

- [ ] All tests pass
- [ ] No performance regression (< 5% variance)
- [ ] Public APIs unchanged
- [ ] Zero `CanExecuteStructuredOutput` references
- [ ] Zero `IterativeToGeneratorAdapter` references
- [ ] All execution through `AttemptIterator`
- [ ] Clean separation: streaming vs attempts
- [ ] Simplified configuration
- [ ] Documentation updated

---

## Next Actions

1. **Review this plan** with team/maintainers
2. **Create feature branch**: `refactor/clean-attempt-architecture`
3. **Start Phase 1, Step 1.1**: Create `SyncUpdateGenerator`
4. **Commit frequently** with passing tests
5. **Open PR** after Phase 3 (core complete)
6. **Merge after validation** (Phase 7)

---

## Notes

- Each phase should be independently committable
- Tests must pass after each step
- Use feature toggles if needed for gradual rollout
- Consider parallel work on documentation (Phase 6)

---

**Questions or blockers?** Add them here:

- [ ] Review `ResponseNormalizer` usage in sync execution
- [ ] Confirm configuration migration path acceptable
- [ ] Verify no external packages depend on `CanExecuteStructuredOutput`
