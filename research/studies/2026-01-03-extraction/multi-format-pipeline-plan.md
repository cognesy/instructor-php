# Plan: Multi-Format Pipeline Refactoring

**Date:** 2026-01-03
**Status:** Completed

## Design Decision: OutputMode Derives DataFormat (Per Call)

**DataFormat is derived from OutputMode per `extract()` call** - no instance-level `DataFormat` state.
Allow an explicit override parameter on `extract()` for internal/advanced use; avoid `withFormat()`.

```php
// In OutputMode enum or ResponseExtractor
private function resolveFormat(OutputMode $mode, ?DataFormat $override = null): DataFormat
{
    if ($override !== null) {
        return $override;
    }

    return match($mode) {
        OutputMode::Tools, OutputMode::Json, OutputMode::JsonSchema, OutputMode::MdJson => DataFormat::Json,
        // Future:
        // OutputMode::MdYaml, OutputMode::Yaml => DataFormat::Yaml,
        // OutputMode::MdXml, OutputMode::Xml => DataFormat::Xml,
        default => DataFormat::Json, // safe default
    };
}
```

**No `withFormat()` method needed** - format is inferred from mode; optional override is per-call.

## Problem Statement

The current extraction/deserialization pipeline is deeply coupled to JSON:
- All extractors assume JSON content (SmartBraceExtractor tracks `{`, etc.)
- `ResponseExtractor.extract()` does `json_decode()` at line 226
- Streaming buffers use `Json::fromPartial()` with `str_contains($raw, '{')`
- Need to support future formats: YAML, XML, etc.

**Key Question:** Where should format-specific parsing (string→array) live?

## Analysis: Extraction vs Parsing Coupling

After analyzing the actual codebase:

| Extractor | Format Knowledge |
|-----------|------------------|
| `DirectJsonExtractor` | `json_decode()` for validation |
| `SmartBraceExtractor` | Tracks `{` depth, `"` strings - inherently JSON |
| `MarkdownBlockExtractor` | Looks for ` ```json ` blocks |
| `ResilientJsonExtractor` | Uses `ResilientJson::parse()` |

**Conclusion:** Extraction and parsing ARE coupled - you cannot find YAML content with JSON extractors.

**The Natural Separation Point:**
```
Extractors: Find + Validate → format-specific string
Parsers: Convert string → canonical PHP array
Deserializer: Hydrate array → PHP object (format-agnostic)
```

## Recommended Architecture: Encapsulated ResponseExtractor

**Key Insight:** Hide all complexity inside `ResponseExtractor`. The public contract is simple:
```php
ResponseExtractor::extract(response, mode) → Result<array>
```

All format-specific logic (extractors, parsers, format detection) is **internal** to ResponseExtractor.

```
LLM Response
    ↓
ResponseExtractor.extract(response, mode) → Result<array>
    │
    │  INTERNAL (hidden from consumers):
    │  ├── Data Access: get content string based on mode
    │  ├── Format Detection: determine DataFormat from OutputMode
    │  ├── Extraction: find structured content (format-specific extractors)
    │  └── Parsing: convert to array (format-specific parser)
    │
    ↓
ResponseDeserializer.deserialize(array, model) → object
    │
    ↓
ResponseValidator.validate(object)
```

**Benefits:**
- Clean public API: extractor takes messy input, returns canonical array
- All format complexity hidden behind one service
- Strict separation: Parsing (String -> Array) vs Deserialization (Array -> Object)
- Downstream code (Deserializer, Validator) only deals with structured data (arrays)

---

## Implementation Steps

### Phase 1: Internal Infrastructure (Hidden Inside ResponseExtractor)

All these classes are **internal** - not exposed in public API.

#### 1.1 Create DataFormat Enum (internal)
**File:** `packages/instructor/src/Extraction/Enums/DataFormat.php`

```php
/** @internal */
enum DataFormat: string
{
    case Json = 'json';
    case Yaml = 'yaml';
    case Xml = 'xml';
}
```

#### 1.2 Create CanParseContent Contract (internal)
**File:** `packages/instructor/src/Extraction/Contracts/CanParseContent.php`

```php
/** @internal */
interface CanParseContent
{
    /** @return Result<array<string, mixed>, string> */
    public function parse(string $content): Result;
}
```

#### 1.3 Create JsonParser (internal) - Leverage Existing Utils
**File:** `packages/instructor/src/Extraction/Parsers/JsonParser.php`

Leverage existing `packages/utils/src/Json/Json.php` utilities:

```php
/** @internal */
final class JsonParser implements CanParseContent
{
    public function parse(string $content): Result
    {
        // Use existing Json utility - it has robust decoding with error handling
        return Result::try(function () use ($content): array {
            $decoded = Json::decode($content);
            if (!is_array($decoded)) {
                throw new InvalidArgumentException('Parsed JSON must be an object/array.');
            }
            return $decoded;
        });
    }
}
```

### Phase 2: Refactor ResponseExtractor

**File:** `packages/instructor/src/Extraction/ResponseExtractor.php`

The public contract stays clean - all complexity is internal. **Format is derived strictly from OutputMode.**

```php
class ResponseExtractor implements CanExtractResponse, CanProvideContentBuffer
{
    // ... constructor ...

