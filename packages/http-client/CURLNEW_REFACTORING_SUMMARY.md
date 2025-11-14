# CurlNew Refactoring Summary

## Overview

Successfully refactored CurlNewPool and CurlNewDriver based on senior developer feedback, eliminating arrays-as-collections, extracting reusable components, and improving code clarity.

---

## Changes Made

### 1. New Value Objects (Replacing Arrays)

**ActiveTransfer.php** (22 lines)
- Readonly value object for single active transfer
- Replaces: `['handle' => $handle, 'parser' => $parser, 'request' => $request, 'index' => $index]`

**ActiveTransfers.php** (65 lines)
- Type-safe collection for managing active transfers
- Methods: `add()`, `getByNativeHandle()`, `removeByHandle()`, `count()`, `isEmpty()`, `hasCapacity()`
- Replaces: `$active[$handleId] = [...];` array manipulation

**PoolResponses.php** (42 lines)
- Collection for HTTP responses with guaranteed ordering
- Methods: `set()`, `count()`, `finalize()`
- Replaces: `$responses[$index] = $result;` followed by `ksort()` and `array_values()`

### 2. Shared Error Mapper

**CurlErrorMapper.php** (60 lines)
- Maps curl error codes to domain exceptions
- Shared between CurlNewDriver and CurlNewPool
- Centralizes: TimeoutException, ConnectionException, NetworkException mapping
- Eliminates duplicate error mapping logic

### 3. Execution State Object

**PoolState.php** (64 lines)
- Encapsulates all mutable pool execution state
- Fields: `queue`, `nextIndex`, `maxConcurrent`, `activeTransfers`, `responses`
- Methods: `hasMoreRequests()`, `nextRequest()`, `currentIndex()`, `isComplete()`
- Eliminates parameter passing by reference

### 4. Robust Event Loop Runner

**CurlMultiRunner.php** (237 lines)
- Isolated, testable event loop execution
- Handles:
  - `CURLM_CALL_MULTI_PERFORM` - continues instead of breaking
  - `curl_multi_select()` returning -1 - uses `curl_multi_wait()` or `usleep()` fallback
  - Drains all messages from `curl_multi_info_read()`
- Rolling window concurrency management
- Complete separation from orchestration logic

### 5. Refactored Components

**CurlNewPool.php**
- **Before:** 374 lines (monolithic)
- **After:** 82 lines (orchestrator)
- **Reduction:** 78% (292 lines eliminated)

**CurlNewDriver.php**
- **Before:** 186 lines (inline error mapping)
- **After:** 171 lines (uses CurlErrorMapper)
- **Reduction:** 8% (15 lines, cleaner code)

---

## Metrics

### Code Size

| Component | Before | After | Change |
|-----------|--------|-------|--------|
| CurlNewPool | 374 | 82 | -78% |
| CurlNewDriver | 186 | 171 | -8% |
| **Total Driver Code** | **560** | **253** | **-55%** |

### New Shared Components

| Component | Lines | Purpose |
|-----------|-------|---------|
| ActiveTransfer | 22 | Value object |
| ActiveTransfers | 65 | Collection |
| PoolResponses | 42 | Collection |
| PoolState | 64 | State encapsulation |
| CurlErrorMapper | 60 | Error mapping |
| CurlMultiRunner | 237 | Event loop |
| **Total** | **490** | **Reusable, testable** |

### Net Impact

- **Removed:** 307 lines of driver code
- **Added:** 490 lines of reusable components
- **Effective:** +183 lines, but:
  - Components are isolated and unit-testable
  - CurlErrorMapper shared between driver and pool
  - No arrays-as-collections violations
  - Clear separation of concerns
  - Robust curl_multi handling

---

## Before/After Comparison

### CurlNewPool

**Before (374 lines):**
```php
final class CurlNewPool {
    private readonly CurlFactory $factory;

    public function pool(array $requests, ?int $maxConcurrent = null): array {
        // Inline state management
        $queue = array_values($requests);
        $queueIndex = 0;
        $responses = [];
        $active = []; // Array-as-collection ❌

        // Inline window filling
        $this->fillWindow($multiHandle, $queue, $queueIndex, $maxConcurrent, $active);

        // Inline event loop with parameter passing by reference
        $this->driveMultiHandle($multiHandle, $queue, $queueIndex, $maxConcurrent, $active, $responses);

        // Manual normalization
        return $this->finalizeResponses($responses);
    }

    // 300+ lines of inline logic...
}
```

**After (82 lines):**
```php
final class CurlNewPool {
    private readonly CurlMultiRunner $runner;

    public function pool(array $requests, ?int $maxConcurrent = null): array {
        // Create execution state
        $state = new PoolState(
            requests: $requests,
            maxConcurrent: $maxConcurrent ?? $this->config->maxConcurrent ?? 5,
            activeTransfers: new ActiveTransfers(), // Type-safe collection ✅
            responses: new PoolResponses(), // Type-safe collection ✅
        );

        // Delegate to runner
        $multiHandle = curl_multi_init();
        try {
            $this->runner->execute($multiHandle, $state);
        } finally {
            curl_multi_close($multiHandle);
        }

        return $state->responses->finalize();
    }
}
```

