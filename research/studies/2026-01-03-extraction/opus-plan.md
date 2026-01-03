# Plan: Unified Extraction Configuration (Opus v2)

**Date:** 2026-01-03
**Status:** Draft - For Review

## Problem Statement

`StructuredOutput` has incoherent extraction API:
1. `withExtractor(CanExtractResponse)` - sets custom extractor
2. `withExtractionStrategies(ExtractionStrategy...)` - creates extractor with strategies
3. `withStreamingExtractionStrategies(ExtractionStrategy...)` - separate streaming config

This violates clean API design:
- Strategies shouldn't be injected via facade - extractor should be pre-configured
- Streaming config is disconnected from extractor
- Inconsistent with how we'd use other services

---

## Deep Analysis: Current vs Proposed Patterns

### Current Pattern Comparison

| Aspect | ResponseValidator | ResponseDeserializer | JsonResponseExtractor |
|--------|------------------|---------------------|----------------------|
| **Service interface** | `CanValidateResponse` | `CanDeserializeResponse` | `CanExtractResponse` |
| **Orchestrates** | `CanValidateObject[]` | `CanDeserializeClass[]` | `ExtractionStrategy[]` (internal) |
| **Behavior** | All run, merge results | First success wins | First success wins |
| **Self-processing** | `CanValidateSelf` | `CanDeserializeSelf` | None |
| **Public contract** | Yes | Yes | **No** (strategies are internal) |

**Key Insight:** `ExtractionStrategy` is an internal implementation detail, not a first-class contract like `CanValidateObject` or `CanDeserializeClass`. This is inconsistent.

### Proposed Unified Pattern

```
ResponseExtractor (service class) implements CanExtractResponse
├── Orchestrates: CanExtractContent[] instances
├── Behavior: First success wins (like deserializer)
├── Self-processing: CanExtractSelf (optional)
└── NO special tool call handling - unified extraction
```

**Key Insight from ContentBuffer Analysis:**

Looking at `JsonBuffer` and `ToolsBuffer`, they are **nearly identical** - both use `Json::fromPartial()` for normalization. The only differences are:
1. Delta source in `ExtractDeltaReducer`: `toolArgs` vs `contentDelta` (line 53-56)
2. Buffer class selection (line 88-92)

This reveals that "tool call handling" is NOT special extraction logic - it's just **data access** (which field to read). The extraction/normalization is uniform.

**Two Layers:**
1. **Data Access Layer**: Get content string from response (mode-dependent)
2. **Extraction Layer**: Normalize/extract JSON from string (mode-agnostic)

This mirrors `ResponseDeserializer` exactly:
- Service class orchestrates multiple "doers"
- Doers implement a simple extraction interface
- First success wins
- Data access is separate from extraction logic

---

## Naming Convention Fixes

| Current | Proposed | Reason |
|---------|----------|--------|
| `ContentBuffer` | `CanBufferContent` | Follow `CanDoSomething` convention |
| `ExtractionStrategy` | `CanExtractContent` | Consistency with `CanValidateObject`, `CanDeserializeClass` |

---

## Solution: Full Service Pattern

### New Interface: `CanExtractContent`

Replaces `ExtractionStrategy` with proper naming. **Format-agnostic** - extracts structured content that could be JSON, XML, YAML, PHP array, etc.

```php
interface CanExtractContent
{
    /**
     * Extract structured content from raw response.
     *
     * @param string $content Raw content that may contain structured data
     * @return Result<string, string> Success with extracted content string, or Failure
     *
     * Note: This interface is format-agnostic. The returned string could be:
     * - JSON: {"name": "John"}
     * - XML: <user><name>John</name></user>
     * - YAML: name: John
     * - PHP array: ['name' => 'John']
     *
     * The ResponseExtractor orchestrates extractors and delegates format
     * decoding to the appropriate deserializer.
     */
    public function extract(string $content): Result;

    /**
     * Extractor name for debugging/logging.
     */
    public function name(): string;
}
```