    /**
     * Extract structured content from response.
     * @return Result<array<string, mixed>, string>
     */
    public function extract(InferenceResponse $response, OutputMode $mode): Result
    {
        // 1. Get content string
        $content = $this->getContentString($response, $mode);

        // 2. Resolve format + parser for this call
        $format = $this->resolveFormat($mode);
        $parser = $this->resolveParser($format);
        $extractors = $this->extractors ?? $this->defaultExtractorsFor($format);

        // 3. Find structured content using format-specific extractors
        $extracted = $this->extractContent($content, $extractors);
        if ($extracted->isFailure()) {
            return $extracted;
        }

        // 4. Parse to array using format-specific parser
        return $parser->parse($extracted->unwrap());
    }

    // Internal: resolve format from mode
    private function resolveFormat(OutputMode $mode): DataFormat
    {
        return match($mode) {
            OutputMode::Tools, OutputMode::Json, OutputMode::JsonSchema, OutputMode::MdJson => DataFormat::Json,
            // Future:
            // OutputMode::MdYaml, OutputMode::Yaml => DataFormat::Yaml,
            default => DataFormat::Json,
        };
    }

    // Internal: resolve parser based on format
    private function resolveParser(DataFormat $format): CanParseContent
    {
        return match($format) {
            DataFormat::Json => new JsonParser(),
        };
    }
}
```

**Key Change:** `json_decode()` moves into `JsonParser`. `ResponseExtractor` is the definitive source of "Parsed Array" data.

### Phase 3: Streaming Support

#### 3.1 Add `parsed()` to CanBufferContent
**File:** `packages/instructor/src/Extraction/Contracts/CanBufferContent.php`

```php
interface CanBufferContent
{
    // ... existing methods ...
    /** @return Result<array<string, mixed>, string> */
    public function parsed(): Result;  // NEW
}
```

#### 3.2 Update Buffer Implementations
- `JsonBuffer::parsed()` - `Result::try(fn() => Json::fromPartial($this->normalized())->toArray())`
- `ExtractingJsonBuffer::parsed()` - same
- `ToolsBuffer::parsed()` - same

### Phase 4: Downstream Changes (Array-Only Migration)

#### 4.1 Update CanDeserializeResponse Interface
**File:** `packages/instructor/src/Deserialization/Contracts/CanDeserializeResponse.php`

**BREAKING CHANGE:** Migrate interface to accept array only.

```php
interface CanDeserializeResponse
{
    /** @param array<string, mixed> $data */
    public function deserialize(array $data, ResponseModel $responseModel): Result;
}
```

#### 4.2 Update ResponseDeserializer
**File:** `packages/instructor/src/Deserialization/ResponseDeserializer.php`

Refactor to implement the new interface. Logic currently in `deserializeFromArray` becomes the main `deserialize` method.
Legacy string-based `deserialize` logic (with `json_decode`) is removed or deprecated/private.

#### 4.3 Update CanValidatePartialResponse Interface
**File:** `packages/instructor/src/Validation/Contracts/CanValidatePartialResponse.php`

**BREAKING CHANGE:** Migrate interface to accept array only.

```php
interface CanValidatePartialResponse
{
    /** @param array<string, mixed> $data */
    public function validatePartialResponse(array $data, ResponseModel $responseModel): Result;
}
```

#### 4.4 Update PartialValidation
**File:** `packages/instructor/src/Validation/PartialValidation.php`

Refactor `validatePartialResponse`:
- Remove `Json::fromPartial($text)` calls.
- Accept `$data` array directly.
- Apply validation rules (schema check, field subset check) on the array.

#### 4.5 ModularPipeline Updates
**File:** `packages/instructor/src/ResponseIterators/ModularPipeline/Pipeline/DeserializeAndDeduplicateReducer.php`

Update to use `buffer.parsed()` and pass arrays to downstream services.

```php
// 1. Get parsed array from buffer
$parsed = $reducible->buffer->parsed();
if ($parsed->isFailure()) {
    // If parsing fails (invalid JSON), we can't deserialize/validate yet.
    // Forward error or wait for more data?
    // Current behavior implies waiting or failing.
    $frame = $reducible->withObject($parsed);
    return $this->inner->step($accumulator, $frame);
}

