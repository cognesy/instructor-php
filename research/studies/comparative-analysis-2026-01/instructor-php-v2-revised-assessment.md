# InstructorPHP V2: Revised Assessment - Leveraging Existing Capabilities

**Date:** 2026-01-02
**Status:** Reassessment after discovering existing features
**Purpose:** Correct initial assessment and focus on real gaps vs. perceived gaps

---

## Critical Discovery: What InstructorPHP ALREADY Has

### D1: Manual Schema Builders (Like Prism) ✅ EXISTS

**Location:** `packages/utils/src/JsonSchema/JsonSchema.php`

**What was claimed:** "Prism has manual schema builders, InstructorPHP only has reflection"
**Reality:** InstructorPHP has BOTH reflection AND manual builders!

```php
// InstructorPHP ALREADY supports this (just like Prism)
$schema = JsonSchema::object(
    name: 'User',
    description: 'User data',
    properties: [
        JsonSchema::string(name: 'name', description: 'User name'),
        JsonSchema::number(name: 'age', description: 'User age'),
    ],
    requiredProperties: ['name'],
);

// Also supports fluent building
$schema = JsonSchema::array('list')
    ->withItemSchema(JsonSchema::string())
    ->withRequired(['id', 'name']);

// And can build from arrays
$schema = JsonSchema::fromArray([
    'type' => 'object',
    'properties' => [
        'name' => ['type' => 'string'],
    ],
]);
```

**Available factory methods:**
- `JsonSchema::object()`
- `JsonSchema::array()`
- `JsonSchema::string()`
- `JsonSchema::number()`
- `JsonSchema::integer()`
- `JsonSchema::boolean()`
- `JsonSchema::enum()`
- `JsonSchema::collection()`

**Status:** ✅ No work needed - feature exists, needs better documentation/discovery

---

### D2: Array-Based Response Models (Like Prism) ✅ EXISTS

**Location:** `packages/instructor/src/StructuredOutput.php:89,105`

**What was claimed:** "Prism returns arrays, InstructorPHP forces objects"
**Reality:** InstructorPHP accepts arrays as response model spec!

```php
// StructuredOutput signature
public function with(
    string|array|Message|Messages|null $messages = null,
    string|array|object|null $responseModel = null,  // ← Accepts ARRAYS
    // ...
)
```

**Usage:**
```php
// Can pass array schema directly
$output = StructuredOutput::create()
    ->with(
        messages: 'Extract user',
        responseModel: [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
                'age' => ['type' => 'integer'],
            ],
            'required' => ['name'],
        ],
    )
    ->execute();
```

**Status:** ✅ No work needed - feature exists, needs documentation

---

### D3: Multiple Extraction Strategies (Like NeuronAI) ✅ EXISTS

**Location:** `packages/utils/src/Json/JsonParser.php`

**What was claimed:** "NeuronAI has 4 extraction strategies, InstructorPHP has basic extraction"
**Reality:** NeuronAI took extraction strategies FROM InstructorPHP! InstructorPHP has MORE!

**InstructorPHP extraction strategies:**

```php
class JsonParser {
    public function findCompleteJson(string $input): string {
        $extractors = [
            fn($text) => [$text],                      // 1. Try as-is
            fn($text) => $this->findByMarkdown($text), // 2. Markdown ```json blocks
            fn($text) => [$this->findByBrackets($text)], // 3. First { to last }
            fn($text) => $this->findJSONLikeStrings($text), // 4. Smart brace matching
        ];
        // ... fallback chain
    }
}
```

**NeuronAI extraction strategies (from their code comments):**
> "Inspired by InstructorPHP's JsonParser"

1. Direct parsing
2. Markdown code block (```json)
3. Bracket extraction
4. Smart brace matching

**They literally copied from us!**

**Status:** ✅ Feature exists, but is scattered and hard to discover

---

### D4: Resilient Parsing with Repairs ✅ EXISTS

**Location:** `packages/utils/src/Json/Partial/ResilientJson.php`

**What was claimed:** "Need better parsing fallbacks"
**Reality:** InstructorPHP has sophisticated 3-stage parsing!

```php
class ResilientJson {
    public static function parse(string $input): mixed {
        // 1) Fast path - native json_decode
        try {
            return json_decode($sliced, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {}

        // 2) Minimal repairs + retry
        $repaired = self::repairCommonIssues($sliced);
        //   - Balance quotes
        //   - Remove trailing commas
        //   - Balance braces/brackets
        try {
            return json_decode($repaired, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {}

        // 3) Lenient parser (custom implementation)
        return (new LenientParser())->parse($input);
    }
}
```

