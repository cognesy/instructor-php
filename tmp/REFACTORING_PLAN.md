# Refactoring Plan: Remove PartialInferenceResponse, Use InferenceResponse with StreamBuffer

## Overview

**Goal:** Eliminate memory overhead from storing thousands of PartialInferenceResponse objects by:
1. Storing raw SSE chunks in StreamBuffer (append-only, efficient)
2. Using InferenceResponse for both complete and partial responses (via isPartial flag)
3. Removing PartialInferenceResponse and PartialInferenceResponseList entirely

**Impact:** ~97% memory reduction for streaming scenarios (1.5MB → 50KB for 1K chunks)

## Architectural Changes

### Before
```
HTTP → EventStreamReader → ResponseAdapter creates PartialInferenceResponse
→ InferenceStream accumulates in PartialInferenceResponseList
→ Instructor processes PartialInferenceResponse objects
```

### After
```
HTTP → StreamBuffer (raw strings) → EventStreamReader → ResponseAdapter creates InferenceResponse(isPartial=true)
→ InferenceStream uses InferenceResponse → Instructor processes InferenceResponse objects
```

---

## Step-by-Step Refactoring Plan

### Phase 1: Core Data Layer Changes

#### 1.1 Modify InferenceResponse Class
**File:** `packages/polyglot/src/Inference/Data/InferenceResponse.php`

**Changes:**
1. Change class from `final readonly class` to `final class` (need mutable StreamBuffer)
2. Add `private StreamBuffer $streamBuffer` field
3. Keep all existing fields as `readonly` EXCEPT the class itself
4. Add import: `use Cognesy\Http\Data\StreamBuffer;`
5. Initialize `$streamBuffer` in constructor: `$this->streamBuffer = StreamBuffer::empty();`
6. Add method: `public function pushRawChunk(string $chunk): void { $this->streamBuffer->push($chunk); }`
7. Add method: `public function streamBuffer(): StreamBuffer { return $this->streamBuffer; }`
8. Add method: `public function makeBufferReader(): StreamBufferReader { return $this->streamBuffer->makeReader(); }`
9. Remove unused `$contentDelta` and `$reasoningContentDelta` fields (not used in current code)
10. Update factory method `partial()` to return `InferenceResponse` with `isPartial: true`

**Rationale:** InferenceResponse becomes the single response type, with StreamBuffer storing raw chunks efficiently.

#### 1.2 Update PartialInferenceResponse (Temporary Adapter)
**File:** `packages/polyglot/src/Inference/Data/PartialInferenceResponse.php`

**Changes:**
1. Add deprecation notice at class level
2. Keep class for now to minimize breaking changes during refactor
3. Will be deleted in final step

**Rationale:** Keep temporarily to allow incremental migration without breaking everything at once.

---

### Phase 2: Interface Changes

#### 2.1 Update CanTranslateInferenceResponse Interface
**File:** `packages/polyglot/src/Inference/Contracts/CanTranslateInferenceResponse.php`

**Changes:**
```php
interface CanTranslateInferenceResponse
{
    public function fromResponse(HttpResponse $response): ?InferenceResponse;

    // CHANGE: Return InferenceResponse instead of PartialInferenceResponse
    public function fromStreamResponse(string $eventBody): ?InferenceResponse;

    public function toEventBody(string $data): string|bool;
}
```

#### 2.2 Update CanHandleInference Interface
**File:** `packages/polyglot/src/Inference/Contracts/CanHandleInference.php`

**Changes:**
```php
interface CanHandleInference
{
    public function makeResponseFor(InferenceRequest $request) : InferenceResponse;

    // CHANGE: Return InferenceResponse instead of PartialInferenceResponse
    /** @return iterable<InferenceResponse> */
    public function makeStreamResponsesFor(InferenceRequest $request): iterable;

    public function toHttpRequest(InferenceRequest $request): HttpRequest;
    public function httpResponseToInference(HttpResponse $httpResponse): InferenceResponse;

    // CHANGE: Return InferenceResponse instead of PartialInferenceResponse
    /** @return iterable<InferenceResponse> */
    public function httpResponseToInferenceStream(HttpResponse $httpResponse): iterable;
}
```

---

### Phase 3: Driver Response Adapters (Polyglot)

**Update all ResponseAdapter classes to return `InferenceResponse` instead of `PartialInferenceResponse`**