**Key Design Point:** Extractors are responsible for *finding and extracting* structured content from potentially messy LLM output. They don't decode - that's the deserializer's job. This separation allows:
- JSON extractors to handle markdown-wrapped JSON, partial JSON, etc.
- XML extractors to handle CDATA sections, namespaces, etc.
- YAML extractors to handle embedded YAML blocks
- Each format can have its own extraction chain

### New Service: `ResponseExtractor`

Replaces `JsonResponseExtractor` with service pattern. **Format-agnostic** - works with JSON, XML, YAML, etc.

```php
class ResponseExtractor implements CanExtractResponse
{
    public function __construct(
        private EventDispatcherInterface $events,
        /** @var CanExtractContent[]|class-string[] */
        private array $extractors,
        private StructuredOutputConfig $config,
    ) {}

    /**
     * Extract structured content from response.
     *
     * @return Result<array|string, string> Success with extracted content, or Failure
     *
     * Returns:
     * - For JSON: decoded array/object
     * - For XML/YAML: raw string (deserializer handles parsing)
     * - For tool calls: decoded args array
     */
    public function extract(InferenceResponse $response, OutputMode $mode): Result
    {
        // 1. DATA ACCESS: Get content string based on mode (uniform for all modes)
        $content = $this->getContentString($response, $mode);

        if (empty($content)) {
            return Result::failure('Empty response content');
        }

        // 2. EXTRACTION: Try extractors in order (format-agnostic)
        return $this->extractFromContent($content);
    }

    /**
     * Uniform data access - no special cases, just different sources.
     */
    private function getContentString(InferenceResponse $response, OutputMode $mode): string
    {
        return match ($mode) {
            OutputMode::Tools => $this->getToolCallContent($response),
            default => $response->content(),
        };
    }

    private function getToolCallContent(InferenceResponse $response): string
    {
        // Convert tool call args to string (JSON for now, could be other formats)
        $toolCalls = $response->toolCalls();
        if ($toolCalls->isEmpty()) {
            return '';
        }

        if ($toolCalls->hasSingle()) {
            return json_encode($toolCalls->first()?->args() ?? []);
        }

        return json_encode($toolCalls->toArray());
    }

    /**
     * Format-agnostic extraction - same logic for all content types.
     * Extractors find and isolate the structured content.
     * Decoding (JSON, XML, YAML parsing) happens downstream in deserializer.
     */
    private function extractFromContent(string $content): Result
    {
        foreach ($this->extractors as $extractor) {
            $extractor = $this->resolveExtractor($extractor);
            $result = $extractor->extract($content);
            if ($result->isSuccess()) {
                // Return extracted content - let deserializer handle format-specific parsing
                return $result;
            }
        }
        return Result::failure('No extractor succeeded');
    }
}
```

### Updated StructuredOutput API

```php
// Like other services - pass configured extractors
public function withExtractors(CanExtractContent|string ...$extractors): static {
    $this->extractors = $extractors;
    return $this;
}

// In create():
$responseExtractor = new ResponseExtractor(
    events: $this->events,
    extractors: $this->extractors ?: [
        DirectJsonExtractor::class,
        ResilientJsonExtractor::class,
        MarkdownBlockExtractor::class,
        BracketMatchingExtractor::class,
        SmartBraceExtractor::class,
    ],
    config: $config,
);
```

---

## Format Extensibility: JSON, XML, YAML, and Beyond

The extraction layer is intentionally **format-agnostic**. This enables future support for:

| Format | Extractor Examples | Use Case |
|--------|-------------------|----------|
| **JSON** | `DirectJsonExtractor`, `MarkdownBlockExtractor`, `BracketMatchingExtractor` | Most LLM responses |
| **XML** | `XmlTagExtractor`, `CdataExtractor` | Legacy system integration |
| **YAML** | `YamlBlockExtractor`, `FrontmatterExtractor` | Config-style responses |
| **PHP** | `PhpArrayExtractor` | Native PHP serialization |

