# P0: Streaming Pipeline Duplication

## Problem Statement

Three separate streaming pipeline implementations exist with significant functional overlap:

```
ResponseIterators/
├── ModularPipeline/     (~20 files)  - Current default, transducer-based
├── DecoratedPipeline/   (~25 files)  - Alternative, event-dispatching
├── GeneratorBased/      (~15 files)  - Legacy, generator-based
```

All three accomplish the same core task: process streaming LLM responses into typed objects.

## Evidence of Duplication

### 1. Near-Identical Update Generators

```php
// ModularUpdateGenerator.php
public function nextChunk(StructuredOutputExecution $execution): StructuredOutputExecution {
    $state = $execution->attemptState();
    if ($state === null || !$state->isStreamInitialized()) {
        return $this->initializeStream($execution);
    }
    return $this->processNextChunk($execution, $state);
}

// StreamingUpdatesGenerator.php (Legacy)
public function nextChunk(StructuredOutputExecution $execution): StructuredOutputExecution {
    $state = $execution->attemptState();
    if ($state === null || !$state->isStreamInitialized()) {
        return $this->initializeStream($execution);
    }
    return $this->processNextChunk($execution, $state);
}
```

Both have nearly identical `hasNext()`, `nextChunk()`, `initializeStream()`, and `processNextChunk()` methods.

### 2. Duplicated Pipeline Stages

| Stage | ModularPipeline | DecoratedPipeline | GeneratorBased |
|-------|-----------------|-------------------|----------------|
| Extract Delta | `Pipeline/ExtractDelta.php` | `DeltaExtraction/ExtractDelta.php` | (inline) |
| Deserialize | `Pipeline/DeserializeAndDeduplicate.php` | `PartialCreation/DeserializeAndDeduplicate.php` | `PartialGen/AssemblePartialObject.php` |
| Sequence | `Pipeline/UpdateSequence.php` | `Sequence/UpdateSequence.php` | `SequenceGen/SequenceableEmitter.php` |
| Enrich | `Pipeline/EnrichResponse.php` | `PartialEmission/EnrichResponse.php` | (inline) |
| Aggregate | `Aggregation/AggregateStream.php` | `ResponseAggregation/AggregateResponse.php` | (inline) |

### 3. Factory Method Complexity

`ResponseIteratorFactory.makeStreamingIterator()` must branch on configuration:

```php
private function makeStreamingIterator(StructuredOutputExecution $execution): CanStreamStructuredOutputUpdates {
    $pipeline = $execution->config()->responseIterator;
    return match($pipeline) {
        'modular' => $this->makeModularStreamingIterator(),
        'partials' => $this->makePartialStreamingIterator(),
        'legacy' => $this->makeLegacyStreamingIterator($execution),
        default => $this->makeModularStreamingIterator(),
    };
}
```

Each branch creates completely different object graphs.

## Impact

- **~60 files** of streaming-related code (could be reduced to ~20)
- **3x maintenance burden** for any streaming-related changes
- **Cognitive load** for contributors trying to understand the system
- **Testing complexity** - each pipeline needs its own test coverage

## Root Cause

Historical evolution: Pipeline implementations were added incrementally as improvements, but old versions were never removed. The `responseIterator` config option preserves backward compatibility at the cost of complexity.

## Proposed Solution

### Phase 1: Deprecate Alternatives (Low Effort)

1. Mark `DecoratedPipeline` and `GeneratorBased` as `@deprecated`
2. Add deprecation warnings when non-modular pipelines are selected
3. Update documentation to recommend only `modular` pipeline
4. Keep config option for backward compatibility during transition

### Phase 2: Extract Common Base (Medium Effort)

1. Extract shared logic into `AbstractUpdateGenerator`:
   - `hasNext()` implementation
   - `nextChunk()` orchestration
   - Stream initialization pattern
   - State management pattern

2. ModularUpdateGenerator extends AbstractUpdateGenerator

### Phase 3: Remove Deprecated Code (Low Effort)

After deprecation period:
1. Remove `DecoratedPipeline/` directory (~25 files)
2. Remove `GeneratorBased/` directory (~15 files)
3. Remove pipeline selection logic from factory
4. Remove `responseIterator` config option

## File Impact

### Files to DELETE (Phase 3)

```
ResponseIterators/DecoratedPipeline/ (entire directory)
├── DeltaExtraction/
├── JsonMode/
├── PartialCreation/
├── PartialEmission/
├── ResponseAggregation/
├── Sequence/
├── ToolCallMode/
├── EventDispatchingStream.php
├── PartialStreamFactory.php
└── PartialUpdateGenerator.php

ResponseIterators/GeneratorBased/ (entire directory)
├── Contracts/
├── PartialGen/
├── SequenceGen/
└── StreamingUpdatesGenerator.php
```

### Files to MODIFY

- `ResponseIteratorFactory.php` - Remove `makePartialStreamingIterator()` and `makeLegacyStreamingIterator()`
- `Config/StructuredOutputConfig.php` - Remove `responseIterator` property
- Related test files

## Migration Path

```php
// Before (multiple options)
$config = new StructuredOutputConfig(responseIterator: 'legacy');

// After (single implementation)
// responseIterator option no longer exists
// ModularPipeline is always used
```

## Risk Assessment

- **Low risk** - ModularPipeline is already the default and well-tested
- **Breaking change** - Users explicitly selecting 'legacy' or 'partials' will need to adapt
- **Mitigation** - Deprecation warnings give time for migration

## Estimated Effort

- Phase 1: 2 hours
- Phase 2: 4 hours
- Phase 3: 2 hours
- **Total: ~8 hours** (including tests)

## Success Metrics

- Reduce `ResponseIterators/` from ~60 files to ~20 files
- Remove `responseIterator` configuration complexity
- Single streaming path to understand and maintain