#### 3.1 OpenAI Response Adapter
**File:** `packages/polyglot/src/Inference/Drivers/OpenAI/OpenAIResponseAdapter.php`

**Method:** `fromStreamResponse(string $eventBody): ?InferenceResponse`

**Changes:**
1. Change return type from `?PartialInferenceResponse` to `?InferenceResponse`
2. Parse SSE event body (unchanged)
3. Extract delta fields (unchanged)
4. Instead of: `return new PartialInferenceResponse(...)`
5. Use: `return new InferenceResponse(..., isPartial: true)`
6. Map all delta fields to InferenceResponse constructor parameters
7. Store raw event body: before returning, call `$response->pushRawChunk($eventBody)`

**Key mapping:**
- `contentDelta` → `content` (accumulated in InferenceStream)
- `reasoningContentDelta` → `reasoningContent`
- `toolArgs` → include in `responseData` array
- `toolName` → include in `responseData` array
- Set `isPartial: true`

#### 3.2 Anthropic Response Adapter
**File:** `packages/polyglot/src/Inference/Drivers/Anthropic/AnthropicResponseAdapter.php`

**Apply same changes as OpenAI above.**

**Additional Anthropic-specific:**
- Handle `content_block_delta` events
- Handle `content_block_start` events
- Map Anthropic's tool use blocks to InferenceResponse structure

#### 3.3 Gemini Response Adapter
**File:** `packages/polyglot/src/Inference/Drivers/Gemini/GeminiResponseAdapter.php`

**Apply same changes as OpenAI above.**

**Gemini-specific:**
- Handle their streaming format differences
- Map function calls to tool structure

#### 3.4 Other Driver Adapters
**Files:**
- `packages/polyglot/src/Inference/Drivers/CohereV2/CohereV2ResponseAdapter.php`
- `packages/polyglot/src/Inference/Drivers/Deepseek/DeepseekResponseAdapter.php`
- All other driver adapters in `packages/polyglot/src/Inference/Drivers/*/`

**For each:**
1. Update `fromStreamResponse()` return type to `?InferenceResponse`
2. Change instantiation from `PartialInferenceResponse` to `InferenceResponse(isPartial: true)`
3. Call `pushRawChunk()` before returning

---

### Phase 4: Base Driver & Stream Classes

#### 4.1 Update BaseInferenceDriver
**File:** `packages/polyglot/src/Inference/Drivers/BaseInferenceDriver.php`

**Method:** `httpResponseToInferenceStream(HttpResponse $httpResponse): iterable`

**Changes:**
```php
// OLD return type:
/** @return iterable<PartialInferenceResponse> */

// NEW return type:
/** @return iterable<InferenceResponse> */

public function httpResponseToInferenceStream(HttpResponse $httpResponse): iterable {
    $reader = new EventStreamReader(
        events: $this->events,
        parser: $this->toEventBody(...),
    );
    try {
        foreach($reader->eventsFrom($httpResponse->stream()) as $eventBody) {
            // CHANGE: Now returns InferenceResponse
            $partialResponse = $this->responseTranslator->fromStreamResponse($eventBody);
            if ($partialResponse === null) {
                continue;
            }
            // partialResponse is now InferenceResponse with isPartial=true
            yield $partialResponse;
        }
    } catch (Exception $e) {
        // ... error handling unchanged
    }
}
```

**Method:** `makeStreamResponsesFor(InferenceRequest $request): iterable`

**Changes:**
```php
// OLD return type:
/** @return iterable<PartialInferenceResponse> */

// NEW return type:
/** @return iterable<InferenceResponse> */
public function makeStreamResponsesFor(InferenceRequest $request): iterable {
    // implementation unchanged, just type annotation
}
```

#### 4.2 Update InferenceStream
**File:** `packages/polyglot/src/Inference/Streaming/InferenceStream.php`

**Changes:**

**Field update:**
```php
// OLD:
/** @var iterable<PartialInferenceResponse> */
protected iterable $stream;

// NEW:
/** @var iterable<InferenceResponse> */
protected iterable $stream;
```

**Method signature updates:**
```php
// OLD:
/** @var (Closure(PartialInferenceResponse): void)|null */
protected ?Closure $onPartialResponse = null;

// NEW:
/** @var (Closure(InferenceResponse): void)|null */
protected ?Closure $onPartialResponse = null;
```