### Separation of Concerns

```
LLM Response
    │
    ▼
┌─────────────────────────────────────────────────────────┐
│  EXTRACTION (ResponseExtractor)                         │
│  - Data Access: get content string from response        │
│  - Content Extraction: find structured data in content  │
│  - Output: raw structured string (JSON/XML/YAML/etc.)   │
└─────────────────────────────────────────────────────────┘
    │
    ▼
┌─────────────────────────────────────────────────────────┐
│  DESERIALIZATION (ResponseDeserializer)                 │
│  - Parse format (JSON decode, XML parse, YAML parse)    │
│  - Map to response model class                          │
│  - Output: typed PHP object                             │
└─────────────────────────────────────────────────────────┘
    │
    ▼
┌─────────────────────────────────────────────────────────┐
│  VALIDATION (ResponseValidator)                         │
│  - Validate business rules                              │
│  - Output: validated object or errors                   │
└─────────────────────────────────────────────────────────┘
```

**Key insight:** Extractors don't parse - they *find and isolate* structured content from messy LLM output. The deserializer handles format-specific parsing. This separation allows:
- Different extractors for same format (e.g., multiple JSON extraction strategies)
- Same extractor pattern applied to different formats
- Clean pipeline where each stage has single responsibility

---

## Sync vs Streaming: Different Logic Required

| Aspect | Sync Extraction | Streaming Extraction |
|--------|----------------|---------------------|
| **Input** | Complete `InferenceResponse` | Partial deltas |
| **Default extractors** | 5 (full chain) | 2 (fast subset) |
| **Why different** | Can try markdown, brace matching | Incomplete content, need speed |

### Streaming Support via `CanProvideContentBuffer`

```php
interface CanProvideContentBuffer
{
    public function makeContentBuffer(OutputMode $mode): CanBufferContent;
}

// ResponseExtractor can implement this:
class ResponseExtractor implements CanExtractResponse, CanProvideContentBuffer
{
    /** @var CanExtractContent[]|null Streaming-specific extractors */
    private ?array $streamingExtractors = null;

    public function makeContentBuffer(OutputMode $mode): CanBufferContent
    {
        $extractors = $this->streamingExtractors ?? $this->getDefaultStreamingExtractors();
        return match ($mode) {
            OutputMode::Tools => ToolsBuffer::empty(),
            default => ExtractingJsonBuffer::empty($extractors),
        };
    }
}
```

---

## Implementation Steps

### Step 1: Rename `ContentBuffer` to `CanBufferContent`

**Files to rename:**
- `ContentBuffer.php` → Update interface name to `CanBufferContent`
- Update all implementations: `ExtractingJsonBuffer`, `JsonBuffer`, `ToolsBuffer`, `TextBuffer`

### Step 2: Rename `ExtractionStrategy` to `CanExtractContent`

**Files:**
- Rename interface: `ExtractionStrategy` → `CanExtractContent`
- Rename implementations to match:
  - `DirectJsonStrategy` → `DirectJsonExtractor`
  - `ResilientJsonStrategy` → `ResilientJsonExtractor`
  - `MarkdownCodeBlockStrategy` → `MarkdownBlockExtractor`
  - `BracketMatchingStrategy` → `BracketMatchingExtractor`
  - `SmartBraceMatchingStrategy` → `SmartBraceExtractor`

### Step 3: Create `ResponseExtractor` Service

**File:** `packages/instructor/src/Extraction/ResponseExtractor.php`

Replace `JsonResponseExtractor` with a proper service class:

```php
class ResponseExtractor implements CanExtractResponse, CanProvideContentBuffer
{
    public function __construct(
        private EventDispatcherInterface $events,
        /** @var CanExtractContent[]|class-string[] */
        private array $extractors,
        /** @var CanExtractContent[]|class-string[]|null */
        private ?array $streamingExtractors = null,
        private StructuredOutputConfig $config,
    ) {}

    #[\Override]
    public function extract(InferenceResponse $response, OutputMode $mode): Result
    {
        // 1. DATA ACCESS: Get content string based on mode (uniform for all modes)
        $content = $this->getContentString($response, $mode);

        if (empty($content)) {
            return Result::failure('Empty response content');
        }

        // 2. EXTRACTION: Try extractors in order (mode-agnostic)
        return $this->extractFromContent($content);
    }

    /**
     * Uniform data access - no special cases, just different sources.
     */
    private function getContentString(InferenceResponse $response, OutputMode $mode): string
    {
        return match ($mode) {
            OutputMode::Tools => $this->getToolCallContent($response),
            default => $response->content(),
        };
    }

    private function getToolCallContent(InferenceResponse $response): string
    {
        $toolCalls = $response->toolCalls();
        if ($toolCalls->isEmpty()) {
            return '';
        }
        if ($toolCalls->hasSingle()) {
            return json_encode($toolCalls->first()?->args() ?? []);
        }
        return json_encode($toolCalls->toArray());
    }

    #[\Override]
    public function makeContentBuffer(OutputMode $mode): CanBufferContent
    {
        $extractors = $this->streamingExtractors ?? self::defaultStreamingExtractors();
        return match ($mode) {
            OutputMode::Tools => ToolsBuffer::empty(),
            default => ExtractingJsonBuffer::empty($extractors),
        };
    }

    public static function defaultExtractors(): array
    {
        return [
            DirectJsonExtractor::class,
            ResilientJsonExtractor::class,
            MarkdownBlockExtractor::class,
            BracketMatchingExtractor::class,
            SmartBraceExtractor::class,
        ];
    }

    public static function defaultStreamingExtractors(): array
    {
        return [
            DirectJsonExtractor::class,
            ResilientJsonExtractor::class,
        ];
    }

    /**
     * Mode-agnostic extraction - same logic for all content types.
     */
    private function extractFromContent(string $content): Result
    {
        $this->dispatch(new ExtractionStarted([...]));
        $errors = [];

        foreach ($this->extractors as $extractor) {
            $extractor = $this->resolveExtractor($extractor);
            $result = $extractor->extract($content);

            if ($result->isSuccess()) {
                $this->dispatch(new ExtractionCompleted([...]));
                return $this->decodeJson($result->unwrap());
            }

            $errors[$extractor->name()] = $result->errorMessage();
        }

        $this->dispatch(new ExtractionFailed([...]));
        return Result::failure("No extractor succeeded");
    }
}
```

### Step 4: Create `CanProvideContentBuffer` Interface

**File:** `packages/instructor/src/Extraction/Contracts/CanProvideContentBuffer.php`

```php
interface CanProvideContentBuffer
{
    public function makeContentBuffer(OutputMode $mode): CanBufferContent;
}
```

### Step 5: Update `ResponseIteratorFactory`

**File:** `packages/instructor/src/ResponseIteratorFactory.php`

Changes:
- Remove `$streamingExtractionStrategies` parameter
- Use `CanProvideContentBuffer` interface for buffer creation

```php
class ResponseIteratorFactory
{
    public function __construct(
        // ... other params ...
        ?CanExtractResponse $extractor = null,
        // REMOVE: array $streamingExtractionStrategies = [],
    ) {}

    private function makeBufferFactory(): ?Closure
    {
        if ($this->extractor instanceof CanProvideContentBuffer) {
            $extractor = $this->extractor;
            return fn(OutputMode $mode) => $extractor->makeContentBuffer($mode);
        }
        return null;
    }
}
```

### Step 6: Update `StructuredOutput`

**File:** `packages/instructor/src/StructuredOutput.php`

Changes:
- Remove `$streamingExtractionStrategies` property
- Remove `withExtractionStrategies()` method
- Remove `withStreamingExtractionStrategies()` method
- Add `withExtractors()` method (like `withValidators()`)
- Update `create()` to build `ResponseExtractor`