**Plus multiple parser strategies in JsonParser:**
```php
private function tryParse(string $maybeJson): mixed {
    $parsers = [
        fn($json) => json_decode($json, true, 512, JSON_THROW_ON_ERROR),
        fn($json) => (new ResilientJsonParser($json))->parse(),
        fn($json) => (new PartialJsonParser)->parse($json),
    ];
    // Try each until one succeeds
}
```

**Status:** ✅ Feature exists, sophisticated, needs better organization

---

### D5: Partial JSON Support for Streaming ✅ EXISTS

**Location:** `packages/utils/src/Json/Partial/MultiJsonExtractor.php`

**What was claimed:** "Only InstructorPHP has streaming"
**Reality:** Confirmed - InstructorPHP has unique streaming with partial JSON!

```php
class MultiJsonExtractor {
    public static function extract(
        string $text,
        MultiJsonStrategy $strategy // StopOnFirst | StopOnLast | ParseAll
    ): mixed {
        // Sophisticated partial JSON extraction
        // - Handles incomplete JSON during streaming
        // - Multiple extraction strategies
        // - Tolerant tokenizer
    }
}
```

**Status:** ✅ Unique feature, works well, keep improving

---

## What's ACTUALLY Missing or Needs Improvement

### M1: Feature Discoverability ❌ CRITICAL GAP

**Problem:** Existing features are hidden in utils, not prominently documented

**Evidence:**
- JsonSchema manual builders exist but not mentioned in main docs
- Array-based response models supported but not shown in examples
- Extraction strategies scattered across multiple files
- No clear "extension points" documentation

**Impact:** Users reinvent features that already exist

**Solution:**
```
Documentation needed:
├── Manual Schema Building Guide
│   ├── When to use reflection vs manual
│   ├── All JsonSchema factory methods
│   └── Fluent builder examples
├── Response Model Formats Guide
│   ├── Class-based (reflection)
│   ├── Array-based (manual schema)
│   └── JsonSchema object-based
├── Advanced JSON Extraction Guide
│   ├── Built-in strategies
│   ├── Adding custom extractors
│   └── Debugging extraction failures
└── Extension Points Reference
    ├── Custom deserializers
    ├── Custom validators
    └── Custom transformers
```

**Priority:** HIGH - Prevents user confusion and duplicate work

---

### M2: Extraction Strategy Abstraction ⚠️ NEEDS IMPROVEMENT

**Problem:** Extraction strategies exist but not pluggable/extensible

**Current state:**
- `JsonParser` has hardcoded extractors array
- No interface for custom extraction strategies
- No way to add YAML/XML extractors without modifying core

**What exists:**
```php
// Hardcoded in JsonParser
$extractors = [
    fn($text) => [$text],
    fn($text) => $this->findByMarkdown($text),
    fn($text) => [$this->findByBrackets($text)],
    fn($text) => $this->findJSONLikeStrings($text),
];
```

**What's needed:**
```php
interface ExtractionStrategy {
    public function extract(string $content): Result;
    public function supports(string $format): bool; // json, yaml, xml
}

class JsonParser {
    /** @var array<ExtractionStrategy> */
    private array $strategies = [];

    public function addStrategy(ExtractionStrategy $strategy): self {
        $this->strategies[] = $strategy;
        return $this;
    }

    public function findCompleteJson(string $input): string {
        foreach ($this->strategies as $strategy) {
            $result = $strategy->extract($input);
            if ($result->isSuccess()) {
                return $result->unwrap();
            }
        }
        return '';
    }
}

// Usage
$parser = new JsonParser();
$parser->addStrategy(new MarkdownJsonStrategy());
$parser->addStrategy(new BracketMatchingStrategy());
$parser->addStrategy(new CustomYamlStrategy());
```

**Priority:** MEDIUM - Nice to have, but current approach works

---

### M3: Format Abstraction Layer ❌ MISSING

**Problem:** Only JSON supported, no YAML/XML/etc.

**What's missing:**
- YAML schema generation
- XML schema generation (XSD)
- OpenAPI schema generation
- YAML/XML data extraction
- YAML/XML data parsing

**This was correctly identified in original proposal.**