```php
// OLD:
public function responses(): Generator<PartialInferenceResponse>

// NEW:
public function responses(): Generator<InferenceResponse>
```

**Method `makePartialResponses()` update:**
```php
// OLD signature:
private function makePartialResponses(iterable $stream): Generator<PartialInferenceResponse>

// NEW signature:
private function makePartialResponses(iterable $stream): Generator<InferenceResponse>

// OLD body:
/** @var PartialInferenceResponse $partialResponse */
foreach ($stream as $partialResponse) {
    // ...
    $partialResponse = $partialResponse->withAccumulatedContent($priorResponse);
    // ...
}

// NEW body:
/** @var InferenceResponse $partialResponse */
foreach ($stream as $partialResponse) {
    // Check if it's partial
    if (!$partialResponse->isPartial()) {
        continue; // Skip complete responses in stream
    }

    // Accumulate content manually (no more withAccumulatedContent method)
    $accumulatedContent = ($priorResponse?->content() ?? '') . $partialResponse->content();
    $accumulatedReasoning = ($priorResponse?->reasoningContent() ?? '') . $partialResponse->reasoningContent();

    $partialResponse = $partialResponse->with(
        content: $accumulatedContent,
        reasoningContent: $accumulatedReasoning,
        finishReason: $partialResponse->finishReason() ?: $priorResponse?->finishReason(),
    );

    $this->notifyOnPartialResponse($partialResponse);
    yield $partialResponse;
    $priorResponse = $partialResponse;
}
```

**Method `notifyOnPartialResponse()` update:**
```php
// OLD:
private function notifyOnPartialResponse(PartialInferenceResponse $enrichedResponse): void

// NEW:
private function notifyOnPartialResponse(InferenceResponse $enrichedResponse): void
```

**All method signatures using PartialInferenceResponse:**
- `map()`: Update callable signature
- `filter()`: Update callable signature
- `reduce()`: Update callable signature
- `all()`: Return `array<InferenceResponse>`

---

### Phase 5: InferenceExecution & InferenceAttempt

#### 5.1 Update InferenceAttempt
**File:** `packages/polyglot/src/Inference/Data/InferenceAttempt.php`

**Changes:**

**Field update:**
```php
// OLD:
private PartialInferenceResponseList $partialResponses;

// NEW: Store StreamBuffer reference instead
private ?StreamBuffer $streamBuffer = null;
```

**Constructor:**
```php
public function __construct(
    // ... other params
    ?StreamBuffer $streamBuffer = null,
    // Remove: ?PartialInferenceResponseList $partialResponses = null,
) {
    // ...
    $this->streamBuffer = $streamBuffer;
}
```

**Method changes:**
```php
// REMOVE entirely (no longer needed):
public function partialResponses(): PartialInferenceResponseList

// ADD:
public function streamBuffer(): ?StreamBuffer {
    return $this->streamBuffer;
}

// ADD: Lazy reconstruction if needed for backward compat
public function reconstructPartials(): array {
    if ($this->streamBuffer === null) {
        return [];
    }
    // Parse buffer chunks if really needed (rare - only for debugging)
    // Implementation: replay through parser
    return []; // Most code shouldn't need this
}
```

**Mutator update:**
```php
// OLD:
public function withNewPartialResponse(PartialInferenceResponse $response): self

// NEW:
public function withStreamBuffer(StreamBuffer $buffer): self {
    return new self(
        // ... other params
        streamBuffer: $buffer,
    );
}
```

#### 5.2 Update InferenceExecution
**File:** `packages/polyglot/src/Inference/Data/InferenceExecution.php`

**Changes:**

**Method removal:**
```php
// REMOVE:
public function partialResponses(): PartialInferenceResponseList {
    return $this->currentAttempt?->partialResponses() ?? PartialInferenceResponseList::empty();
}
```

**Add StreamBuffer access:**
```php
// ADD:
public function streamBuffer(): ?StreamBuffer {
    return $this->currentAttempt?->streamBuffer();
}
```

