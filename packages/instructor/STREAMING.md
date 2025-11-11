# Streaming Architecture - Clean response iterator

## Overview

The Clean response iterator provides low-latency, memory-efficient streaming extraction of structured data from LLM responses. Built on transducer-based pipelines, it processes Server-Sent Events (SSE) chunks as they arrive, progressively deserializing partial objects without buffering the entire response.

### Purpose

Instructor-PHP extracts structured data from unstructured LLM outputs. While batch processing works for complete responses, streaming scenarios demand:

- **Progressive updates**: Display partial results as they arrive
- **Low memory footprint**: Process gigabyte-scale responses without accumulation
- **Event-driven UX**: Enable real-time UI updates for better user experience
- **Sequence extraction**: Handle lists/arrays that stream item-by-item

The Clean response iterator addresses these requirements through a functional pipeline architecture.

## Core Concepts

### Transducers

Transducers are composable transformation pipelines that separate:
- **What to transform** (the transformation logic)
- **How to reduce** (accumulation strategy)

Each pipeline stage is a `Transducer` that wraps a `Reducer`:

```php
interface Transducer {
    public function __invoke(Reducer $reducer): Reducer;
}

interface Reducer {
    public function init(): mixed;
    public function step(mixed $accumulator, mixed $reducible): mixed;
    public function complete(mixed $accumulator): mixed;
}
```

Benefits:
- **Composable**: Chain stages without coupling
- **Memory-efficient**: Process one item at a time (no intermediate collections)
- **Reusable**: Same transformation works with different reducers

### Content Buffers

Format-specific accumulators that handle incremental assembly:

```php
interface ContentBuffer {
    public function assemble(string $delta): self;
    public function raw(): string;
    public function normalized(): string;
    public function isEmpty(): bool;
}
```

**Key insight**: Different content formats require different assembly strategies:
- **JSON**: Use partial JSON parser to handle incomplete syntax
- **XML**: Track tag depth and balance
- **YAML**: Handle indentation-sensitive increments
- **Tool calls**: Accumulate JSON arguments with specific normalization

Buffers are **immutable** - each `assemble()` returns a new instance.

### Frames

The pipeline's internal data structure representing a point-in-time snapshot:

```php
final readonly class PartialFrame {
    public function __construct(
        public PartialInferenceResponse $source,  // Original SSE chunk
        public ContentBuffer $buffer,              // Accumulated content
        public Result $object,                     // Deserialized object (or error)
        public Emission $emission,                 // Should we emit this frame?
        public FrameMetadata $metadata,            // Index, timing, etc.
    ) {}
}
```

Frames flow through the pipeline, each stage enriching or transforming them.

## Architecture

### Pipeline Overview

```
PartialInferenceResponse (SSE chunk)
    ↓
┌─────────────────────────────────────────────────────┐
│ 1. ExtractDelta                                     │
│    - Extract content from response                  │
│    - Select buffer type (JSON/Tools/Text)           │
│    - Accumulate delta into buffer                   │
│    - Create PartialFrame                            │
└─────────────────────────────────────────────────────┘
    ↓ PartialFrame (with accumulated buffer)
┌─────────────────────────────────────────────────────┐
│ 2. DeserializeAndDeduplicate                        │
│    - Use buffer.normalized() as source              │
│    - Validate → Deserialize → Transform             │
│    - Deduplicate by content hash                    │
│    - Mark emission if object changed                │
└─────────────────────────────────────────────────────┘
    ↓ PartialFrame (with deserialized object)
┌─────────────────────────────────────────────────────┐
│ 3. UpdateSequence                                   │
│    - Track sequence item updates                    │
│    - Build sequence update list                     │
│    - Detect item additions/changes                  │
└─────────────────────────────────────────────────────┘
    ↓ PartialFrame (with sequence tracking)
┌─────────────────────────────────────────────────────┐
│ 4. EventTapTransducer                               │
│    - Dispatch ChunkReceived (every frame)           │
│    - Dispatch PartialResponseGenerated (on emit)    │
│    - Track tool calls (if Tools mode)               │
│    - Dispatch StreamedResponseReceived (on complete)│
└─────────────────────────────────────────────────────┘
    ↓ PartialFrame (events dispatched)
┌─────────────────────────────────────────────────────┐
│ 5. EnrichResponse                                   │
│    - Convert PartialFrame → PartialInferenceResponse│
│    - Preserve object, usage, finish reason          │
└─────────────────────────────────────────────────────┘
    ↓ PartialInferenceResponse (enriched)
┌─────────────────────────────────────────────────────┐
│ 6. AggregateStream                                  │
│    - Accumulate into StreamAggregate                │
│    - Optionally collect partial history             │
│    - Emit observable aggregate                      │
└─────────────────────────────────────────────────────┘
    ↓ StreamAggregate (for observation)
```