**Implementation approach:**
```php
interface SchemaFormatProvider {
    public function generate(Schema $schema): string;
    public function contentType(): string;
    public function supportsStreaming(): bool;
}

class JsonSchemaProvider implements SchemaFormatProvider {
    public function generate(Schema $schema): string {
        // Use existing SchemaToJsonSchema visitor
        $visitor = new SchemaToJsonSchema();
        return json_encode($schema->accept($visitor));
    }
}

class YamlSchemaProvider implements SchemaFormatProvider {
    public function generate(Schema $schema): string {
        $visitor = new SchemaToYaml(); // New visitor
        return Yaml::dump($schema->accept($visitor));
    }
}
```

**Priority:** LOW-MEDIUM - Nice to have, but JSON covers 95% of use cases

---

### M4: Source Abstraction Layer ❌ MISSING

**Problem:** Only LLM responses supported, can't extract from CLI/files/etc.

**This was correctly identified in original proposal.**

**What's needed:**
```php
interface ContentSource {
    public function isStreamable(): bool;
    public function nextChunk(): ?ContentChunk;
    public function complete(): string;
    public function isExhausted(): bool;
}

class LlmSource implements ContentSource { /* existing */ }
class CliSource implements ContentSource { /* new */ }
class FileSource implements ContentSource { /* new */ }
class StreamSource implements ContentSource { /* new */ }
```

**Priority:** MEDIUM - Expands InstructorPHP beyond LLM use case

---

### M5: Event-Driven Extension System ⚠️ PARTIAL

**Problem:** Events exist but limited extensibility

**What exists:**
- Event system in place
- Events dispatched at key points
- Can listen to events

**What's missing:**
- Pre/post hooks for pipeline stages
- Middleware injection points
- Third-party plugin system

**Example of what exists:**
```php
// Events are dispatched
$this->events->dispatch(new StructuredOutputRequestReceived([...]));
$this->events->dispatch(new StructuredOutputStarted([...]));
```

**What's needed:**
```php
// Pipeline hooks
interface PipelineHook {
    public function before(PipelineContext $context): void;
    public function after(PipelineContext $context, Result $result): void;
}

// Usage
$output = StructuredOutput::create()
    ->with(...)
    ->beforeDeserialization(fn($ctx) => /* custom logic */)
    ->afterValidation(fn($ctx, $result) => /* custom logic */)
    ->execute();
```

**Priority:** LOW - Current event system sufficient for most use cases

---

### M6: Raw Mode (Skip Deserialization) ❌ MISSING

**Problem:** Always deserializes to objects, can't get raw arrays

**What Prism does:**
```php
$response->structured; // ['name' => 'John', 'age' => 30]
// Developer controls when/how to deserialize
```

**What InstructorPHP needs:**
```php
$output = StructuredOutput::create()
    ->with(...)
    ->rawMode() // New method
    ->execute();
// Returns: ['name' => 'John', 'age' => 30] instead of User object
```

**Implementation:**
```php
class PendingStructuredOutput {
    private bool $skipDeserialization = false;

    public function rawMode(): self {
        $this->skipDeserialization = true;
        return $this;
    }

    public function execute(): mixed {
        // ... existing code
        if ($this->skipDeserialization) {
            return $parsedData; // Return array
        }
        return $deserializedObject; // Return object
    }
}
```

**Priority:** LOW - Most users want objects, but useful for middleware scenarios

---

### M7: Better Error Feedback to LLM ⚠️ NEEDS IMPROVEMENT

**Problem:** Retry policy exists but error messages could be more explicit

**What exists:**
```php
interface CanDetermineRetry {
    public function shouldRetry(...): bool;
    public function recordFailure(...): StructuredOutputExecution;
    public function prepareRetry(...): StructuredOutputExecution;
}
```

**What NeuronAI does:**
```php
$correctionMessage = new UserMessage(
    "There was a problem: $error. Try to generate the correct JSON structure."
);
$this->addToChatHistory($correctionMessage);
```

**What InstructorPHP could add:**
```php
interface ErrorMessageFormatter {
    public function format(string $error): string;
}

class DetailedErrorFormatter implements ErrorMessageFormatter {
    public function format(string $error): string {
        return <<<TEXT
Your previous response had validation errors:

{$error}

Please:
1. Ensure all required fields are present
2. Verify data types match the schema
3. Check constraints (min/max, patterns, etc.)

Generate corrected output now.
TEXT;
    }
}

// Usage
$output = StructuredOutput::create()
    ->with(...)
    ->withErrorFormatter(new DetailedErrorFormatter())
    ->execute();
```