**Update usage() method:**
```php
// OLD implementation accumulates from partialResponses list
public function usage(): Usage {
    $attemptsUsage = $this->attempts->usage();
    $current = $this->currentAttempt;
    if ($current !== null && !$current->isFinalized()) {
        // OLD: Loop through partialResponses()->all()
        $partialsUsage = Usage::none();
        $partials = $current->partialResponses();
        // ...
    }
    return $attemptsUsage;
}

// NEW implementation gets usage from final response
public function usage(): Usage {
    $attemptsUsage = $this->attempts->usage();
    $current = $this->currentAttempt;
    if ($current !== null && !$current->isFinalized()) {
        // Get usage from current response (accumulated)
        $currentUsage = $current->response()?->usage() ?? Usage::none();
        return $attemptsUsage->withAccumulated($currentUsage);
    }
    return $attemptsUsage;
}
```

**Mutator updates:**
```php
// OLD:
public function withNewPartialResponse(PartialInferenceResponse $response): self

// NEW:
public function withStreamBuffer(StreamBuffer $buffer): self {
    $newAttempt = $this->currentAttempt->withStreamBuffer($buffer);
    return new self(
        // ... pass through params
        currentAttempt: $newAttempt,
    );
}
```

---

### Phase 6: Instructor Layer Changes

#### 6.1 Update StructuredOutputAttempt
**File:** `packages/instructor/src/Data/StructuredOutputAttempt.php`

**Changes:**

**Field changes:**
```php
// OLD:
private PartialInferenceResponseList $partialResponses;

// NEW:
private ?StreamBuffer $streamBuffer = null;
```

**Method changes:**
```php
// REMOVE:
public function partialResponses(): PartialInferenceResponseList

// ADD:
public function streamBuffer(): ?StreamBuffer {
    return $this->streamBuffer;
}
```

**Constructor & mutators:**
Update all methods that reference `$partialResponses` to use `$streamBuffer` instead.

#### 6.2 Update StructuredOutputExecution
**File:** `packages/instructor/src/Data/StructuredOutputExecution.php`

**Changes:**

**Method updates:**
```php
// REMOVE:
public function partialResponses(): PartialInferenceResponseList {
    return $this->currentAttempt->partialResponses();
}

// ADD:
public function streamBuffer(): ?StreamBuffer {
    return $this->currentAttempt->streamBuffer();
}
```

**Usage calculation:**
```php
// OLD:
public function usage(): Usage {
    $usage = $this->attempts->usage();
    if (!$this->currentAttempt->isFinalized()) {
        $partials = $this->currentAttempt->partialResponses();
        foreach ($partials->all() as $partial) {
            $usage = $usage->withAccumulated($partial->usage());
        }
    }
    return $usage;
}

// NEW:
public function usage(): Usage {
    $usage = $this->attempts->usage();
    if (!$this->currentAttempt->isFinalized()) {
        // Get usage from accumulated inference response
        $current = $this->currentAttempt->inferenceResponse();
        if ($current !== null) {
            $usage = $usage->withAccumulated($current->usage());
        }
    }
    return $usage;
}
```

#### 6.3 Update StructuredOutputAttemptState
**File:** `packages/instructor/src/Data/StructuredOutputAttemptState.php`

**Look for any references to PartialInferenceResponseList and remove/refactor.**

---

### Phase 7: Streaming Iterators & Pipelines

#### 7.1 Update ModularUpdateGenerator
**File:** `packages/instructor/src/ResponseIterators/ModularPipeline/ModularUpdateGenerator.php`

**Method `initializeStream()`:**
```php
// The stream source changes type:
// OLD:
$inferenceStream = $this->inferenceProvider
    ->getInference($execution)
    ->stream()
    ->responses(); // Returns Generator<PartialInferenceResponse>

// NEW:
$inferenceStream = $this->inferenceProvider
    ->getInference($execution)
    ->stream()
    ->responses(); // Now returns Generator<InferenceResponse>

// Pipeline still works the same - just different type flowing through
$aggregateStream = $this->factory->makeStream(
    source: $inferenceStream, // Now InferenceResponse stream
    responseModel: $responseModel,
    mode: $execution->outputMode(),
    accumulatePartials: false, // CHANGE: Don't accumulate by default!
);
```

**No other changes needed** - pipeline is type-agnostic, works with InferenceResponse.

#### 7.2 Update StreamAggregate
**File:** `packages/instructor/src/ResponseIterators/ModularPipeline/Aggregation/StreamAggregate.php`

**Changes:**