```php
class StructuredOutput
{
    /** @var CanExtractContent[]|class-string[] */
    protected array $extractors = [];

    // New method - consistent with withValidators(), withDeserializers()
    public function withExtractors(CanExtractContent|string ...$extractors): static {
        $this->extractors = $extractors;
        return $this;
    }

    public function create(): PendingStructuredOutput {
        // ...

        // Create ResponseExtractor like other services
        $responseExtractor = new ResponseExtractor(
            events: $this->events,
            extractors: $this->extractors ?: ResponseExtractor::defaultExtractors(),
            config: $config,
        );

        $executorFactory = new ResponseIteratorFactory(
            // ...
            extractor: $responseExtractor,
        );
    }
}
```

### Step 7: Update Tests and Examples

**Files:**
- `packages/instructor/tests/Feature/Instructor/CustomExtractorTest.php`
- `examples/A05_Extras/CustomExtractor/run.php`

```php
// OLD:
$result = (new StructuredOutput)
    ->withExtractionStrategies(new DirectJsonStrategy())
    ->with(...)
    ->get();

// NEW (consistent with other services):
$result = (new StructuredOutput)
    ->withExtractors(DirectJsonExtractor::class, ResilientJsonExtractor::class)
    ->with(...)
    ->get();
```

---

## User-Facing API After Refactor

### Basic Usage (Defaults)
```php
$result = (new StructuredOutput)
    ->with(messages: 'Extract user data', responseModel: User::class)
    ->get();
// Uses default extractors: Direct → Resilient → Markdown → Bracket → SmartBrace
```

### Custom Extractors (Like Validators/Deserializers)
```php
$result = (new StructuredOutput)
    ->withExtractors(
        DirectJsonExtractor::class,
        ResilientJsonExtractor::class,
        MyCustomExtractor::class,
    )
    ->with(messages: 'Extract user data', responseModel: User::class)
    ->get();
```

### Custom Extractor Instances
```php
$result = (new StructuredOutput)
    ->withExtractors(
        new DirectJsonExtractor(),
        new MyCustomExtractor(option: 'value'),
    )
    ->with(...)
    ->get();
```

### Implementing Custom Extractor (JSON)
```php
class XmlCdataExtractor implements CanExtractContent
{
    public function extract(string $content): Result
    {
        if (preg_match('/<!\[CDATA\[(.*?)\]\]>/s', $content, $matches)) {
            return Result::success(trim($matches[1]));
        }
        return Result::failure('No CDATA found');
    }

    public function name(): string { return 'xml_cdata'; }
}
```

### Implementing Custom Extractor (YAML)
```php
class YamlBlockExtractor implements CanExtractContent
{
    public function extract(string $content): Result
    {
        // Extract YAML from markdown code block
        if (preg_match('/```ya?ml\s*\n(.*?)```/s', $content, $matches)) {
            return Result::success(trim($matches[1]));
        }
        return Result::failure('No YAML block found');
    }

    public function name(): string { return 'yaml_block'; }
}
```

### Implementing Custom Extractor (XML)
```php
class XmlTagExtractor implements CanExtractContent
{
    public function __construct(private string $rootTag = 'response') {}

    public function extract(string $content): Result
    {
        $pattern = sprintf('/<%1$s[^>]*>.*<\/%1$s>/s', preg_quote($this->rootTag));
        if (preg_match($pattern, $content, $matches)) {
            return Result::success($matches[0]);
        }
        return Result::failure("No <{$this->rootTag}> tag found");
    }

    public function name(): string { return "xml_{$this->rootTag}"; }
}
```

### Using Multiple Format Extractors
```php
// Configure for XML responses with JSON fallback
$result = (new StructuredOutput)
    ->withExtractors(
        new XmlTagExtractor('user'),      // Try XML first
        DirectJsonExtractor::class,        // Fall back to JSON
        MarkdownBlockExtractor::class,     // Try markdown-wrapped
    )
    ->with(messages: 'Return user data', responseModel: User::class)
    ->get();
```