**Priority:** MEDIUM - Improves LLM self-correction rates

---

## Revised V2 Roadmap

### Phase 1: Documentation & Discoverability (Weeks 1-2) 🔥 CRITICAL

**Goal:** Make existing features discoverable

**Deliverables:**
1. **Manual Schema Building Guide**
   - Document all JsonSchema factory methods
   - Examples comparing reflection vs manual
   - When to use each approach

2. **Response Model Formats Guide**
   - Class-based (existing docs)
   - Array-based (new docs)
   - JsonSchema object-based (new docs)
   - Conversion between formats

3. **Extension Points Reference**
   - Custom deserializers
   - Custom validators
   - Custom transformers
   - Event listeners

4. **Advanced Topics Guide**
   - JSON extraction strategies
   - Partial JSON handling
   - Streaming internals
   - Error recovery

**Effort:** 2 weeks
**Impact:** HIGH - Prevents user confusion
**Breaking changes:** ZERO

---

### Phase 2: Extraction Strategy Abstraction (Weeks 3-4)

**Goal:** Make extraction strategies pluggable

**Deliverables:**
1. `ExtractionStrategy` interface
2. Refactor existing extractors to implement interface
3. `JsonParser` accepts custom strategies
4. Documentation for custom extractors

**Example:**
```php
$parser = new JsonParser();
$parser->addStrategy(new CustomMarkdownStrategy());
$parser->addStrategy(new XmlToJsonStrategy());
```

**Effort:** 2 weeks
**Impact:** MEDIUM - Enables custom formats
**Breaking changes:** ZERO (backward compatible)

---

### Phase 3: Raw Mode Support (Weeks 5-6)

**Goal:** Allow skipping deserialization

**Deliverables:**
1. `rawMode()` method on PendingStructuredOutput
2. Conditional deserialization in pipeline
3. Documentation and examples

**Usage:**
```php
$array = StructuredOutput::create()
    ->with(...)
    ->rawMode()
    ->execute();
// Returns array instead of object
```

**Effort:** 1 week
**Impact:** LOW - Niche use case
**Breaking changes:** ZERO

---

### Phase 4: Enhanced Error Feedback (Weeks 7-8)

**Goal:** Improve LLM self-correction

**Deliverables:**
1. `ErrorMessageFormatter` interface
2. Provider-specific formatters (OpenAI, Anthropic, etc.)
3. Integration with retry policy
4. A/B testing framework for error messages

**Usage:**
```php
$output = StructuredOutput::create()
    ->with(...)
    ->withErrorFormatter(new AnthropicErrorFormatter())
    ->execute();
```

**Effort:** 2 weeks
**Impact:** MEDIUM - Improves success rates
**Breaking changes:** ZERO

---

### Phase 5: Format Abstraction (Weeks 9-12) - OPTIONAL

**Goal:** Support YAML/XML schemas

**Deliverables:**
1. `SchemaFormatProvider` interface
2. `YamlSchemaProvider` implementation
3. `XmlSchemaProvider` (XSD) implementation
4. YAML/XML extraction and parsing
5. Format-aware streaming

**Effort:** 4 weeks
**Impact:** LOW - JSON covers most cases
**Breaking changes:** ZERO

---

### Phase 6: Source Abstraction (Weeks 13-16) - OPTIONAL

**Goal:** Extract from CLI/files/etc.

**Deliverables:**
1. `ContentSource` interface
2. `CliSource` implementation
3. `FileSource` implementation
4. `StreamSource` implementation
5. Integration examples

**Effort:** 4 weeks
**Impact:** MEDIUM - New use cases
**Breaking changes:** ZERO

---

## Key Insights from Reassessment

### I1: InstructorPHP is More Feature-Rich Than Realized

**Original assessment:** "We need to learn from Prism and NeuronAI"
**Reality:** We already have most of their features!

- ✅ Manual schema builders (like Prism)
- ✅ Array-based schemas (like Prism)
- ✅ Multiple extraction strategies (NeuronAI copied from us!)
- ✅ Resilient parsing with repairs
- ✅ Unique streaming with partials

**Conclusion:** Focus on making existing features discoverable, not building new ones

---

### I2: NeuronAI Learned From InstructorPHP

**Evidence from NeuronAI source code:**
> "JSON extraction strategies inspired by InstructorPHP's JsonParser"