**Field update:**
```php
// OLD:
public readonly PartialInferenceResponseList $partials;

// NEW: Remove this field entirely OR change to StreamBuffer
// Option A: Remove (preferred)
// Option B: Replace with StreamBuffer $rawChunks
```

**Constructor:**
```php
// Remove $partials parameter
public function __construct(
    public readonly string $content,
    public readonly mixed $value,
    public readonly Usage $usage,
    public readonly string $finishReason,
    // REMOVE: public readonly PartialInferenceResponseList $partials,
) {}
```

**Factory methods:**
Update `empty()` and other creation methods to not include partials.

**Impact:** Significant memory savings - no longer storing 1000s of partial response objects.

#### 7.3 Update AggregationState
**File:** `packages/instructor/src/ResponseIterators/DecoratedPipeline/ResponseAggregation/AggregationState.php`

**Changes:**

**Field update:**
```php
// OLD:
public readonly PartialInferenceResponseList $partials;

// NEW: Remove entirely
```

**Constructor & methods:**
Remove all references to `$partials` field.

**Method `merge()`:**
```php
// OLD signature:
public function merge(PartialInferenceResponse $partial): self

// NEW signature:
public function merge(InferenceResponse $partial): self {
    // Check if partial
    if (!$partial->isPartial()) {
        // Handle complete response
    }
    // Merge logic unchanged, just different type
}
```

**Method `withPartialAppended()`:**
Remove this method entirely - no longer accumulating partials list.

#### 7.4 Update PartialFrame
**File:** `packages/instructor/src/ResponseIterators/ModularPipeline/Domain/PartialFrame.php`

**Changes:**

**Field update:**
```php
// OLD:
public readonly PartialInferenceResponse $source;

// NEW:
public readonly InferenceResponse $source;
```

**Constructor signature:**
```php
public function __construct(
    public readonly InferenceResponse $source, // CHANGED
    // ... rest unchanged
)
```

**All methods:**
Update any type hints from `PartialInferenceResponse` to `InferenceResponse`.

#### 7.5 Update ExtractDeltaReducer (both versions)
**Files:**
- `packages/instructor/src/ResponseIterators/ModularPipeline/Pipeline/ExtractDeltaReducer.php`
- `packages/instructor/src/ResponseIterators/DecoratedPipeline/DeltaExtraction/ExtractDeltaReducer.php`

**Changes:**

**Method `step()` parameter:**
```php
// OLD:
public function step(mixed $accumulator, mixed $reducible): mixed {
    assert($reducible instanceof PartialInferenceResponse);
    // ...
}

// NEW:
public function step(mixed $accumulator, mixed $reducible): mixed {
    assert($reducible instanceof InferenceResponse);
    assert($reducible->isPartial()); // Ensure it's a partial response
    // ...
}
```

**Accessing delta fields:**
```php
// OLD:
$delta = $reducible->contentDelta;

// NEW: InferenceResponse doesn't have contentDelta
// Extract delta from content (first chunk) or compute diff
$delta = $this->extractDelta($reducible);
```

**Add helper method:**
```php
private function extractDelta(InferenceResponse $response): string {
    // For first chunk, entire content is delta
    // For subsequent chunks, compute diff from previous
    // OR: Use raw chunk from buffer
    $buffer = $response->streamBuffer();
    $reader = $buffer->makeReader();
    // Get latest chunk
    foreach ($reader->readDeltas() as $chunk) {
        return $chunk; // Return latest raw chunk
    }
    return '';
}
```

**Alternative approach:** Extract delta from StreamBuffer directly instead of response content.

#### 7.6 Update EnrichResponseReducer (both versions)
**Files:**
- `packages/instructor/src/ResponseIterators/ModularPipeline/Pipeline/EnrichResponseReducer.php`
- `packages/instructor/src/ResponseIterators/DecoratedPipeline/PartialEmission/EnrichResponseReducer.php`

**Changes:**

**Return type:**
```php
// OLD: Converts PartialFrame -> PartialInferenceResponse
// NEW: Converts PartialFrame -> InferenceResponse (keeps isPartial=true)

// Method signature unchanged, implementation needs to create InferenceResponse instead
```

#### 7.7 Update SyncUpdateGenerator
**File:** `packages/instructor/src/ResponseIterators/Sync/SyncUpdateGenerator.php`