### Design Principles

1. **Single Source of Truth**: Buffer accumulates all content - no split between driver accumulation and buffer accumulation
2. **Immutability**: All domain objects are immutable value objects
3. **Pure Transformations**: Stages transform data without side effects (except EventTap)
4. **Clear Separation**: Extract → Assemble → Deserialize → Track → Observe
5. **Format Agnostic**: Buffer abstraction decouples format from pipeline logic

## Components

### 1. Pipeline Stages

#### ExtractDelta

**Location**: `packages/instructor/src/ResponseIterators/ModularPipeline/Pipeline/ExtractDeltaReducer.php`

**Responsibility**: Entry point - extract content delta from SSE chunk and accumulate into buffer.

**Key Logic**:
```php
public function step(mixed $accumulator, mixed $reducible): mixed {
    // Extract delta based on OutputMode
    $delta = match ($this->mode) {
        OutputMode::Tools => $reducible->toolArgs ?: $reducible->contentDelta,
        default => $reducible->contentDelta,
    };

    // Always accumulate delta into persistent buffer
    if ($delta !== '') {
        $this->accumulatedBuffer = $this->accumulatedBuffer->assemble($delta);
    }

    // Create frame with accumulated buffer
    $frame = $this->createFrame($reducible, $this->frameIndex++, $this->accumulatedBuffer);
    return $this->inner->step($accumulator, $frame);
}
```

**Buffer Selection**: Chooses buffer type based on `OutputMode`:
- `Tools` → `ToolsBuffer` (JSON tool arguments)
- `JsonSchema`, `Json`, `MdJson` → `JsonBuffer` (JSON content)
- Future: YAML, XML, etc.

**Why Always Accumulate**: Previously split between driver-accumulated content (JSON modes) and buffer-accumulated (Tools mode). Unified approach makes buffer the single source of truth.

#### DeserializeAndDeduplicate

**Location**: `packages/instructor/src/ResponseIterators/ModularPipeline/Pipeline/DeserializeAndDeduplicateReducer.php`

**Responsibility**: Convert buffer content to validated, transformed objects with deduplication.

**Process**:
1. Check if driver provided value directly (bypass deserialization)
2. Use `buffer.normalized()` as single source
3. Validate → Deserialize → Transform (with Result monad for errors)
4. Hash object content for deduplication
5. Mark `Emission::ObjectReady` if hash changed

**Deduplication Strategy**:
```php
private DeduplicationState $state;

public function step(mixed $accumulator, mixed $reducible): mixed {
    $normalizedText = $reducible->buffer->normalized();
    $result = $this->createObject($normalizedText);

    if ($result->isFailure()) {
        return $this->inner->step($accumulator, $reducible->withObject($result));
    }

    $object = $result->unwrap();

    // Only emit if content hash changed
    if (!$this->state->shouldEmit($object)) {
        $this->state = $this->state->withHash(ContentHash::of($object));
        return $this->inner->step($accumulator, $reducible->withObject($result));
    }

    // Object changed - mark for emission
    $this->state = $this->state->withHash(ContentHash::of($object));
    $frame = $reducible
        ->withObject($result)
        ->withEmission(Emission::ObjectReady);
    return $this->inner->step($accumulator, $frame);
}
```

**Why Deduplication**: LLMs may emit identical partial JSON across multiple chunks. Without deduplication, observers receive redundant updates.

#### UpdateSequence

**Location**: `packages/instructor/src/ResponseIterators/ModularPipeline/Pipeline/UpdateSequenceReducer.php`

**Responsibility**: Track sequence (array/collection) item updates for progressive list rendering.

**Behavior**:
- Only activates when object implements `Sequenceable` interface
- Tracks which items in sequence changed
- Builds `SequenceUpdateList` for consumers
- Pure state tracking - no events (handled by EventTap)

**Use Case**: Streaming a list of search results - UI updates as each new item arrives.

#### EventTapTransducer

**Location**: `packages/instructor/src/ResponseIterators/ModularPipeline/Events/EventTapTransducer.php`

**Responsibility**: Side-effect stage - dispatch domain events for observers.

**Events Dispatched**:

1. **ChunkReceived** (every frame)
   - Raw SSE chunk arrival notification
   - Useful for progress indicators

2. **PartialResponseGenerated** (on `Emission::ObjectReady`)
   - Partial object ready for consumption
   - Main event for UI updates

3. **StreamedToolCallStarted/Updated/Completed** (Tools mode)
   - Granular tool call lifecycle events
   - Track tool name and argument accumulation

4. **StreamedResponseReceived** (on complete)
   - Final aggregate with finish reason and usage
   - Signals end of stream