// 2. Pass ARRAY to createObject
$result = $this->createObject($parsed->unwrap());
```

**Refactor `createObject(array $data)`:**
```php
private function createObject(array $data): Result {
    // Validate (Array)
    $validationResult = $this->validator->validatePartialResponse($data, $this->responseModel);
    if ($validationResult->isFailure()) { return $validationResult; }

    // Deserialize (Array)
    $deserialized = $this->deserializer->deserialize($data, $this->responseModel);
    // ...
}
```

**No `parser` injection needed** in `ModularStreamFactory` or `DeserializeAndDeduplicateReducer`. The buffer provides the parsed data.

---

## Files Summary

### New Files (Internal)
| File | Purpose |
|------|---------|
| `Extraction/Enums/DataFormat.php` | Internal format enum |
| `Extraction/Contracts/CanParseContent.php` | Internal parser contract |
| `Extraction/Parsers/JsonParser.php` | JSON string → array |

### Modified Files (Refactoring)
| File | Changes |
|------|---------|
| `ResponseExtractor.php` | Extractors return array, strict OutputMode->Format mapping |
| `CanBufferContent.php` | Add `parsed(): Result<array>` |
| `JsonBuffer.php` | Implement `parsed()` |
| `ExtractingJsonBuffer.php` | Implement `parsed()` |
| `ToolsBuffer.php` | Implement `parsed()` |
| `CanDeserializeResponse.php` | **Breaking:** Change input to `array $data` |
| `ResponseDeserializer.php` | Implement `deserialize(array)` |
| `CanValidatePartialResponse.php` | **Breaking:** Change input to `array $data` |
| `PartialValidation.php` | Implement `validatePartialResponse(array)` |
| `DeserializeAndDeduplicateReducer.php` | Use `buffer.parsed()` + array interfaces |

### Unchanged Files
| File | Reason |
|------|--------|
| `CanExtractContent.php` | Extractors still return `Result<string>` |
| `ResponseValidator.php` | Works on objects, unchanged |
| `StructuredOutput.php` | No change needed |

---

## Backward Compatibility

**Breaking Changes:**
1. `CanDeserializeResponse::deserialize` now requires `array`, not `string`.
2. `CanValidatePartialResponse::validatePartialResponse` now requires `array`, not `string`.

**Mitigation:**
- These are internal interfaces mostly used by the pipeline.
- `ResponseDeserializer` can keep a deprecated `deserializeString` method if needed for other callers, but the interface will enforce array.

---

## Trade-offs

### Chosen Approach: Strict Array Pipeline
**Pros:**
- **Type Safety:** Pipeline clearly distinguishes between "Raw String" (Buffer) and "Structured Data" (Array).
- **Performance:** Parsing happens once (in Extractor or Buffer), not repeated in Validator and Deserializer.
- **Simplicity:** Validator/Deserializer don't need to know about JSON/YAML parsing nuances.

**Cons:**
- **Breaking Changes:** Requires updating interfaces and implementations.
- **Refactoring Effort:** Need to touch Validation and Deserialization logic.

---

## Critical Files to Review/Modify

1. `packages/instructor/src/Extraction/ResponseExtractor.php`
2. `packages/instructor/src/Deserialization/Contracts/CanDeserializeResponse.php`
3. `packages/instructor/src/Deserialization/ResponseDeserializer.php`
4. `packages/instructor/src/Validation/Contracts/CanValidatePartialResponse.php`
5. `packages/instructor/src/Validation/PartialValidation.php`
6. `packages/instructor/src/ResponseIterators/ModularPipeline/Pipeline/DeserializeAndDeduplicateReducer.php`