**Changes:**
Update any type hints from `PartialInferenceResponse` to `InferenceResponse`.

---

### Phase 8: Events

#### 8.1 Update PartialInferenceResponseCreated Event
**File:** `packages/polyglot/src/Inference/Events/PartialInferenceResponseCreated.php`

**Option A (Rename):**
Rename class to `InferenceResponseStreamChunk` or similar.

**Option B (Keep name, change type):**
```php
class PartialInferenceResponseCreated
{
    public function __construct(
        // OLD: public readonly PartialInferenceResponse $response
        // NEW:
        public readonly InferenceResponse $response
    ) {}
}
```

**Rationale:** Keep event name for backward compatibility, just change payload type.

---

### Phase 9: Tests

#### 9.1 Update All Test Files
**Search pattern:** `PartialInferenceResponse`

**Files requiring updates (100+ files):**

**For each test file:**
1. Replace `use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;` with `use Cognesy\Polyglot\Inference\Data\InferenceResponse;`
2. Replace `new PartialInferenceResponse(...)` with `new InferenceResponse(..., isPartial: true)`
3. Replace `PartialInferenceResponse $` type hints with `InferenceResponse $`
4. Update assertions checking for `PartialInferenceResponse` type to check `isPartial()` flag
5. Replace `$response->contentDelta` with logic to extract from content or buffer
6. Remove/update tests that verify `PartialInferenceResponseList` behavior

**Key test files:**
- `packages/instructor/tests/Benchmarks/MemoryDiagnostics.php` - Update memory profiling
- `packages/instructor/tests/Feature/ModularPipeline/*` - Update all pipeline tests
- `packages/polyglot/tests/Unit/Inference/*` - Update inference tests
- `packages/instructor/tests/Unit/ModularPipeline/*` - Update reducer tests

#### 9.2 Update FakeInferenceDriver (Test Support)
**Files:**
- `packages/instructor/tests/Support/FakeInferenceDriver.php`
- `packages/addons/tests/Support/FakeInferenceDriver.php`

**Changes:**
```php
// OLD:
public function makeStreamResponsesFor(InferenceRequest $request): iterable {
    foreach ($this->streamResponses as $response) {
        yield $response; // PartialInferenceResponse
    }
}

// NEW:
public function makeStreamResponsesFor(InferenceRequest $request): iterable {
    foreach ($this->streamResponses as $response) {
        yield $response; // InferenceResponse with isPartial=true
    }
}
```

Update test data creation to use `InferenceResponse::partial()` factory.

---

### Phase 10: Cleanup & Removal

#### 10.1 Remove PartialInferenceResponse Class
**File:** `packages/polyglot/src/Inference/Data/PartialInferenceResponse.php`

**Action:** DELETE FILE

#### 10.2 Remove PartialInferenceResponseList Class
**File:** `packages/polyglot/src/Inference/Collections/PartialInferenceResponseList.php`

**Action:** DELETE FILE

#### 10.3 Remove InferenceResponseFactory (if only used for partials)
**File:** `packages/polyglot/src/Inference/Creation/InferenceResponseFactory.php`

**Check usage:**
- If `fromPartialResponses()` method is the only usage
- And no other code depends on it
- **Action:** DELETE FILE

**Otherwise:** Remove the `fromPartialResponses()` method only.

#### 10.4 Clean up imports
**Action:** Run IDE/tool to remove unused imports across all files:
- Remove `use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;`
- Remove `use Cognesy\Polyglot\Inference\Collections\PartialInferenceResponseList;`

---

### Phase 11: Documentation Updates

#### 11.1 Update STREAMING.md
**File:** `packages/instructor/STREAMING.md`

**Changes:**
- Update architecture diagrams to show InferenceResponse flow
- Remove references to PartialInferenceResponse
- Document isPartial flag usage
- Document StreamBuffer approach
- Update memory efficiency section with new metrics

#### 11.2 Update Polyglot Docs
**Files:**
- `packages/polyglot/docs/streaming/overview.md`
- `packages/polyglot/docs/internals/providers.md`
- `packages/polyglot/docs/internals/adapters.md`

**Changes:**
- Update streaming examples to use InferenceResponse
- Document adapter contract changes
- Update type annotations in examples

#### 11.3 Update Cookbook Examples
**Files in:** `docs-build/cookbook/`, `packages/hub/examples/`