**Tool Call Tracking**:
```php
private ToolCallTracker $tracker;

public function step(mixed $accumulator, mixed $reducible): mixed {
    // Dispatch ChunkReceived for every frame
    $this->events->dispatch(new ChunkReceived(...));

    // Dispatch PartialResponseGenerated if object ready
    if ($reducible->emission === Emission::ObjectReady) {
        $this->events->dispatch(new PartialResponseGenerated(...));
    }

    // Track tool calls if in Tools mode
    if ($this->expectedToolName !== '') {
        $this->tracker = $this->tracker->processFrame($reducible, $this->events);
    }

    return $this->inner->step($accumulator, $reducible);
}
```

#### EnrichResponse

**Location**: `packages/instructor/src/ResponseIterators/ModularPipeline/Pipeline/EnrichResponseReducer.php`

**Responsibility**: Convert internal `PartialFrame` back to `PartialInferenceResponse` for consumers.

**Why Needed**: Pipeline uses `PartialFrame` internally for rich context, but consumers expect standard `PartialInferenceResponse` format.

#### AggregateStream

**Location**: `packages/instructor/src/ResponseIterators/ModularPipeline/Aggregation/AggregateStream.php`

**Responsibility**: Terminal stage - accumulate stream into observable `StreamAggregate`.

**Aggregate Contents**:
```php
final readonly class StreamAggregate {
    public string $content;              // Latest accumulated content
    public mixed $value;                 // Latest deserialized object
    public Usage $usage;                 // Cumulative token usage
    public string $finishReason;         // Stop reason (if finished)
    public PartialInferenceResponseList $partials;  // History (optional)
}
```

**Accumulation Strategy**:
- `$accumulatePartials = true`: Keep history of all partials (memory cost)
- `$accumulatePartials = false`: Only latest value (memory efficient)

**Observable Pattern**: Each chunk emits the current aggregate, enabling reactive UI:
```php
foreach ($stream as $aggregate) {
    echo "Latest: " . json_encode($aggregate->value) . "\n";
    echo "Tokens: " . $aggregate->usage->total() . "\n";
}
```

### 2. Domain Objects

#### PartialFrame

**Location**: `packages/instructor/src/ResponseIterators/ModularPipeline/Domain/PartialFrame.php`

Internal pipeline data structure. Immutable value object with `with*()` methods for transformations:

```php
$frame = $frame
    ->withBuffer($newBuffer)
    ->withObject($result)
    ->withEmission(Emission::ObjectReady);
```

#### ContentHash

**Location**: `packages/instructor/src/ResponseIterators/ModularPipeline/Domain/ContentHash.php`

Value object for deduplication. Hashes object content using `serialize()`:

```php
final readonly class ContentHash {
    private function __construct(private string $hash) {}

    public static function of(mixed $value): self {
        return new self(hash('xxh3', serialize($value)));
    }

    public function equals(self $other): bool {
        return $this->hash === $other->hash;
    }
}
```

**Why xxh3**: Fast non-cryptographic hash suitable for deduplication.

#### DeduplicationState

**Location**: `packages/instructor/src/ResponseIterators/ModularPipeline/Domain/DeduplicationState.php`

Tracks last emitted content hash:

```php
final readonly class DeduplicationState {
    private function __construct(private ?ContentHash $lastHash) {}

    public function shouldEmit(mixed $object): bool {
        $hash = ContentHash::of($object);
        return $this->lastHash === null || !$this->lastHash->equals($hash);
    }

    public function withHash(ContentHash $hash): self {
        return new self($hash);
    }
}
```

#### SequenceTracker / SequenceUpdateList

**Location**: `packages/instructor/src/ResponseIterators/ModularPipeline/Domain/`

Track changes in sequences (arrays/collections):

```php
final readonly class SequenceTracker {
    public function processObject(mixed $object): array {
        // Returns [$updatedTracker, $updatesList]
        // Compares current items with previous snapshot
        // Detects additions and content changes
    }
}
```

Used when object implements `Sequenceable` interface.

#### ToolCallTracker

**Location**: `packages/instructor/src/ResponseIterators/ModularPipeline/Domain/ToolCallTracker.php`

State machine for tool call lifecycle:

```php
final readonly class ToolCallTracker {
    public function processFrame(
        PartialFrame $frame,
        CanHandleEvents $events
    ): self {
        // Detect tool call start (toolName arrives)
        // Track argument accumulation
        // Detect completion (finishReason present)
        // Dispatch appropriate events
    }
}
```

States: `NotStarted` → `InProgress` → `Completed`

### 3. Content Buffers

#### ContentBuffer Interface

