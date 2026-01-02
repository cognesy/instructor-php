# InstructorPHP V2: Corrected Assessment - What We ACTUALLY Have

**Date:** 2026-01-02
**Status:** Final corrected assessment after thorough verification
**Purpose:** Document what actually exists vs. what we initially claimed

---

## Summary of Corrections

After verifying tests, docs, and examples, here's what we ACTUALLY have:

| Feature | Initial Claim | Reality | Evidence |
|---------|---------------|---------|----------|
| Manual schema builders | ✅ Exists | ✅ **CONFIRMED** | JsonSchema::object(), ::string(), etc. |
| Array-based schemas | ✅ Exists | ⚠️ **PARTIAL** | Arrays accepted but with `x-php-class` metadata |
| Returns raw arrays | ❌ Doesn't exist | ❌ **CONFIRMED MISSING** | No tests, docs, or examples |
| Extraction strategies | ✅ Exists | ✅ **CONFIRMED** | JsonParser has 4 extractors + 3 parsers |
| Resilient parsing | ✅ Exists | ✅ **CONFIRMED** | ResilientJson with repairs |

---

## D1: Manual Schema Builders ✅ CONFIRMED

**Location:** `packages/utils/src/JsonSchema/JsonSchema.php`

**Verified Evidence:**
- ✅ Factory methods exist
- ✅ Documented in code comments
- ❌ NOT in user-facing docs
- ❌ NO examples

**Available methods:**
```php
JsonSchema::object(name: 'User', properties: [...], requiredProperties: [...])
JsonSchema::string(name: 'name', description: '...')
JsonSchema::number(name: 'age')
JsonSchema::integer(name: 'count')
JsonSchema::boolean(name: 'active')
JsonSchema::enum(name: 'role', enumValues: [...])
JsonSchema::array(name: 'items', itemSchema: ...)
JsonSchema::collection(name: 'list', itemSchema: ...)
JsonSchema::fromArray([...]) // Build from array
```

**Conclusion:** Feature exists but is UNDOCUMENTED. Priority: Add to user docs.

---

## D2: Array-Based Response Models ⚠️ PARTIAL SUPPORT

**Location:** `packages/instructor/src/StructuredOutput.php:89,105`

**What the signature says:**
```php
public function with(
    string|array|object|null $responseModel = null,  // ← Accepts arrays
    // ...
)
```

**What actually happens:**

### Test Evidence (`ResponseModelTest.php:55-78`)

```php
it('can handle array schema', function($user) {
    // Input: array schema with 'x-php-class' metadata
    $user = [
        'x-php-class' => 'Cognesy\Instructor\Tests\Examples\ResponseModel\User',
        'type' => 'object',
        'properties' => [
            'name' => ['type' => 'string'],
            'email' => ['type' => 'string'],
        ],
        "required" => ['name', 'email']
    ];

    $responseModel = $responseModelFactory->fromAny($user);

    // Output: STILL returns User object, NOT array!
    expect($responseModel->instanceClass())->toBe(User::class);
    expect($responseModel->instance())->toBeInstanceOf(User::class);
});
```

### Documentation Evidence (`docs/internals/response_models.md:25-34`)

> If `array` value is provided, it is considered a raw JSON Schema...
>
> **Instructor requires information on the class** of each nested object in your JSON Schema, so it can correctly deserialize the data into appropriate type.
>
> Current design uses JSON Schema **`$comment` field** on property to overcome this information gap. Instructor expects developer to use `$comment` field to provide fully qualified name of the target class...

**KEY INSIGHT:** Arrays are accepted as INPUT (schema specification), but OUTPUT is ALWAYS an object!

**Conclusion:**
- ✅ Arrays work as schema INPUT
- ❌ Arrays are NOT returned as OUTPUT
- Arrays must include `x-php-class` or `$comment` metadata
- Still deserializes to objects

---

## D3: Structure - Dynamic Objects, Not Arrays ⚠️ CLARIFICATION

**Location:** `packages/dynamic/src/Structure.php`

**What the docs say (`examples/A02_Advanced/Structures/run.php:14-15`):**

> If `Structure` instance has been provided as a response model, Instructor **returns an array in the shape you defined**.

**What actually happens:**

```php
$structure = Structure::define('person', [
    Field::string('name','Name of the person'),
    Field::int('age', 'Age of the person'),
]);

$person = (new StructuredOutput)->with(
    messages: $text,
    responseModel: $structure,
)->get();

// Access as object properties (NOT array keys!)
print($person->name);        // ← Object property access
print($person->age);         // ← Object property access
print($person->address->city); // ← Nested object access
```