**Changes:**
- Find streaming examples
- Update to use InferenceResponse with isPartial checks
- Remove PartialInferenceResponse references

---

## Verification Checklist

After completing all phases:

### Compilation Checks
- [ ] Run `composer install` across all packages
- [ ] Run static analysis: `vendor/bin/phpstan analyze`
- [ ] Check for any remaining PartialInferenceResponse references: `grep -r "PartialInferenceResponse" packages/`
- [ ] Check for PartialInferenceResponseList references: `grep -r "PartialInferenceResponseList" packages/`

### Test Execution
- [ ] Run polyglot tests: `cd packages/polyglot && vendor/bin/pest`
- [ ] Run instructor tests: `cd packages/instructor && vendor/bin/pest`
- [ ] Run integration tests
- [ ] Run streaming-specific tests
- [ ] Run memory benchmarks to verify memory reduction

### Functional Verification
- [ ] Test streaming with OpenAI driver
- [ ] Test streaming with Anthropic driver
- [ ] Test streaming with Gemini driver
- [ ] Test tool calling in streaming mode
- [ ] Test JSON mode streaming
- [ ] Test structured output streaming
- [ ] Verify events are still dispatched correctly
- [ ] Verify usage tracking still works

### Memory Verification
- [ ] Run memory profiling benchmarks
- [ ] Verify ~97% memory reduction for streaming scenarios
- [ ] Confirm StreamBuffer overhead is minimal
- [ ] Test with 10K+ chunk streams

---

## Migration Guide for External Users

### For Library Users

**Before:**
```php
foreach ($stream->responses() as $partial) {
    // $partial is PartialInferenceResponse
    echo $partial->contentDelta;
}
```

**After:**
```php
foreach ($stream->responses() as $response) {
    // $response is InferenceResponse with isPartial() = true
    if ($response->isPartial()) {
        echo $response->content(); // Accumulated content
    }
}
```

### For Driver Implementers

**Before:**
```php
public function fromStreamResponse(string $eventBody): ?PartialInferenceResponse {
    return new PartialInferenceResponse(
        contentDelta: $delta,
        // ...
    );
}
```

**After:**
```php
public function fromStreamResponse(string $eventBody): ?InferenceResponse {
    $response = new InferenceResponse(
        content: $delta, // Note: streaming accumulation happens in InferenceStream
        isPartial: true,
        // ...
    );
    $response->pushRawChunk($eventBody); // Store raw chunk
    return $response;
}
```

---

## Risk Assessment

### High Risk Areas
1. **Driver adapters** - Each provider has different SSE format
2. **Content accumulation** - Logic moving from PartialInferenceResponse to InferenceStream
3. **Usage calculation** - Scattered across multiple classes

### Mitigation Strategies
1. **Comprehensive testing** - Test each driver independently
2. **Incremental rollout** - Can be done per-driver if needed
3. **Feature flag** - Could add temporary flag to use old/new path
4. **Benchmark validation** - Verify memory improvements are real

---

## Timeline Estimate

**Total:** ~20-30 hours of focused work

- **Phase 1-2** (Core + Interfaces): 2-3 hours
- **Phase 3** (Driver Adapters): 4-6 hours (15+ files)
- **Phase 4** (Base Classes): 2-3 hours
- **Phase 5-6** (Execution Classes): 3-4 hours
- **Phase 7** (Pipelines): 5-7 hours (complex logic)
- **Phase 8** (Events): 1 hour
- **Phase 9** (Tests): 6-8 hours (100+ files)
- **Phase 10** (Cleanup): 1 hour
- **Phase 11** (Docs): 2-3 hours

---

## Success Criteria

1. ✅ All tests passing
2. ✅ No compilation errors
3. ✅ No references to PartialInferenceResponse or PartialInferenceResponseList
4. ✅ Memory usage reduced by >90% for streaming scenarios
5. ✅ All drivers working with streaming
6. ✅ Events still firing correctly
7. ✅ Documentation updated
8. ✅ No behavioral regressions

---

## Notes

- This is a **breaking change** for external driver implementers
- Internal refactor, but API surface changes
- Consider major version bump (v2.0)
- Announce deprecation if gradual migration desired
- StreamBuffer makes replay/debugging scenarios much easier
- Opens door for future optimizations (compression, persistence, etc.)