**Location**: `packages/instructor/src/ResponseIterators/ModularPipeline/ContentBuffer/ContentBuffer.php`

The abstraction that enables format flexibility:

```php
interface ContentBuffer {
    public function assemble(string $delta): self;  // Accumulate chunk
    public function raw(): string;                   // Original text
    public function normalized(): string;            // Parsed/cleaned
    public function isEmpty(): bool;
}
```

#### JsonBuffer

**Location**: `packages/instructor/src/ResponseIterators/ModularPipeline/ContentBuffer/JsonBuffer.php`

Handles JSON content with partial parsing:

```php
public function assemble(string $delta): self {
    if (trim($delta) === '') {
        return $this;
    }
    $raw = $this->raw . $delta;

    // Only parse if structural JSON present (avoid scalar-only content)
    $hasBraces = str_contains($raw, '{') || str_contains($raw, '[');
    $normalized = match ($hasBraces) {
        true => Json::fromPartial($raw)->toString(),
        false => $this->normalized,
    };

    return new self($raw, $normalized);
}
```

**Optimization**: Skip partial JSON parsing for scalar-only deltas (e.g., numeric chunks) since parser expects objects/arrays.

**Partial JSON Parsing**: `Json::fromPartial()` completes incomplete JSON:
- `{"key": "val` → `{"key": "val"}`
- `[1, 2, 3` → `[1, 2, 3]`
- Enables deserialization of incomplete structures

#### ToolsBuffer

**Location**: `packages/instructor/src/ResponseIterators/ModularPipeline/ContentBuffer/ToolsBuffer.php`

Specialized for tool call arguments (always JSON):

```php
public function assemble(string $delta): self {
    if (trim($delta) === '') {
        return $this;
    }

    $raw = $this->raw . $delta;
    $hasBraces = str_contains($raw, '{') || str_contains($raw, '[');
    $normalized = match ($hasBraces) {
        true => Json::fromPartial($raw)->toString(),
        false => $this->normalized,
    };

    return new self($raw, $normalized);
}
```

**Difference from JsonBuffer**: Tool arguments come as `toolArgs` field (delta-only), never cumulative. Buffer must accumulate across all chunks.

#### TextBuffer

**Location**: `packages/instructor/src/ResponseIterators/ModularPipeline/ContentBuffer/TextBuffer.php`

Simple text accumulation with trimming:

```php
public function assemble(string $delta): self {
    return new self($this->raw . $delta);
}

public function normalized(): string {
    return trim($this->raw);
}
```

Used for text output modes.

### 4. Enums

#### Emission

**Location**: `packages/instructor/src/ResponseIterators/ModularPipeline/Enums/Emission.php`

Signals whether frame should be emitted to observers:

```php
enum Emission {
    case None;           // Nothing to emit (no changes)
    case ObjectReady;    // Deserialized object ready (hash changed)
    case DriverValue;    // Driver provided value directly
}
```

Controls `PartialResponseGenerated` event dispatch.

### 5. Factory

#### CleanStreamFactory

**Location**: `packages/instructor/src/ResponseIterators/ModularPipeline/CleanStreamFactory.php`

Composes the pipeline:

```php
public function makeObservableStream(
    iterable $source,
    ResponseModel $responseModel,
    OutputMode $mode,
    bool $accumulatePartials = true,
): IteratorAggregate {
    $stages = [
        new ExtractDelta($mode),
        new DeserializeAndDeduplicate(
            deserializer: $this->deserializer,
            validator: $this->validator,
            transformer: $this->transformer,
            responseModel: $responseModel,
        ),
        new UpdateSequence(),
        new EventTapTransducer(
            events: $this->events,
            expectedToolName: $mode === OutputMode::Tools ? $responseModel->toolName() : '',
        ),
        new EnrichResponse($mode),
        new AggregateStream($accumulatePartials),
    ];

    $transformation = Transformation::define(...$stages);
    return TransformationStream::from($source)->using($transformation);
}
```

**Transducer Composition**: Stages wrap each other like onion layers - first stage is innermost reducer.

## Processing Flow

### Example: Streaming JSON Object

**Input Stream** (SSE chunks):
```
{"name": "Al
ice", "age": 3
0, "city": "NYC"}
```

**Frame-by-Frame Processing**:

#### Chunk 1: `{"name": "Al`

1. **ExtractDelta**:
   - Extract: `{"name": "Al`
   - Buffer accumulates: `{"name": "Al`
   - Create frame with buffer

2. **DeserializeAndDeduplicate**:
   - Normalize: `{"name": "Al"}` (partial parser completes)
   - Deserialize: `{name: "Al", age: null, city: null}`
   - Hash: `xxh3(...)`
   - No previous hash → Mark `Emission::ObjectReady`