---

## Files to Modify

### Extraction Contracts (rename + stay in place)
| Current | Action | New Name/Location |
|---------|--------|-------------------|
| `Extraction/Contracts/ExtractionStrategy.php` | RENAME | → `CanExtractContent` |
| `Extraction/Contracts/CanExtractResponse.php` | KEEP | (unchanged) |
| `Extraction/Contracts/CanProvideContentBuffer.php` | CREATE | New interface |

### Extraction Strategies → Extractors (rename)
| Current | Action | New Name |
|---------|--------|----------|
| `DirectJsonStrategy.php` | RENAME | → `DirectJsonExtractor` |
| `ResilientJsonStrategy.php` | RENAME | → `ResilientJsonExtractor` |
| `MarkdownCodeBlockStrategy.php` | RENAME | → `MarkdownBlockExtractor` |
| `BracketMatchingStrategy.php` | RENAME | → `BracketMatchingExtractor` |
| `SmartBraceMatchingStrategy.php` | RENAME | → `SmartBraceExtractor` |

### Content Buffers (move from ModularPipeline to Extraction)
| Current Location | Action | New Location |
|------------------|--------|--------------|
| `ResponseIterators/ModularPipeline/ContentBuffer/ContentBuffer.php` | MOVE+RENAME | → `Extraction/Contracts/CanBufferContent.php` |
| `ResponseIterators/ModularPipeline/ContentBuffer/JsonBuffer.php` | MOVE | → `Extraction/Buffers/JsonBuffer.php` |
| `ResponseIterators/ModularPipeline/ContentBuffer/ToolsBuffer.php` | MOVE | → `Extraction/Buffers/ToolsBuffer.php` |
| `ResponseIterators/ModularPipeline/ContentBuffer/ExtractingJsonBuffer.php` | MOVE | → `Extraction/Buffers/ExtractingJsonBuffer.php` |
| `ResponseIterators/ModularPipeline/ContentBuffer/TextBuffer.php` | MOVE | → `Extraction/Buffers/TextBuffer.php` |

### Service Classes
| Current | Action | New Name |
|---------|--------|----------|
| `JsonResponseExtractor.php` | REPLACE | → `ResponseExtractor` (service class) |

### Consumers
| File | Action | Changes |
|------|--------|---------|
| `ResponseIteratorFactory.php` | MODIFY | Remove strategies param, update imports |
| `StructuredOutput.php` | MODIFY | `withExtractors()` instead of strategies |
| `ModularStreamFactory.php` | MODIFY | Update imports from new location |
| Tests and examples | MODIFY | Update to new API |

---

## Breaking Changes

1. `withExtractionStrategies()` removed → use `withExtractors(...)`
2. `withStreamingExtractionStrategies()` removed → handled by `ResponseExtractor`
3. `ExtractionStrategy` renamed → `CanExtractContent`
4. `JsonResponseExtractor` replaced → `ResponseExtractor`
5. `ContentBuffer` moved → `Extraction/Contracts/CanBufferContent`
6. Buffer implementations moved → `Extraction/Buffers/`

---

## Benefits

1. **Consistent Pattern**: Same as `ResponseValidator`, `ResponseDeserializer`
2. **Naming Convention**: All interfaces follow `CanDoSomething` pattern
3. **Clean Facade API**: `withExtractors()` matches `withValidators()`, `withDeserializers()`
4. **Service Encapsulation**: `ResponseExtractor` handles sync and streaming internally
5. **First-Class Contract**: `CanExtractContent` is a public contract, not internal detail
6. **Format Extensibility**: Support for JSON, XML, YAML, PHP arrays - extractors find content, deserializers parse it
7. **Unified Tool Handling**: No special cases - tools are just a different data source, same extraction logic
8. **Cohesive Extraction Namespace**: All extraction concerns (sync + streaming buffers) in one place
9. **Reusable Buffers**: `CanBufferContent` implementations available for any streaming pipeline
