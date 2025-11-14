# HTTP Pool Architecture Analysis

## Current Implementation Comparison

### Overview

| Pool | Lines | Strategy | Modularity | Error Handling | Dependencies |
|------|-------|----------|------------|----------------|--------------|
| CurlNew | 374 | Rolling window | High (4 components) | Specific exceptions | None |
| Guzzle | 162 | Framework-driven | Medium | Generic | GuzzleHttp |
| Symfony | 257 | Streaming API | Low | Mixed | Symfony HttpClient |
| Laravel | 144 | Batching | Low | Simple | Laravel HTTP |

---

## Detailed Analysis

### 1. CurlNewPool - The Modular Reference

**Architecture:**
```
CurlNewPool (orchestrator)
├── CurlFactory (configuration - reusable)
├── HeaderParser (parsing - single responsibility)
├── CurlHandle (resource lifecycle - automatic cleanup)
└── PoolCurlResponseAdapter (response wrapping)
```

**Strengths:**
- ✅ **Zero duplication** - CurlFactory shared with CurlNewDriver
- ✅ **Clear separation** - Each component has single responsibility
- ✅ **Rolling window** - Optimal concurrency (fills slots as requests complete)
- ✅ **Explicit resource management** - CurlHandle with destructors
- ✅ **Specific error types** - TimeoutException, ConnectionException, NetworkException
- ✅ **Well-documented** - Clear comments explain curl_multi limitations
- ✅ **Type-safe** - Strong typing throughout

**Structure:**
1. `executePool()` - Main orchestration
2. `fillWindow()` - Initialize concurrent slots
3. `driveMultiHandle()` - Event loop
4. `processCompleted()` - Handle finished transfers
5. `attachRequest()` / `detachHandle()` - Resource management
6. `createResponse()` - Response creation with validation

**Key Insight:**
> Uses curl_multi_getcontent (not curl_exec) because curl_multi has already driven the transfer. This is properly documented in comments.

---

### 2. SymfonyPool - Framework Complexity

**Structure:**
```
SymfonyPool
├── prepareHttpResponses() - Create Symfony responses
├── processHttpResponses() - Stream processing with chunks
├── handleTimeout() / checkForErrors() / processLastChunk()
└── Multiple nested try-catch blocks
```

**Issues:**
- ❌ **Mixed concerns** - Request prep, streaming, error handling interleaved
- ❌ **Nested complexity** - Try-catch within foreach within try-catch
- ❌ **Code duplication** - Error handling repeated in multiple places
- ❌ **Unclear flow** - Chunk streaming makes control flow hard to follow
- ❌ **Generic errors** - All exceptions become HttpRequestException
- ⚠️ **isPoolComplete()** - Unnecessary method (just checks count)

**Error Handling Scatter:**
- Line 68-82: prepareRequest errors
- Line 88-94: Timeout handling
- Line 102-110: Transport exceptions
- Line 112-121: General exceptions
- Line 153-180: checkForErrors method

---

### 3. LaravelPool - Simplest but Suboptimal

**Structure:**
```
LaravelPool
├── pool() - Splits into batches
├── processBatch() - Uses Laravel's Pool API
├── createPoolRequests() - Maps HttpRequest → Laravel
└── processBatchResponses() - Match-based type handling
```

**Issues:**
- ❌ **Batching strategy** - Serializes batches instead of rolling window
  ```php
  $batches = array_chunk($requests, $maxConcurrent);
  foreach ($batches as $batch) { // ← Each batch waits for previous!
      $batchResponses = $this->processBatch($batch);
  ```
- ❌ **Less efficient** - 100 requests with concurrency=10 takes 10 rounds
- ✅ **Simple** - Leverages Laravel's built-in Pool
- ✅ **Clean match** - Nice pattern matching for response types (line 101-106)

**Example Inefficiency:**
- CurlNew: 100 requests @ 10 concurrent ≈ time of slowest + 9 others
- Laravel: 100 requests @ 10 concurrent = 10 × (time of slowest in each batch)

---

### 4. GuzzlePool - Clean but Framework-Dependent

**Structure:**
```
GuzzlePool
├── createRequestGenerator() - Yields PSR-7 requests
├── createPoolConfiguration() - Callbacks for fulfilled/rejected
├── handleFulfilledResponse() / handleRejectedResponse()
└── Pool execution via promise->wait()
```

**Strengths:**
- ✅ **Generator pattern** - Memory efficient request creation
- ✅ **Clear callbacks** - Fulfilled/rejected handlers
- ✅ **Simple structure** - Leverages Guzzle's Pool well

**Issues:**
- ⚠️ **Missing event** - Comment on line 124: "TODO: we don't know how to handle this atm"
- ⚠️ **Generic errors** - No specific exception types
- ⚠️ **Nullable events** - Runtime check instead of type safety

---

## Common Patterns Across All Pools