3. **EventTap**:
   - Dispatch `ChunkReceived`
   - Dispatch `PartialResponseGenerated` (emission ready)

4. **AggregateStream**:
   - Emit `StreamAggregate{value: {name: "Al", ...}}`

#### Chunk 2: `ice", "age": 3`

1. **ExtractDelta**:
   - Extract: `ice", "age": 3`
   - Buffer accumulates: `{"name": "Alice", "age": 3`

2. **DeserializeAndDeduplicate**:
   - Normalize: `{"name": "Alice", "age": 3}`
   - Deserialize: `{name: "Alice", age: 3, city: null}`
   - Hash: `xxh3(...)` (different!)
   - Previous hash exists but differs → Mark `Emission::ObjectReady`

3. **EventTap**:
   - Dispatch `PartialResponseGenerated` (updated object)

4. **AggregateStream**:
   - Emit `StreamAggregate{value: {name: "Alice", age: 3, ...}}`

#### Chunk 3: `0, "city": "NYC"}`

1. **ExtractDelta**:
   - Buffer: `{"name": "Alice", "age": 30, "city": "NYC"}`

2. **DeserializeAndDeduplicate**:
   - Deserialize: `{name: "Alice", age: 30, city: "NYC"}`
   - Hash changed → Emit

3. **AggregateStream**:
   - Emit final complete object

### Memory Profile

**Per-Frame Memory**:
- `PartialFrame`: ~1KB (references, not copies)
- `ContentBuffer`: Size of accumulated content (string)
- `StreamAggregate`: ~1-2KB + object size

**No Accumulation**: Previous frames are garbage collected immediately (unless `$accumulatePartials = true`).

**Scalability**: Can process gigabyte responses with ~constant memory (few KB overhead per object).

## Integration with Codebase

### Entry Point: CleanStreamingUpdateGenerator

**Location**: `packages/instructor/src/ResponseIterators/ModularPipeline/CleanStreamingUpdateGenerator.php`

Used by `Instructor` class for streaming mode:

```php
$generator = new CleanStreamingUpdateGenerator(
    streamFactory: $this->cleanStreamFactory,
    responseModel: $responseModel,
    execution: $execution,
);

while ($generator->hasNext()) {
    $execution = $generator->nextChunk();
    // $execution now has updated partial
}
```

### Integration with Polyglot

**Driver Layer**: Polyglot drivers (OpenAI, Anthropic, etc.) provide `PartialInferenceResponse` stream:

```php
interface InferenceDriver {
    public function stream(InferenceRequest $request): iterable<PartialInferenceResponse>;
}
```

**OutputMode Mapping**:
- `OutputMode::Tools` → Tool calls (function calling)
- `OutputMode::JsonSchema`, `Json`, `MdJson` → Structured output
- `OutputMode::Text` → Plain text

Clean response iterator adapts to each mode via buffer selection.

### Integration with Deserialization

**Contracts**:
```php
interface CanDeserializeResponse {
    public function deserialize(string $json, ResponseModel $model): Result;
}

interface CanValidatePartialResponse {
    public function validatePartialResponse(string $json, ResponseModel $model): Result;
}

interface CanTransformResponse {
    public function transform(object $object, ResponseModel $model): Result;
}
```

Injected into `DeserializeAndDeduplicate` stage via factory.

### Integration with Events

**Event System**: Uses `CanHandleEvents` from `cognesy/events` package:

```php
interface CanHandleEvents {
    public function dispatch(object $event): void;
}
```

Consumers register listeners:
```php
$events->wiretap(PartialResponseGenerated::class, function($event) {
    echo "Partial: " . json_encode($event->response->value()) . "\n";
});
```

## Extension Points

### Adding New Content Formats

To support new formats (YAML, XML, INI, etc.):

#### 1. Implement ContentBuffer

```php
final readonly class YamlBuffer implements ContentBuffer {
    private function __construct(
        private string $raw,
        private string $normalized,
    ) {}

    public static function empty(): self {
        return new self('', '');
    }

    public function assemble(string $delta): self {
        $raw = $this->raw . $delta;

        // YAML-specific normalization
        // - Handle indentation
        // - Complete incomplete structures
        // - Preserve multiline strings
        $normalized = $this->normalizeYaml($raw);

        return new self($raw, $normalized);
    }

    private function normalizeYaml(string $raw): string {
        // Custom logic:
        // - Parse YAML incrementally
        // - Complete partial structures
        // - Return valid YAML or empty string
        return Yaml::parsePartial($raw);
    }

    // ... other methods
}
```

#### 2. Update ExtractDelta Buffer Selection

