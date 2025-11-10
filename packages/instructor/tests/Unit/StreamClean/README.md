# Clean Architecture Stream Tests

This directory contains unit tests for the Clean streaming architecture components.

## Test Coverage

### Reducers (Pipeline Components)

1. **ExtractDeltaReducerTest.php** - Tests delta extraction and frame creation
   - Extracts contentDelta in different output modes (JsonSchema, MdJson, Tools)
   - Falls back to contentDelta when toolArgs empty
   - Forwards frames even with empty delta
   - Preserves source response metadata
   - Increments frame index correctly
   - Resets state on init()

2. **DeserializeAndDeduplicateReducerTest.php** - Tests object deserialization and deduplication
   - Deserializes valid JSON and marks for emission
   - Skips frames without content
   - Handles deserialization, validation, and transformation failures
   - Deduplicates identical objects (same hash)
   - Emits when object changes (different hash)
   - Resets deduplication state on init()
   - Does not update dedup state on errors
   - Preserves frame metadata through transformation

3. **AggregateStreamReducerTest.php** - Tests stream aggregation
   - Initializes with empty aggregate
   - Accumulates frame content progressively
   - Updates latest value when frame has object
   - Accumulates multiple frames
   - Accumulates usage across frames
   - Captures finish reason
   - Accumulates partials when enabled/disabled
   - Only adds emittable frames to partials
   - Resets aggregate on init()

4. **EventTapTest.php** - Tests event dispatching
   - Dispatches ChunkReceived for every frame
   - Dispatches PartialResponseGenerated for ObjectReady emission
   - Dispatches tool call events (Started, Updated, Completed)
   - Dispatches SequenceUpdated for sequenceable objects
   - Dispatches StreamedResponseReceived on complete
   - Does not track tool calls when expectedToolName is empty
   - Forwards frames unchanged to inner reducer
   - Resets tracker state on init()

### Services

5. **CleanStreamFactoryTest.php** - Tests stream factory and pipeline assembly
   - Creates observable stream from iterable source
   - Stream processes all source items
   - Accumulates/doesn't accumulate partials based on flag
   - Handles different output modes (JsonSchema, Tools, MdJson)
   - Accumulates usage across chunks
   - Handles finish reason
   - Uses provided deserializer, validator, transformer, event handler

6. **CleanStreamingUpdateGeneratorTest.php** - Tests execution update generator
   - hasNext() returns true when not started
   - hasNext() returns true when stream has more chunks
   - hasNext() returns false when stream exhausted
   - nextChunk() initializes stream on first call
   - nextChunk() processes chunks sequentially
   - nextChunk() updates execution with current attempt
   - nextChunk() accumulates partials in execution
   - nextChunk() marks stream as exhausted when done
   - Handles empty chunk streams
   - Preserves existing errors in execution
   - Works with Tools output mode

## Testing Patterns

### Collector Reducers

Tests use collector reducers to capture processed frames:

```php
function makeFrameCollector(): Reducer {
    return new class implements Reducer {
        public array $collected = [];

        public function step(mixed $accumulator, mixed $reducible): mixed {
            $this->collected[] = $reducible;
            return $reducible;
        }
    };
}
```

### Mock Services

Tests create simple mock implementations for dependencies:

```php
function makeSuccessDeserializer(): CanDeserializeResponse {
    return new class implements CanDeserializeResponse {
        public function deserialize(string $json, ResponseModel $responseModel): Result {
            return Result::success((object) json_decode($json, true));
        }
    };
}
```

### Test Focus

- **Services and Reducers Only** - Data classes are NOT tested
- **Behavior, Not Implementation** - Tests verify observable behavior
- **Single Responsibility** - Each test verifies one specific behavior
- **Clear Assertions** - Expectations are explicit and well-documented

## Not Tested (Data Classes)

The following are pure data carriers and are NOT tested:

- PartialFrame
- DeduplicationState
- SequenceTracker
- ToolCallTracker
- StreamAggregate
- ContentHash
- FrameMetadata
- ContentBuffer implementations (JsonBuffer, TextBuffer)
- Value objects
- Enums

## Running Tests

```bash
# Run all Clean stream tests
vendor/bin/pest tests/Unit/StreamClean/

# Run specific test file
vendor/bin/pest tests/Unit/StreamClean/ExtractDeltaReducerTest.php

# Run with coverage
vendor/bin/pest --coverage tests/Unit/StreamClean/
```

## Test Conventions

1. Use descriptive test names that explain what is being tested
2. Arrange-Act-Assert pattern
3. One assertion concept per test
4. Use factories for complex object creation
5. Mock only external dependencies, not domain objects
6. Test edge cases (empty, null, errors)
7. Test state transitions (init, multiple calls, reset)
