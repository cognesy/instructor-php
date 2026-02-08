# Low-Hanging Fruits for `packages/polyglot/src`

## 🔴 High Priority (Correctness Bugs)

### 1. `InferenceRequest::toArray()` returns objects instead of arrays
- **File:** `Inference/Data/InferenceRequest.php:314-325`
- **Issue:** Returns `Messages` and `ResponseFormat` objects instead of arrays, breaking JSON encoding, logging, and translators
- **Fix:**
```php
public function toArray() : array {
    return [
        'messages' => $this->messages->toArray(),
        // ...
        'response_format' => $this->responseFormat->toArray(),
        // ...
    ];
}
```

### 2. `InferenceRequest::hasMessages()` uses `empty()` on object
- **File:** `Inference/Data/InferenceRequest.php:193-195`
- **Issue:** `empty($this->messages)` on a `Messages` object is always `false` when non-null
- **Fix:**
```php
public function hasMessages(): bool {
    return !$this->messages->isEmpty()
        || ($this->cachedContext !== null && !$this->cachedContext->messages()->isEmpty());
}
```

### 3. `InferenceRequest::withCacheApplied()` uses `empty()` on objects
- **File:** `Inference/Data/InferenceRequest.php:288-305`
- **Issue:** `empty($this->responseFormat)` always false for objects, so cached context never applies
- **Fix:** Use `$this->responseFormat->isEmpty()` instead of `empty($this->responseFormat)`

### 4. `PartialInferenceResponse::toArray()` stores object instead of array
- **File:** `Inference/Data/PartialInferenceResponse.php:327`
- **Issue:** `'response_data' => $this->responseData` stores object, but `fromArray()` expects array
- **Fix:**
```php
'response_data' => $this->responseData?->toArray(),
```

---

## 🟠 High Priority (Observability/Behavior)

### 5. Duplicate completion events for streamed requests
- **File:** `Inference/PendingInference.php` + `Inference/Streaming/InferenceStream.php`
- **Issue:** For streamed requests, `InferenceAttemptSucceeded`, `InferenceUsageReported`, and `InferenceCompleted` are dispatched twice:
  1. In `InferenceStream::finalizeStream()` via `dispatchStreamCompletionEvents()`
  2. In `PendingInference::response()` via `handleAttemptSuccess()` and `dispatchInferenceCompleted()`
- **Fix:** Remove or gate `dispatchStreamCompletionEvents()` in `InferenceStream`; keep completion events exclusively in `PendingInference`

### 6. Attempt IDs not unique per retry attempt
- **File:** `Inference/PendingInference.php:336-340`
- **Issue:** `getCurrentAttemptId()` returns `execution->id` for multiple attempts since no attempt object is added at attempt-start
- **Fix:** Generate a new UUID in `dispatchAttemptStarted()` and store as `$this->currentAttemptId`:
```php
private ?string $currentAttemptId = null;

private function dispatchAttemptStarted(): void {
    $this->attemptNumber++;
    $this->attemptStartedAt = new DateTimeImmutable();
    $this->currentAttemptId = Uuid::uuid4();
    // ...
}
```

### 7. Streaming path has no retry handling
- **File:** `Inference/Streaming/InferenceStream.php`
- **Issue:** `PendingInference` has retry logic, but streaming is delegated to `InferenceStream` which has no retry support
- **Fix (minimal):** Document that streaming does not retry; optionally emit an event when stream errors occur

---

## 🟡 Medium Priority (Consistency/Clarity)

### 8. Type mismatch in `HandlesRequestBuilder::withToolChoice()`
- **File:** `Inference/Traits/HandlesRequestBuilder.php:35-37`
- **Issue:** Accepts `string` only, but `Inference::with()` and `InferenceRequest` support `string|array`
- **Fix:** Change to `string|array $toolChoice`