```php
private function createEmptyBuffer(): ContentBuffer {
    return match ($this->mode) {
        OutputMode::Tools => ToolsBuffer::empty(),
        OutputMode::Yaml => YamlBuffer::empty(),       // New
        OutputMode::Xml => XmlBuffer::empty(),         // New
        default => JsonBuffer::empty(),
    };
}
```

#### 3. Add OutputMode Variant

```php
enum OutputMode {
    case JsonSchema;
    case MdJson;
    case Tools;
    case Yaml;    // New
    case Xml;     // New
    // ...
}
```

#### 4. Update Deserialization

Ensure deserializer can handle the format:
```php
class YamlDeserializer implements CanDeserializeResponse {
    public function deserialize(string $yaml, ResponseModel $model): Result {
        try {
            $data = Yaml::parse($yaml);
            return Result::success($this->hydrate($data, $model->class));
        } catch (Throwable $e) {
            return Result::failure($e->getMessage());
        }
    }
}
```

**No Pipeline Changes Required**: Buffer abstraction isolates format-specific logic.

### Adding New Extraction Methods

Current methods:
- **Tool calls**: `toolArgs` field in SSE response
- **Structured output**: `contentDelta` with JSON
- **Markdown fenced**: JSON in ````json` blocks

To add new extraction methods:

#### Example: Extracting from Custom Headers

```php
final class ExtractFromHeaderReducer implements Reducer {
    public function step(mixed $accumulator, mixed $reducible): mixed {
        assert($reducible instanceof PartialInferenceResponse);

        // Extract from custom header instead of content
        $delta = $reducible->responseData['x-structured-data'] ?? '';

        if ($delta !== '') {
            $this->accumulatedBuffer = $this->accumulatedBuffer->assemble($delta);
        }

        $frame = $this->createFrame($reducible, $this->frameIndex++, $this->accumulatedBuffer);
        return $this->inner->step($accumulator, $frame);
    }
}
```

Replace `ExtractDelta` in factory with custom extractor.

#### Example: Multi-Source Extraction

Extract from multiple fields and merge:

```php
public function step(mixed $accumulator, mixed $reducible): mixed {
    // Extract from both content and reasoning
    $contentDelta = $reducible->contentDelta;
    $reasoningDelta = $reducible->reasoningContentDelta;

    // Merge strategies:
    // 1. Concatenate
    $delta = $contentDelta . $reasoningDelta;

    // 2. Prefer one over other
    $delta = $contentDelta ?: $reasoningDelta;

    // 3. JSON merge
    $delta = $this->mergeJson($contentDelta, $reasoningDelta);

    // Accumulate merged delta
    $this->accumulatedBuffer = $this->accumulatedBuffer->assemble($delta);

    // Continue pipeline...
}
```

### Adding New Pipeline Stages

Insert custom stages anywhere in the pipeline:

#### Example: Content Filtering Stage

```php
final readonly class FilterSensitiveContent implements Transducer {
    public function __invoke(Reducer $reducer): Reducer {
        return new class($reducer) implements Reducer {
            public function step(mixed $accumulator, mixed $reducible): mixed {
                assert($reducible instanceof PartialFrame);

                // Filter buffer content
                $filtered = $this->removePII($reducible->buffer->normalized());
                $newBuffer = /* create buffer with filtered content */;

                $frame = $reducible->withBuffer($newBuffer);
                return $this->inner->step($accumulator, $frame);
            }

            private function removePII(string $content): string {
                // Custom filtering logic
            }
        };
    }
}
```

Add to factory:
```php
$stages = [
    new ExtractDelta($mode),
    new FilterSensitiveContent(),  // Custom stage
    new DeserializeAndDeduplicate(...),
    // ...
];
```

#### Example: Metrics Collection Stage

```php
final readonly class CollectMetrics implements Transducer {
    public function __invoke(Reducer $reducer): Reducer {
        return new class($reducer, $this->metrics) implements Reducer {
            private int $frameCount = 0;
            private int $emissionCount = 0;

            public function step(mixed $accumulator, mixed $reducible): mixed {
                $this->frameCount++;

                if ($reducible->emission === Emission::ObjectReady) {
                    $this->emissionCount++;
                }

                return $this->inner->step($accumulator, $reducible);
            }

            public function complete(mixed $accumulator): mixed {
                $this->metrics->record('frames', $this->frameCount);
                $this->metrics->record('emissions', $this->emissionCount);
                return $this->inner->complete($accumulator);
            }
        };
    }
}
```

### Custom Event Dispatching

Add application-specific events:

```php
final readonly class DispatchCustomEvents implements Transducer {
    public function __invoke(Reducer $reducer): Reducer {
        return new class($reducer, $this->events) implements Reducer {
            public function step(mixed $accumulator, mixed $reducible): mixed {
                // Custom business logic
                if ($this->isHighValue($reducible->object)) {
                    $this->events->dispatch(new HighValueObjectDetected($reducible));
                }

                if ($this->isAnomaly($reducible->buffer)) {
                    $this->events->dispatch(new AnomalyDetected($reducible));
                }

                return $this->inner->step($accumulator, $reducible);
            }
        };
    }
}
```

### Custom Aggregation Strategies

Replace or wrap `AggregateStream`:

```php
final readonly class CustomAggregator implements Transducer {
    public function __invoke(Reducer $reducer): Reducer {
        return new class($reducer) implements Reducer {
            private array $objectHistory = [];
            private Statistics $stats;

            public function step(mixed $accumulator, mixed $reducible): mixed {
                // Custom accumulation logic
                $this->objectHistory[] = $reducible->value();
                $this->stats = $this->stats->update($reducible);

                // Create custom aggregate
                $aggregate = new CustomAggregate(
                    history: $this->objectHistory,
                    statistics: $this->stats,
                    latest: $reducible->value(),
                );

                return $this->inner->step($accumulator, $aggregate);
            }
        };
    }
}
```

## Performance Characteristics

### Time Complexity

- **Per chunk**: O(n) where n = accumulated buffer size
  - Partial JSON parsing: O(n)
  - Deserialization: O(n)
  - Hashing: O(n)

- **Total stream**: O(m × n) where m = chunks, n = final size
  - But n grows gradually, so average is lower

### Space Complexity

- **Memory**: O(1) per stream (constant overhead)
  - Only current frame in memory
  - Buffer size = accumulated content (unavoidable)
  - No chunk history unless `$accumulatePartials = true`

- **With accumulation**: O(m) where m = number of chunks
  - Stores all `PartialInferenceResponse` objects
  - Use for debugging/replay, not production streaming

### Optimization Strategies

1. **Skip redundant parsing**: JsonBuffer checks for braces before parsing
2. **Early deduplication**: Avoid unnecessary event dispatch
3. **Lazy evaluation**: Transducers only process when pulled
4. **Immutable data structures**: Enable structural sharing (PHP COW)

## Testing Strategy

### Unit Tests

Test each component in isolation:

```php
// Test buffer accumulation
test('JsonBuffer accumulates JSON fragments', function() {
    $buffer = JsonBuffer::empty();
    $buffer = $buffer->assemble('{"key"');
    $buffer = $buffer->assemble(': "value"}');

    expect($buffer->raw())->toBe('{"key": "value"}')
        ->and($buffer->normalized())->toBe('{"key": "value"}');
});