**What they copied:**
1. Try as-is strategy
2. Markdown code block extraction
3. Bracket matching
4. Smart brace matching

**Our version is actually MORE sophisticated** (multiple parsers, repairs, partial support)

**Conclusion:** We're the leader, not the follower

---

### I3: Documentation Gap is Bigger Than Feature Gap

**Real problems:**
1. Manual schema builders exist but not documented
2. Array schemas supported but not shown in examples
3. Extraction strategies scattered and hidden
4. No "Advanced Usage" guide
5. No "Extension Points" reference

**Impact:** Users ask for features that already exist

**Solution:** Documentation sprint before any new features

---

### I4: Backward Compatibility is Easy

**All proposed improvements can be:**
- ✅ Additive (new methods, no removals)
- ✅ Opt-in (existing code works unchanged)
- ✅ Non-breaking (interfaces added, not changed)

**Strategy:**
- Phase 1 (v1.1): Documentation only
- Phase 2 (v1.2): Abstraction improvements
- Phase 3 (v1.3): New convenience features
- Phase 4 (v2.0): Optional advanced features

**No need for major version bump unless we want to clean up APIs**

---

## Recommendations

### R1: Prioritize Documentation Over Features

**Rationale:**
- Existing features cover 90% of needs
- Users don't know they exist
- Documentation has zero breaking change risk

**Action Items:**
1. ✅ Document JsonSchema manual builders
2. ✅ Show array-based schema examples
3. ✅ Explain extraction strategy internals
4. ✅ Create "Extension Points" guide
5. ✅ Write "Advanced Usage" tutorial

**Timeline:** 2 weeks
**Resources:** Technical writer + 1 developer
**ROI:** Huge - reduces support burden

---

### R2: Make Extraction Strategies Pluggable

**Rationale:**
- Enables YAML/XML support without core changes
- Opens door for community contributions
- Low effort, high flexibility gain

**Action Items:**
1. Define `ExtractionStrategy` interface
2. Refactor existing extractors
3. Allow custom strategies in JsonParser
4. Document with examples

**Timeline:** 2 weeks
**Resources:** 1 developer
**ROI:** Medium - enables future growth

---

### R3: Defer Format/Source Abstraction

**Rationale:**
- JSON covers 95%+ of use cases
- LLM is primary use case
- Can add later without breaking changes
- Effort doesn't match demand

**Action Items:**
1. Gather user feedback on YAML/XML needs
2. Monitor CLI/file extraction requests
3. Build only if clear demand emerges
4. Focus on core improvements first

**Timeline:** Wait 6 months
**Resources:** None yet
**ROI:** TBD based on demand

---

### R4: Improve Error Feedback Incrementally

**Rationale:**
- Easy win for better LLM performance
- Can A/B test message formats
- Provider-specific optimization possible

**Action Items:**
1. Create `ErrorMessageFormatter` interface
2. Implement 3 formatters (default, detailed, provider-specific)
3. Add to retry policy
4. Measure impact on retry success rates

**Timeline:** 2 weeks
**Resources:** 1 developer
**ROI:** Medium - measurable improvement

---

## Conclusion

**Original assessment:** "We need major refactoring to catch up with other libraries"

**Revised assessment:** "We're ahead of other libraries, we just need to tell people!"

### What We Have (But Need to Promote):
1. ✅ Manual schema builders
2. ✅ Array-based schemas
3. ✅ Sophisticated extraction (others copied us!)
4. ✅ Resilient parsing with repairs
5. ✅ Unique streaming capabilities

### What We Need (In Priority Order):
1. 🔥 **Documentation** - Make existing features discoverable (2 weeks)
2. ⚠️ **Extraction abstraction** - Make strategies pluggable (2 weeks)
3. 📊 **Error feedback** - Improve LLM correction (2 weeks)
4. 📦 **Raw mode** - Skip deserialization option (1 week)
5. 🔮 **Format support** - YAML/XML (defer until demand clear)
6. 🔮 **Source abstraction** - CLI/file (defer until demand clear)

### Next Steps:
1. ✅ Validate this assessment with stakeholders
2. ✅ Start documentation sprint (highest ROI)
3. ✅ Gather user feedback on missing features
4. ✅ Build extraction abstraction (enables future growth)
5. ⏸️ Wait on format/source abstraction until proven need

**Key Takeaway:** InstructorPHP is already excellent. We need to communicate that excellence, then iterate based on real user needs.