### 1. **Result Wrapping**
All pools return `array<Result<HttpResponse, Throwable>>`:
```php
// Success
Result::success($response)

// Failure
Result::failure($exception)
```

### 2. **Event Dispatching**
Three events in all pools:
- `HttpRequestSent` - Before request execution
- `HttpResponseReceived` - After successful response
- `HttpRequestFailed` - On error

### 3. **Response Normalization**
All pools maintain request order:
```php
ksort($responses);
return array_values($responses);
```

### 4. **Config Handling**
```php
$maxConcurrent = $maxConcurrent ?? $this->config->maxConcurrent;
```

### 5. **failOnError Handling**
```php
if ($this->config->failOnError) {
    throw $exception;
}
return Result::failure($exception);
```

---

## Standardization Opportunities

### 1. Abstract Base Pool Class

Extract common patterns:

```php
abstract class AbstractPool implements CanHandleRequestPool
{
    public function __construct(
        protected HttpClientConfig $config,
        protected EventDispatcherInterface $events,
    ) {}

    // Template method
    final public function pool(array $requests, ?int $maxConcurrent = null): array {
        $this->validateRequests($requests);
        $maxConcurrent = $maxConcurrent ?? $this->config->maxConcurrent;

        $results = $this->executePool($requests, $maxConcurrent);

        return $this->normalizeResponses($results);
    }

    // Implemented by subclasses
    abstract protected function executePool(array $requests, int $maxConcurrent): array;

    // Common implementations
    protected function validateRequests(array $requests): void { /* ... */ }
    protected function normalizeResponses(array $responses): array { /* ... */ }
    protected function dispatchRequestSent(HttpRequest $request): void { /* ... */ }
    protected function dispatchResponseReceived(int $statusCode): void { /* ... */ }
    protected function dispatchRequestFailed(\Throwable $e, HttpRequest $request): void { /* ... */ }
    protected function handleErrorOrFail(\Throwable $e): Result { /* ... */ }
}
```

**Benefits:**
- Eliminates 50+ lines of duplication per pool
- Ensures consistent event dispatching
- Standardizes error handling
- Makes testing easier

---

### 2. Separate Components Pattern (CurlNew Model)

Each pool should follow the component structure:

```
Pool (orchestrator)
├── Factory (configuration)
├── Parser (response parsing)
├── Handle (resource lifecycle)
└── Adapter (response wrapping)
```

**Apply to Symfony:**
```php
SymfonyPool
├── SymfonyRequestFactory (create Symfony requests)
├── SymfonyResponseParser (parse chunks → response)
├── SymfonyStreamHandle (manage streaming lifecycle)
└── SymfonyResponseAdapter (already exists)
```

**Apply to Laravel:**
```php
LaravelPool
├── LaravelRequestFactory (HttpRequest → Laravel request)
├── LaravelPoolStrategy (rolling window instead of batching)
├── LaravelPoolHandle (manage pool lifecycle)
└── LaravelResponseAdapter (already exists)
```

**Apply to Guzzle:**
```php
GuzzlePool
├── GuzzleRequestFactory (PSR-7 creation)
├── GuzzleResponseParser (PSR-7 → HttpResponse)
├── GuzzlePromiseHandle (promise lifecycle)
└── PsrResponseAdapter (already exists)
```

---

### 3. Rolling Window Concurrency Strategy

**Problem:** LaravelPool uses batching which serializes:
```php
// CURRENT (inefficient)
$batches = array_chunk($requests, $maxConcurrent);
foreach ($batches as $batch) {
    $responses = $this->processBatch($batch); // Waits for all
}
```

**Solution:** Implement rolling window:
```php
// PROPOSED (efficient)
class RollingWindowStrategy {
    public function execute(
        array $requests,
        int $maxConcurrent,
        callable $executor
    ): array {
        $queue = array_values($requests);
        $active = [];
        $results = [];
        $index = 0;

        // Fill window
        while ($index < min($maxConcurrent, count($queue))) {
            $active[$index] = $executor($queue[$index], $index);
            $index++;
        }

        // Process completions, fill slots
        while (!empty($active)) {
            $completed = $this->waitForAny($active);
            $results[$completed['index']] = $completed['result'];
            unset($active[$completed['index']]);

            if ($index < count($queue)) {
                $active[$index] = $executor($queue[$index], $index);
                $index++;
            }
        }

        ksort($results);
        return array_values($results);
    }
}
```

---

### 4. Standardized Error Handling

**Current:** Each pool has different error handling:
- CurlNew: Uses HttpExceptionFactory for specific types
- Others: Generic HttpRequestException

**Proposed:** All pools use HttpExceptionFactory:

```php
// In AbstractPool
protected function createException(
    int $statusCode,
    HttpRequest $request,
    ?HttpResponse $response,
): HttpRequestException {
    return HttpExceptionFactory::fromStatusCode(
        $statusCode,
        $request,
        $response,
        null
    );
}

protected function handleCurlError(
    int $errorCode,
    string $message,
    HttpRequest $request,
): HttpRequestException {
    return match (true) {
        in_array($errorCode, [CURLE_OPERATION_TIMEDOUT, CURLE_OPERATION_TIMEOUTED])
            => new TimeoutException($message, $request, null),
        in_array($errorCode, [CURLE_COULDNT_CONNECT, CURLE_COULDNT_RESOLVE_HOST])
            => new ConnectionException($message, $request, null),
        default => new NetworkException($message, $request, null, null),
    };
}
```

---

### 5. Response Processing Pipeline

Standardize the execution flow:

```php
interface PoolExecutionPipeline {
    // Phase 1: Attach
    public function attachRequest(mixed $handle, HttpRequest $request, int index): void;

    // Phase 2: Execute
    public function executeTransfers(mixed $handle): void;

    // Phase 3: Process
    public function processCompleted(mixed $handle): Result;

    // Phase 4: Detach
    public function detachRequest(mixed $handle, int index): void;
}
```

Each pool implements these phases according to its underlying client.

---

## Proposed Refactoring Roadmap

### Phase 1: Extract Common Base
1. Create `AbstractPool` with common methods
2. Move event dispatching to base
3. Move response normalization to base
4. Move validation to base

**Files affected:**
- New: `src/Drivers/AbstractPool.php`
- Update: All pool classes extend AbstractPool

---

### Phase 2: Refactor LaravelPool
1. Replace batching with rolling window
2. Extract `LaravelRequestFactory`
3. Extract `LaravelPoolStrategy`

**Impact:** Performance improvement for large request pools

---

### Phase 3: Modularize SymfonyPool
1. Extract `SymfonyRequestFactory`
2. Extract `SymfonyChunkProcessor`
3. Simplify error handling (remove nesting)
4. Use HttpExceptionFactory

**Impact:** ~50 line reduction, clearer structure

---

### Phase 4: Enhance GuzzlePool
1. Extract `GuzzleRequestFactory`
2. Add missing error event (line 124)
3. Make events non-nullable
4. Use HttpExceptionFactory

**Impact:** Complete error handling, type safety

---

### Phase 5: Create Pool Utilities
1. `RollingWindowStrategy` - Reusable concurrency
2. `PoolErrorHandler` - Standardized error handling
3. `PoolEventDispatcher` - Consistent events

**Impact:** Shared utilities reduce duplication

---

## Metrics Comparison

### Code Duplication

**Current:**
- Event dispatching: ~30 lines × 4 pools = 120 lines
- Response normalization: ~5 lines × 4 pools = 20 lines
- Request validation: ~10 lines × 4 pools = 40 lines
- Error handling patterns: ~40 lines × 4 pools = 160 lines
- **Total duplication: ~340 lines**

**After refactoring:**
- AbstractPool: ~150 lines (implements all common patterns)
- Duplication eliminated: ~190 lines
- Net reduction: ~20% of total pool code

### Complexity Reduction

| Pool | Current Complexity | After Refactoring | Reduction |
|------|-------------------|-------------------|-----------|
| CurlNew | Low (already modular) | Low | 0% |
| Guzzle | Medium | Low | 30% |
| Symfony | High | Medium | 40% |
| Laravel | Medium | Low | 35% |

---

## Recommendations

### Immediate (High Priority)
1. ✅ **Create AbstractPool** - Eliminate duplication
2. ✅ **Fix LaravelPool batching** - Performance issue
3. ✅ **Use HttpExceptionFactory** - Consistent errors

### Short-term (Medium Priority)
4. ✅ **Modularize SymfonyPool** - Reduce complexity
5. ✅ **Extract factories** - Reusable configuration
6. ✅ **Complete GuzzlePool** - Fix TODOs

### Long-term (Low Priority)
7. ⚠️ **Create shared utilities** - RollingWindowStrategy, etc.
8. ⚠️ **Add pool benchmarks** - Measure improvements
9. ⚠️ **Document patterns** - Architecture guide

---

## Conclusion

**CurlNewPool demonstrates the ideal architecture:**
- Modular components with single responsibilities
- Zero code duplication (shares CurlFactory)
- Clear separation of concerns
- Explicit resource management
- Comprehensive error handling
- Well-documented design decisions

**Other pools should adopt:**
1. Component separation (Factory, Parser, Handle, Adapter)
2. Rolling window concurrency
3. HttpExceptionFactory for errors
4. AbstractPool for common patterns
5. Explicit resource lifecycle

**Expected outcomes:**
- ~20% code reduction through deduplication
- 30-40% complexity reduction in Symfony/Guzzle/Laravel
- Performance improvement in Laravel (rolling window vs batching)
- Consistent error handling across all drivers
- Easier testing and maintenance

---

## Next Steps

1. Review this analysis with team
2. Prioritize refactoring phases
3. Create implementation tasks
4. Write tests for AbstractPool
5. Refactor pools one at a time
6. Update documentation