// Test reducer logic
test('DeserializeAndDeduplicateReducer deduplicates', function() {
    $reducer = new DeserializeAndDeduplicateReducer(...);

    $frame1 = makeFrame(buffer: makeBuffer('{"x": 1}'));
    $frame2 = makeFrame(buffer: makeBuffer('{"x": 1}'));  // Same content

    $result1 = $reducer->step(null, $frame1);
    $result2 = $reducer->step(null, $frame2);

    expect($result1->emission)->toBe(Emission::ObjectReady)
        ->and($result2->emission)->toBe(Emission::None);  // Deduplicated
});
```

### Integration Tests

Test full pipeline with mock data:

```php
test('pipeline processes JSON stream', function() {
    $chunks = [
        new PartialInferenceResponse(contentDelta: '{"name"'),
        new PartialInferenceResponse(contentDelta: ': "Alice"}'),
    ];

    $stream = $factory->makeObservableStream(
        source: $chunks,
        responseModel: $model,
        mode: OutputMode::JsonSchema,
    );

    $aggregates = iterator_to_array($stream);

    expect($aggregates)->toHaveCount(2)
        ->and($aggregates[1]->value->name)->toBe('Alice');
});
```

### Feature Tests

Test with real LLM responses (using `FakeInferenceDriver`):

```php
test('extracts user data from streaming response', function() {
    $driver = new FakeInferenceDriver(
        responses: [
            '{"name": "Al',
            'ice", "age": ',
            '30}',
        ],
    );

    $users = $instructor->respond(
        input: 'Extract user',
        responseModel: User::class,
        options: ['stream' => true],
    );

    $updates = [];
    foreach ($users as $partial) {
        $updates[] = $partial->value();
    }

    expect($updates)->toHaveCount(3)
        ->and($updates[0]->name)->toBe('Al')
        ->and($updates[2]->age)->toBe(30);
});
```

## Common Patterns

### Progressive UI Updates

```php
$stream = $instructor->respondStream(
    input: "Generate a story",
    responseModel: Story::class,
);