**What Structure actually is:**
```php
class Structure implements
    CanProvideSchema,
    CanDeserializeSelf,
    CanValidateSelf,
    CanTransformSelf
{
    use HandlesFieldAccess;  // ← Magic __get/__set for property access
    // ...
}
```

**Conclusion:**
- `Structure` is a **dynamic object** with magic property access
- NOT a raw array
- Documentation is misleading - should say "returns a Structure object"

---

## D4: Extraction Strategies ✅ CONFIRMED

**Location:** `packages/utils/src/Json/JsonParser.php`

**Verified:**
```php
class JsonParser {
    public function findCompleteJson(string $input): string {
        $extractors = [
            fn($text) => [$text],                          // 1. Try as-is
            fn($text) => $this->findByMarkdown($text),     // 2. Markdown ```json
            fn($text) => [$this->findByBrackets($text)],   // 3. First { to last }
            fn($text) => $this->findJSONLikeStrings($text),// 4. Smart brace matching
        ];
        // Fallback chain...
    }
}
```

**Plus parsing strategies:**
```php
private function tryParse(string $maybeJson): mixed {
    $parsers = [
        fn($json) => json_decode($json, true, 512, JSON_THROW_ON_ERROR),
        fn($json) => (new ResilientJsonParser($json))->parse(),
        fn($json) => (new PartialJsonParser)->parse($json),
    ];
    // Try each...
}
```

**And resilient repairs:**
```php
// ResilientJson.php
private static function repairCommonIssues(string $s): string {
    // - Balance unescaped quotes
    // - Remove trailing commas
    // - Balance braces/brackets
}
```

**Conclusion:** ✅ Sophisticated, confirmed to exist

---

## What's ACTUALLY Missing

### M1: Raw Mode - Return Arrays Instead of Objects ❌ MISSING

**Problem:** No way to get raw associative arrays instead of deserialized objects

**Evidence:**
- ❌ No tests for raw array output
- ❌ No docs mentioning raw mode
- ❌ No examples returning arrays
- ❌ No `->rawMode()` or similar method

**What Prism does:**
```php
$response = Prism::structured()
    ->using(...)
    ->asStructured();

$data = $response->structured;  // ['name' => 'John', 'age' => 30]
// Developer manually creates objects if needed
$user = new User($data['name'], $data['age']);
```

**What InstructorPHP does:**
```php
$user = StructuredOutput::create()
    ->with(responseModel: User::class, ...)
    ->get();  // User object (ALWAYS)

// No way to get: ['name' => 'John', 'age' => 30]
```

**Why this matters:**
1. **Middleware processing** - Transform data before object creation
2. **Conditional deserialization** - Inspect data, then choose class
3. **Array manipulation** - Easier to manipulate arrays than objects
4. **Debugging** - Easier to inspect raw arrays
5. **Flexibility** - Developer controls when/how to deserialize

**How to implement:**
```php
// Option 1: Add rawMode() method
$data = StructuredOutput::create()
    ->with(responseModel: User::class, ...)
    ->rawMode()  // ← Skip deserialization
    ->get();
// Returns: ['name' => 'John', 'age' => 30]

// Option 2: Add getArray() method
$data = StructuredOutput::create()
    ->with(responseModel: User::class, ...)
    ->getArray();  // ← Returns array instead of object

// Option 3: Return both
$response = StructuredOutput::create()
    ->with(responseModel: User::class, ...)
    ->execute();

$array = $response->toArray();   // Raw data
$object = $response->toObject(); // Deserialized
```

**Priority:** MEDIUM - Useful but not critical

---

### M2: Feature Discoverability ❌ CRITICAL

**Verified gaps in documentation:**

1. **Manual Schema Builders**
   - ✅ Code exists
   - ❌ Not in user docs
   - ❌ No examples

2. **Array Schema Input**
   - ✅ Supported
   - ⚠️ Misleading docs (says "returns array" but doesn't)
   - ❌ No clear examples with `x-php-class` metadata

3. **Structure**
   - ✅ Documented in examples
   - ⚠️ Misleading description ("returns an array")
   - Should say: "returns a Structure object with dynamic properties"

4. **Extraction Strategies**
   - ✅ Code exists
   - ❌ Not documented
   - ❌ No examples
   - ❌ Can't customize without modifying core

**Priority:** 🔥 CRITICAL

---

### M3: Extraction Strategy Abstraction ❌ MISSING

**Problem:** Strategies are hardcoded, can't add custom extractors

**Current:**
```php
// Hardcoded in JsonParser
$extractors = [
    fn($text) => [$text],
    fn($text) => $this->findByMarkdown($text),
    // ... no way to add custom
];
```

**Needed:**
```php
interface ExtractionStrategy {
    public function extract(string $content): Result;
}