### CurlNewDriver

**Before (inline error mapping):**
```php
private function handleError(CurlHandle $handle, HttpRequest $request): never {
    $errorCode = $handle->errorCode();
    $errorMessage = $handle->error() ?? 'Unknown error';

    $exception = match (true) {
        in_array($errorCode, [CURLE_OPERATION_TIMEDOUT, CURLE_OPERATION_TIMEOUTED])
            => new TimeoutException($errorMessage, $request, null),
        in_array($errorCode, [
            CURLE_COULDNT_CONNECT,
            CURLE_COULDNT_RESOLVE_HOST,
            CURLE_COULDNT_RESOLVE_PROXY,
            CURLE_SSL_CONNECT_ERROR,
        ]) => new ConnectionException($errorMessage, $request, null),
        default => new NetworkException($errorMessage, $request, null, null),
    };

    $this->dispatchRequestFailed($exception, $request);
    throw $exception;
}
```

**After (shared mapper):**
```php
private readonly CurlErrorMapper $errorMapper;

private function handleError(CurlHandle $handle, HttpRequest $request): never {
    $exception = $this->errorMapper->mapError(
        $handle->errorCode(),
        $handle->error() ?? 'Unknown error',
        $request,
    );

    $this->dispatchRequestFailed($exception, $request);
    throw $exception;
}
```

---

## Alignment with CLAUDE.md

### ✅ No Arrays as Collections
**Before:** `$active[$handleId] = ['handle' => ..., 'parser' => ..., 'request' => ..., 'index' => ...];`

**After:** `$activeTransfers->add(new ActiveTransfer($handle, $parser, $request, $index));`

### ✅ Reduced Nesting
**Before:** Complex nested loops with multiple conditions

**After:** Flat orchestration, complex logic in CurlMultiRunner

### ✅ Single Responsibility
- **CurlNewPool:** Orchestration only
- **CurlMultiRunner:** Event loop execution
- **PoolState:** State management
- **ActiveTransfers/PoolResponses:** Collection management
- **CurlErrorMapper:** Error translation

### ✅ Prefer Immutability
- **ActiveTransfer:** Readonly value object
- **CurlErrorMapper:** Stateless
- **PoolState:** Minimal, scoped mutability

### ✅ No Deep Nesting
Event loop logic isolated in runner, avoiding nested conditionals in main class.

---

## Robustness Improvements

### 1. CURLM_CALL_MULTI_PERFORM Handling
**Before:** Not handled (could break prematurely)

**After:**
```php
if ($status === CURLM_CALL_MULTI_PERFORM) {
    continue; // Keep executing instead of breaking
}
```

### 2. curl_multi_select -1 Handling
**Before:** Could cause busy loop

**After:**
```php
$selected = curl_multi_select($multiHandle, 0.1);
if ($selected === -1) {
    if (function_exists('curl_multi_wait')) {
        curl_multi_wait($multiHandle, 0.01);
    } else {
        usleep(1000); // Avoid busy loop
    }
}
```

### 3. Message Draining
**Before:** Processed one message at a time

**After:**
```php
// Drain ALL messages - don't stop at first one
while ($info = curl_multi_info_read($multiHandle)) {
    // Process each completion...
}
```

---

## Testing

All existing tests pass without modification:

**CurlNewDriverTest.php**
- ✅ 8 tests passed (19 assertions)
- ✅ All functionality preserved

**CurlNewPoolTest.php**
- ✅ 11 tests passed (50 assertions)
- ✅ Rolling window concurrency works
- ✅ Error handling preserved
- ✅ Response ordering maintained

---

## Benefits

### 1. Clarity
- Clear separation: orchestration vs execution vs state
- No magic arrays with string keys
- Explicit type-safe collections

### 2. Testability
- CurlMultiRunner is isolated and unit-testable
- Collections can be tested independently
- Error mapper can be tested independently

### 3. Maintainability
- Smaller classes with single responsibilities
- Shared error mapper (DRY)
- Less parameter passing
- No by-reference mutations

### 4. Robustness
- Proper curl_multi edge case handling
- No busy loops on select -1
- Complete message draining

### 5. Reusability
- CurlErrorMapper shared between driver and pool
- Collections can be reused in other contexts
- CurlMultiRunner could be adapted for other use cases

---

## Future Opportunities

1. **AbstractPool base class** - Extract event dispatching from CurlMultiRunner to shared base
2. **Unit tests** - Add isolated tests for CurlMultiRunner, collections, error mapper
3. **Apply pattern to other pools** - SymfonyPool, LaravelPool, GuzzlePool could benefit from similar structure
4. **AbstractDriver base class** - Extract common driver patterns as identified in architecture analysis

---

## Conclusion

The refactoring successfully addresses all senior developer feedback:

- ✅ Eliminated arrays-as-collections
- ✅ Removed excessive parameter passing
- ✅ Extracted robust event loop runner
- ✅ Shared error mapper with driver
- ✅ Improved curl_multi edge case handling
- ✅ Clear separation of concerns
- ✅ All tests pass

**CurlNewPool** went from 374 lines of monolithic code to 82 lines of clean orchestration - a **78% reduction** while gaining robustness, testability, and clarity.