foreach ($stream as $aggregate) {
    $story = $aggregate->value;

    // Update UI progressively
    echo "<div id='title'>{$story->title}</div>\n";
    echo "<div id='content'>" . substr($story->content, 0, 100) . "...</div>\n";
    echo "<div id='tokens'>{$aggregate->usage->total()} tokens</div>\n";

    flush();
}
```

### Event-Driven Architecture

```php
$events->wiretap(PartialResponseGenerated::class, function($event) {
    $this->websocket->broadcast([
        'type' => 'partial',
        'data' => $event->response->value(),
    ]);
});

$events->wiretap(StreamedResponseReceived::class, function($event) {
    $this->websocket->broadcast([
        'type' => 'complete',
        'usage' => $event->aggregate->usage->toArray(),
    ]);
});

// Stream will automatically dispatch events
$instructor->respondStream(...);
```

### Streaming Lists

```php
class SearchResults implements Sequenceable {
    /** @var SearchResult[] */
    public array $results;

    public function getSequence(): array {
        return $this->results;
    }
}

$stream = $instructor->respondStream(
    input: "Search for AI papers",
    responseModel: SearchResults::class,
);

foreach ($stream as $aggregate) {
    $results = $aggregate->value->results;

    // Render new results as they arrive
    foreach ($results as $index => $result) {
        if (!isset($rendered[$index])) {
            echo "<div class='result'>{$result->title}</div>\n";
            $rendered[$index] = true;
        }
    }
}
```

### Error Handling

```php
$stream = $instructor->respondStream(
    input: "Extract data",
    responseModel: Data::class,
);

foreach ($stream as $aggregate) {
    // Check if deserialization failed
    if (!$aggregate->hasValue()) {
        echo "Waiting for valid data...\n";
        continue;
    }

    $data = $aggregate->value;
    // Use data...
}

// Or handle via events
$events->wiretap(PartialResponseGenerated::class, function($event) {
    if (!$event->response->hasValue()) {
        Log::warning('Partial deserialization failed', [
            'content' => $event->response->content(),
        ]);
    }
});
```

## Troubleshooting

### Issue: No Partial Updates

**Symptom**: Only final object emitted, no intermediate updates.

**Cause**: Deduplication preventing emissions (all partials hash the same).

**Solution**: Check buffer normalization - may be returning constant value.

```php
// Debug: Log normalized content
public function step(mixed $accumulator, mixed $reducible): mixed {
    $normalized = $reducible->buffer->normalized();
    Log::debug("Normalized: {$normalized}");
    // ...
}
```

### Issue: Memory Leak

**Symptom**: Memory grows unbounded during streaming.

**Cause**: `$accumulatePartials = true` storing all chunks.

**Solution**: Disable accumulation or manually garbage collect:

```php
$stream = $factory->makeObservableStream(
    source: $chunks,
    responseModel: $model,
    mode: $mode,
    accumulatePartials: false,  // Don't store history
);
```

### Issue: Invalid JSON Errors

**Symptom**: Deserialization failures on valid-looking JSON.

**Cause**: Partial JSON parser limitations or buffer normalization bug.

**Solution**: Log raw vs normalized content:

```php
Log::debug('Buffer', [
    'raw' => $buffer->raw(),
    'normalized' => $buffer->normalized(),
]);
```

### Issue: Missing Events

**Symptom**: Event handlers not firing.

**Cause**: Event system not wired, or wrong event class.

**Solution**: Verify event registration before stream:

```php
$events->wiretap(PartialResponseGenerated::class, function($event) {
    Log::info('Event received', ['value' => $event->response->value()]);
});

// Verify events are dispatched
Log::info('Starting stream');
$instructor->respondStream(...);
```

## Future Enhancements

### Planned Features

1. **Parallel Extraction**: Extract multiple objects from single stream
2. **Branching Pipelines**: Route frames to different processing paths
3. **Buffering Strategies**: Time-based or size-based emission throttling
4. **Compression**: Compress accumulated buffers for memory efficiency
5. **Replay**: Persist stream for debugging/replay

### Extension Ideas

1. **Validation Middleware**: Schema validation before deserialization
2. **Rate Limiting**: Throttle emissions based on time/count
3. **Caching**: Cache normalized buffers to avoid re-parsing
4. **Metrics**: Built-in latency/throughput tracking
5. **Tracing**: OpenTelemetry integration for observability

## Conclusion

The Clean response iterator provides a robust, extensible foundation for streaming structured extraction. Its transducer-based architecture enables:

- **Low latency**: Process chunks as they arrive
- **Low memory**: Constant overhead per stream
- **Extensibility**: Add formats/stages without core changes
- **Observability**: Rich event system for monitoring
- **Testability**: Pure functions, easy to unit test

By separating extraction, assembly, and deserialization concerns, the pipeline remains maintainable as requirements evolve.