$parser = new JsonParser();
$parser->addStrategy(new CustomYamlStrategy());
$parser->addStrategy(new XmlToJsonStrategy());
```

**Priority:** MEDIUM

---

### M4: Better Error Feedback ⚠️ NEEDS IMPROVEMENT

**Exists:** Retry policy with validation errors
**Missing:** Explicit, provider-specific error messages to LLM

**Current:**
```php
// Errors are handled but not explicitly formatted for LLM
```

**Needed:**
```php
interface ErrorMessageFormatter {
    public function format(string $error): string;
}

$output = StructuredOutput::create()
    ->with(...)
    ->withErrorFormatter(new DetailedErrorFormatter())
    ->execute();
```

**Priority:** MEDIUM

---

## Corrected Priorities

### Phase 1: Documentation (2 weeks) 🔥 CRITICAL

**Fix misleading/missing docs:**

1. **Manual Schema Builders Guide**
   - All `JsonSchema::*()` factory methods
   - When to use vs. reflection
   - Complete examples

2. **Response Model Formats Guide**
   - Class-based (current docs OK)
   - Array-based (needs `x-php-class` examples)
   - Structure (fix "returns array" → "returns Structure object")
   - JsonSchema object-based (add examples)

3. **Clarify Array Behavior**
   - Arrays as INPUT (schema specification) ✅
   - Arrays as OUTPUT (NOT supported) ❌
   - Document this clearly

4. **Extraction Internals**
   - Document existing strategies
   - Show how they work
   - Explain when each is used

**Deliverables:**
- Updated `docs/internals/response_models.md`
- New `docs/advanced/manual_schemas.md`
- New `docs/advanced/extraction_strategies.md`
- Fix `examples/A02_Advanced/Structures/run.php` description

---

### Phase 2: Raw Mode (1 week) 📦 NEW FEATURE

**Add ability to skip deserialization:**

```php
interface PendingStructuredOutput {
    public function rawMode(): self;
    public function getArray(): array;
}

// Usage
$array = StructuredOutput::create()
    ->with(responseModel: User::class, ...)
    ->rawMode()
    ->get();  // Returns: ['name' => 'John', 'age' => 30]
```

**Implementation:**
1. Add `$skipDeserialization` flag to PendingStructuredOutput
2. Modify response pipeline to skip deserialization when flag set
3. Return parsed array directly
4. Add tests
5. Document

**Priority:** MEDIUM (solves Prism use case)

---

### Phase 3: Extraction Abstraction (2 weeks) ⚠️ IMPROVEMENT

**Make strategies pluggable:**

```php
interface ExtractionStrategy {
    public function extract(string $content): Result;
}

class JsonParser {
    private array $strategies = [];

    public function addStrategy(ExtractionStrategy $strategy): self {
        $this->strategies[] = $strategy;
        return $this;
    }
}
```

**Priority:** MEDIUM

---

### Phase 4: Error Feedback (2 weeks) 📊 IMPROVEMENT

**Explicit error messages:**

```php
interface ErrorMessageFormatter {
    public function format(string $error): string;
}

$output = StructuredOutput::create()
    ->withErrorFormatter(new AnthropicErrorFormatter())
    ->execute();
```

**Priority:** MEDIUM

---

### Phases 5-6: DEFERRED

- Format abstraction (YAML/XML) - Low priority
- Source abstraction (CLI/file) - Low priority

Wait for user demand before building.

---

## Final Corrected Summary

| Feature | Status | Action Needed |
|---------|--------|---------------|
| **Manual schema builders** | ✅ Exists | Document & add examples |
| **Array schema input** | ✅ Exists | Fix misleading docs, add examples |
| **Array output (raw mode)** | ❌ Missing | Implement in Phase 2 |
| **Structure** | ✅ Exists | Fix description, not arrays |
| **Extraction strategies** | ✅ Exists | Document internals |
| **Extraction abstraction** | ❌ Missing | Implement in Phase 3 |
| **Resilient parsing** | ✅ Exists | Document how it works |
| **Error feedback** | ⚠️ Partial | Improve in Phase 4 |

**Key Takeaways:**

1. **We have MORE than we thought** in terms of internal capabilities
2. **We have LESS than we thought** in terms of user-facing features
3. **Documentation is the biggest gap** - features exist but hidden
4. **Raw mode is the biggest missing feature** - no way to get arrays instead of objects
5. **NeuronAI copied FROM us** (extraction strategies) - we're ahead, not behind

**Recommendation:** Start with Phase 1 (documentation) before building new features. Many "missing" features actually exist.