### 9. Duplicate code in `Inference::makeInferenceDriver()`
- **File:** `Inference/Inference.php:134-151`
- **Issue:** Repeated `factory->makeDriver(...)` call
- **Fix:**
```php
$explicit = $resolver instanceof HasExplicitInferenceDriver
    ? $resolver->explicitInferenceDriver()
    : null;

return $explicit ?? $this->getInferenceFactory()->makeDriver(
    config: $resolver->resolveConfig(),
    httpClient: $httpClient
);
```

### 10. Inconsistent preset naming
- **Files:** `Inference.php`, `HandlesLLMProvider.php`
- **Issue:** Mixed naming: `withHttpClientPreset()`, `withDebugPreset()`, `withPreset()`
- **Fix:** Pick one convention - either `withHttpClientPreset` + `withHttpDebugPreset` or consolidate

### 11. `InferenceResponse` null-safety in accessors
- **File:** `Inference/Data/InferenceResponse.php:94,98`
- **Issue:** `usage()` and `toolCalls()` have null-coalescing but fields are already initialized in constructor (redundant)
- **Fix:** Remove redundant null checks or make fields nullable

---

## 🟢 Lower Priority (Clean Code)

### 12. `LLMProvider` claims immutability but has mutable methods
- **File:** `Inference/LLMProvider.php:27-33`
- **Comment says:** "Configuration - all immutable after construction"
- **Reality:** Methods like `withLLMPreset()`, `withDsn()` mutate `$this` directly
- **Fix:** Either make truly immutable (clone-on-write) or remove misleading comments

### 13. Complex tool accumulation in `PartialInferenceResponse`
- **File:** `Inference/Data/PartialInferenceResponse.php:190-264`
- **Issue:** `withAccumulatedContent()` is ~75 lines with complex tool-tracking logic
- **Fix (optional):** Extract tool-call accumulation to a dedicated `ToolDeltaAccumulator` class

### 14. `Usage::accumulate()` mutates in mutable class
- **File:** `Inference/Data/Usage.php:67-74`
- **Issue:** `Usage` has mutable `accumulate()` but also immutable `withAccumulated()` - inconsistent API
- **Fix:** Consider making `Usage` readonly and only using `withAccumulated()`

### 15. Indentation issue in `PendingInference::response()`
- **File:** `Inference/PendingInference.php:204-208`
- **Issue:** Misaligned indentation for cache block inside while loop
- **Fix:** Properly indent the `if ($this->shouldCache())` block

---

## Quick Wins Summary

| Issue | File | Effort |
|-------|------|--------|
| Fix `toArray()` objects | InferenceRequest.php:314-325 | 10 min |
| Fix `hasMessages()` empty check | InferenceRequest.php:193-195 | 5 min |
| Fix `withCacheApplied()` empty checks | InferenceRequest.php:288-305 | 10 min |
| Fix `toArray()` response_data | PartialInferenceResponse.php:327 | 2 min |
| Fix type in `withToolChoice()` | HandlesRequestBuilder.php:35 | 2 min |
| Simplify `makeInferenceDriver()` | Inference.php:134-151 | 10 min |
| Fix indentation | PendingInference.php:204-208 | 2 min |
| Remove duplicate events | InferenceStream.php + PendingInference.php | 1-2h |
| Add unique attempt IDs | PendingInference.php | 30 min |

---

## Additional Notes from Oracle Review

### Streaming vs Non-Streaming Event Ownership
Current architecture has unclear ownership of completion events:
- `InferenceStream::finalizeStream()` dispatches completion events for streaming
- `PendingInference::response()` dispatches completion events for all paths

**Recommendation:** Make `PendingInference` the single owner of attempt/completion/usage events; `InferenceStream` only handles partial events and response creation.

### `LLMProvider` Immutability
The class comment claims "immutable after construction" but methods like `withLLMPreset()` mutate `$this` instead of returning new instances. This is confusing and potentially bug-prone.

### Serialization Consistency
Multiple data classes have inconsistent serialization:
- `InferenceRequest::toArray()` returns objects
- `PartialInferenceResponse::toArray()` mixes objects and primitives
- Consider adding integration tests that assert `toArray()` output is JSON-encodable
