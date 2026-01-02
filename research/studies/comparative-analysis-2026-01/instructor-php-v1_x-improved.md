# InstructorPHP v1.x: Pragmatic Improvements Plan

**Date:** 2026-01-02
**Target:** v1.2, v1.3, v1.4 releases
**Constraints:** Backward compatible, no breaking changes, relatively easy to implement
**Purpose:** Quick wins before v2.0 deep restructuring

---

## Philosophy: High Value, Low Effort

**Criteria for v1.x improvements:**
- ✅ Backward compatible (existing code works unchanged)
- ✅ Additive only (new methods, no modifications to existing APIs)
- ✅ Can be implemented in 1-2 weeks per feature
- ✅ Addresses real user pain points
- ✅ No architectural changes required

**Defer to v2.0:**
- ❌ Format abstraction (YAML/XML) - requires new architecture
- ❌ Source abstraction (CLI/file) - requires pipeline changes
- ❌ Event system overhaul - architectural change
- ❌ Pipeline restructuring - breaking changes

---

## v1.2: Documentation Sprint (Week 1-2) 🔥 CRITICAL

**Goal:** Make existing features discoverable without changing code

**Effort:** 2 weeks
**Value:** 🔥 CRITICAL - Prevents user confusion
**Risk:** ZERO - No code changes

### D1: Fix Misleading Documentation

**Problem:** Structure docs say "returns an array" but returns object

**Location:** `examples/A02_Advanced/Structures/run.php:14-15`

**Current text:**
> If `Structure` instance has been provided as a response model, Instructor returns an array in the shape you defined.

**Corrected text:**
> If `Structure` instance has been provided as a response model, Instructor returns a `Structure` object with dynamic properties matching the shape you defined.

**Example addition:**
```php
// Access properties dynamically
$person = (new StructuredOutput)->with(
    messages: $text,
    responseModel: $structure,
)->get();

// Returns Structure object (NOT array)
print($person->name);         // ✅ Dynamic property access
print($person->address->city); // ✅ Nested objects
print($person['name']);        // ❌ NOT array access

// To convert to array (if needed):
$array = $person->toArray();
```

**Files to update:**
- `examples/A02_Advanced/Structures/run.php`
- `docs/advanced/structures.md`

---

### D2: Document Manual Schema Builders

**Problem:** `JsonSchema` builders exist but undocumented

**New file:** `docs/advanced/manual_schemas.md`

```markdown
# Manual Schema Building

While InstructorPHP can automatically generate schemas from PHP classes via reflection,
you can also build schemas manually using the `JsonSchema` API.

## When to Use Manual Schemas

- **Fine-grained control** over exact JSON Schema output
- **Dynamic schemas** where structure is determined at runtime
- **Provider optimization** when you need to tweak schemas for specific LLMs
- **Legacy integration** when working with existing JSON Schema specifications

## Available Builder Methods

### Object Schemas

```php
use Cognesy\Utils\JsonSchema\JsonSchema;

$schema = JsonSchema::object(
    name: 'User',
    description: 'User data',
    properties: [
        JsonSchema::string(name: 'name', description: 'User name'),
        JsonSchema::integer(name: 'age', description: 'User age'),
        JsonSchema::boolean(name: 'active', description: 'Is active'),
    ],
    requiredProperties: ['name', 'age'],
    additionalProperties: false,
);
```

### Primitive Schemas

```php
// String
JsonSchema::string(
    name: 'email',
    description: 'Email address',
);

// Integer
JsonSchema::integer(
    name: 'count',
    description: 'Number of items',
);

// Number (float)
JsonSchema::number(
    name: 'price',
    description: 'Product price',
);

// Boolean
JsonSchema::boolean(
    name: 'verified',
    description: 'Is verified',
);
```

### Array Schemas

```php
// Array with item schema
JsonSchema::array(
    name: 'tags',
    itemSchema: JsonSchema::string(),
    description: 'List of tags',
);

// Collection (alias for array)
JsonSchema::collection(
    name: 'users',
    itemSchema: JsonSchema::object(...),
    description: 'List of users',
);
```

### Enum Schemas

```php
JsonSchema::enum(
    name: 'status',
    enumValues: ['pending', 'active', 'completed'],
    description: 'Order status',
);
```

### From Array

```php
$schema = JsonSchema::fromArray([
    'type' => 'object',
    'properties' => [
        'name' => ['type' => 'string'],
        'age' => ['type' => 'integer'],
    ],
    'required' => ['name'],
]);
```

## Fluent Builder Pattern

```php
$schema = JsonSchema::object('User')
    ->withProperty(JsonSchema::string('name'))
    ->withProperty(JsonSchema::integer('age'))
    ->withRequired(['name']);
```

## Using Manual Schemas with StructuredOutput

```php
use Cognesy\Instructor\StructuredOutput;

// Build schema manually
$userSchema = JsonSchema::object(
    name: 'User',
    properties: [
        JsonSchema::string(name: 'name'),
        JsonSchema::integer(name: 'age'),
    ],
    requiredProperties: ['name'],
);

// Use with StructuredOutput
$user = StructuredOutput::create()
    ->with(
        messages: 'Extract user: John Doe, 30 years old',
        responseModel: $userSchema,
    )
    ->get();
```

## Comparison: Reflection vs Manual

### Reflection (Automatic)

**Pros:**
- ✅ Concise - just use class name
- ✅ Single source of truth
- ✅ IDE support for refactoring
- ✅ Type-safe

**Cons:**
- ❌ Less control over schema details
- ❌ Performance overhead (reflection)

```php
class User {
    public function __construct(
        public string $name,
        public int $age,
    ) {}
}

$user = StructuredOutput::create()
    ->with(responseModel: User::class, ...)
    ->get();
```

### Manual (Explicit)

**Pros:**
- ✅ Full control over schema
- ✅ No reflection overhead
- ✅ Can optimize for specific providers
- ✅ Runtime schema generation

**Cons:**
- ❌ More verbose
- ❌ Duplication with class definition
- ❌ Manual maintenance

```php
$schema = JsonSchema::object('User', [
    JsonSchema::string('name'),
    JsonSchema::integer('age'),
], ['name', 'age']);

$user = StructuredOutput::create()
    ->with(responseModel: $schema, ...)
    ->get();
```

## Examples

See: `examples/A05_Extras/JsonSchema/run.php`
```

**Files to create:**
- `docs/advanced/manual_schemas.md`
- `examples/A02_Advanced/ManualSchemas/run.php`

---

### D3: Clarify Array Schema Behavior

**Problem:** Unclear that arrays work as INPUT but not OUTPUT

**Location:** `docs/internals/response_models.md:25-34`

**Add section:**
```markdown
## Array Schemas: Input vs Output

### Arrays as Schema INPUT ✅

You can provide an array as `responseModel` to specify the JSON Schema:

```php
$output = StructuredOutput::create()
    ->with(
        responseModel: [
            'x-php-class' => User::class,  // ← Required for deserialization
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
                'age' => ['type' => 'integer'],
            ],
            'required' => ['name'],
        ],
        // ...
    )
    ->get();
```

**Important:** The `x-php-class` field is REQUIRED for InstructorPHP to know which
class to deserialize the data into.

### Arrays as OUTPUT ❌

InstructorPHP does NOT return raw arrays. The output is ALWAYS an object:

```php
$user = StructuredOutput::create()
    ->with(responseModel: User::class, ...)
    ->get();

// Result is User object (not array)
$user->name;  // ✅ Works
$user['name']; // ❌ Error - not an array

// To get array representation (if needed):
$array = json_decode(json_encode($user), true);
```

If you need raw arrays instead of objects, see: [Raw Mode](#raw-mode) (v1.3+)
```

**Files to update:**
- `docs/internals/response_models.md`

---

### D4: Document Extraction Strategies

**New file:** `docs/advanced/json_extraction.md`

```markdown
# JSON Extraction Strategies

InstructorPHP uses multiple strategies to extract JSON from LLM responses,
handling various edge cases where the LLM might return JSON wrapped in
markdown, text, or malformed.

## Extraction Pipeline

When processing an LLM response, InstructorPHP tries multiple extraction
strategies in order:

### 1. Direct Parsing (Try As-Is)

Attempts to parse the response directly as JSON:

```php
// LLM returns clean JSON
{"name": "John", "age": 30}

// ✅ Parsed successfully
```

### 2. Markdown Code Block Extraction

Extracts JSON from markdown fenced code blocks:

```php
// LLM wraps JSON in markdown
Here's the data you requested:

\`\`\`json
{"name": "John", "age": 30}
\`\`\`

// ✅ Extracts content between \`\`\`json and \`\`\`
```

### 3. Bracket Matching

Finds first `{` and last `}` to extract JSON:

```php
// LLM adds explanatory text
The user data is {"name": "John", "age": 30} as extracted from the text.

// ✅ Extracts from first { to last }
```

### 4. Smart Brace Matching

Handles nested braces and escaped quotes:

```php
// LLM returns nested JSON with escaped quotes
Here is {"user": {"name": "John \"The Great\"", "age": 30}} extracted.

// ✅ Correctly handles:
//    - Nested braces
//    - Escaped quotes
//    - String boundaries
```

## Parsing Strategies

After extraction, multiple parsers attempt to handle malformed JSON:

### 1. Standard JSON Parser

Native `json_decode` with strict error handling.

### 2. Resilient Parser

Applies automatic repairs before parsing:

- **Balance quotes** - Adds missing closing quotes
- **Remove trailing commas** - Fixes `{"a": 1,}`
- **Balance braces** - Adds missing `}` or `]`

```php
// Malformed JSON
{"name": "John", "age": 30

// Resilient parser repairs:
{"name": "John", "age": 30}  // ✅ Added missing }
```

### 3. Partial JSON Parser

Handles incomplete JSON during streaming:

```php
// Partial JSON from streaming
{"name": "John", "age":

// ✅ Completes to:
{"name": "John", "age": null}
```

## Implementation Details

**Location:** `packages/utils/src/Json/JsonParser.php`

```php
class JsonParser {
    public function findCompleteJson(string $input): string {
        $extractors = [
            fn($text) => [$text],                          // Direct
            fn($text) => $this->findByMarkdown($text),     // Markdown
            fn($text) => [$this->findByBrackets($text)],   // Brackets
            fn($text) => $this->findJSONLikeStrings($text),// Smart braces
        ];

        foreach ($extractors as $extractor) {
            foreach ($extractor($input) as $candidate) {
                if ($parsed = $this->tryParse($candidate)) {
                    return json_encode($parsed);
                }
            }
        }

        return '';
    }
}
```

## Why This Matters

LLMs don't always return clean JSON:

- **Claude** sometimes wraps in markdown
- **GPT-4** may add explanations
- **Gemini** might include partial responses during streaming
- **Custom prompts** can lead to unexpected formats

InstructorPHP's multi-strategy approach ensures maximum compatibility.

## Debugging Extraction Issues

If extraction fails, enable debug logging to see which strategies were tried:

```php
// TODO: Add debug configuration example
```

## Future: Custom Extractors (v1.3+)

See: [Pluggable Extractors](#pluggable-extractors) for adding custom strategies.
```

**Files to create:**
- `docs/advanced/json_extraction.md`

---

### D5: Summary of Documentation Changes

**New files:**
1. `docs/advanced/manual_schemas.md`
2. `docs/advanced/json_extraction.md`
3. `examples/A02_Advanced/ManualSchemas/run.php`

**Updated files:**
1. `docs/internals/response_models.md` - Clarify array behavior
2. `docs/advanced/structures.md` - Fix "returns array" to "returns object"
3. `examples/A02_Advanced/Structures/run.php` - Fix description

**Deliverables:**
- ✅ 2 new comprehensive guides
- ✅ 1 new example
- ✅ 3 documentation fixes
- ✅ Zero code changes
- ✅ Zero breaking changes

**Timeline:** 2 weeks (1 writer + 1 reviewer)

---

## v1.3: Raw Mode (Week 3) 📦 NEW FEATURE

**Goal:** Add ability to return arrays instead of objects

**Effort:** 1 week (1 developer)
**Value:** HIGH - Addresses Prism use case
**Risk:** LOW - Purely additive

### Implementation Plan

**Step 1: Add flag to PendingStructuredOutput**

**Location:** `packages/instructor/src/PendingStructuredOutput.php`

```php
class PendingStructuredOutput
{
    private bool $skipDeserialization = false;

    /**
     * Return raw array instead of deserialized object
     *
     * @return $this
     */
    public function rawMode(): self {
        $this->skipDeserialization = true;
        return $this;
    }

    /**
     * Alias for rawMode() for clarity
     *
     * @return $this
     */
    public function asArray(): self {
        return $this->rawMode();
    }
}
```

**Step 2: Modify response handling**

**Location:** Response processing in executor

```php
// In response generation
if ($this->skipDeserialization) {
    // Return parsed JSON as array
    return $parsedData;
}

// Normal path - deserialize to object
return $this->deserializer->deserialize($parsedData, $responseModel);
```

**Step 3: Update return type**

```php
/**
 * Execute and return result
 *
 * @return TResponse|array Returns object by default, array if rawMode() used
 */
public function get(): mixed {
    // ...
}
```

### Usage Examples

**Basic usage:**
```php
// Normal mode - returns User object
$user = StructuredOutput::create()
    ->with(responseModel: User::class, ...)
    ->get();
// Type: User

// Raw mode - returns array
$data = StructuredOutput::create()
    ->with(responseModel: User::class, ...)
    ->rawMode()
    ->get();
// Type: array = ['name' => 'John', 'age' => 30]
```

**Use cases:**

1. **Middleware processing:**
```php
$data = StructuredOutput::create()
    ->with(responseModel: User::class, ...)
    ->rawMode()
    ->get();

// Inspect data
if ($data['age'] < 18) {
    // Use different class
    $user = new MinorUser(...$data);
} else {
    $user = new User(...$data);
}
```

2. **Array manipulation:**
```php
$data = StructuredOutput::create()
    ->with(responseModel: User::class, ...)
    ->rawMode()
    ->get();

// Add computed fields
$data['full_name'] = $data['first_name'] . ' ' . $data['last_name'];

// Then deserialize
$user = new User(...$data);
```

3. **Debugging:**
```php
$data = StructuredOutput::create()
    ->with(responseModel: User::class, ...)
    ->rawMode()
    ->get();

dump($data); // Easier to inspect arrays than objects
```

### Testing

**New test file:** `tests/Feature/RawModeTest.php`

```php
it('returns array when rawMode is enabled', function() {
    $data = StructuredOutput::create()
        ->with(
            messages: 'Extract user: John Doe, 30 years old',
            responseModel: User::class,
        )
        ->rawMode()
        ->get();

    expect($data)->toBeArray();
    expect($data)->toHaveKey('name');
    expect($data)->toHaveKey('age');
    expect($data['name'])->toBe('John Doe');
    expect($data['age'])->toBe(30);
});

it('returns object when rawMode is not enabled', function() {
    $user = StructuredOutput::create()
        ->with(
            messages: 'Extract user: John Doe, 30 years old',
            responseModel: User::class,
        )
        ->get();

    expect($user)->toBeInstanceOf(User::class);
    expect($user->name)->toBe('John Doe');
    expect($user->age)->toBe(30);
});

it('works with asArray alias', function() {
    $data = StructuredOutput::create()
        ->with(
            messages: 'Extract user: John Doe, 30 years old',
            responseModel: User::class,
        )
        ->asArray()
        ->get();

    expect($data)->toBeArray();
});
```

### Documentation

**New section in:** `docs/essentials/usage.md`

```markdown
## Raw Mode: Getting Arrays Instead of Objects

By default, InstructorPHP deserializes LLM responses into PHP objects.
If you need raw associative arrays instead, use `rawMode()`:

```php
// Returns array
$data = StructuredOutput::create()
    ->with(responseModel: User::class, ...)
    ->rawMode()
    ->get();

// Or use asArray() alias
$data = StructuredOutput::create()
    ->with(responseModel: User::class, ...)
    ->asArray()
    ->get();
```

### When to Use Raw Mode

- **Middleware processing** - Inspect data before creating objects
- **Conditional deserialization** - Choose class based on data
- **Array manipulation** - Easier to modify arrays than objects
- **Debugging** - Arrays are easier to inspect with `dump()`
- **Legacy integration** - When you need arrays for existing code

### Important Notes

- Schema is still generated from the class
- Validation still runs on the data
- Only the final deserialization step is skipped
- Streaming with partials returns arrays of partial data
```

**New example:** `examples/A02_Advanced/RawMode/run.php`

### Deliverables

- ✅ `rawMode()` and `asArray()` methods
- ✅ Conditional deserialization logic
- ✅ 3 comprehensive tests
- ✅ Documentation section
- ✅ Working example
- ✅ Backward compatible (default behavior unchanged)

**Timeline:** 1 week

---

## v1.4: Pluggable Extraction (Week 4-5) ⚠️ REFACTOR

**Goal:** Make JSON extraction strategies pluggable

**Effort:** 2 weeks (1 developer)
**Value:** MEDIUM - Enables custom extractors
**Risk:** MEDIUM - Requires refactoring, but backward compatible

### Design

**Step 1: Create interface**

**New file:** `packages/utils/src/Json/Extraction/ExtractionStrategy.php`

```php
<?php

namespace Cognesy\Utils\Json\Extraction;

use Cognesy\Utils\Result\Result;

/**
 * Strategy for extracting JSON from mixed content
 */
interface ExtractionStrategy
{
    /**
     * Attempt to extract JSON from content
     *
     * @param string $content Raw content potentially containing JSON
     * @return Result<string> Success with extracted JSON or failure
     */
    public function extract(string $content): Result;

    /**
     * Name of this strategy (for debugging)
     *
     * @return string
     */
    public function name(): string;

    /**
     * Priority (higher runs first)
     *
     * @return int
     */
    public function priority(): int;
}
```

**Step 2: Extract existing strategies**

**New implementations:**

```php
// DirectJsonStrategy.php
class DirectJsonStrategy implements ExtractionStrategy
{
    public function extract(string $content): Result {
        try {
            json_decode($content, flags: JSON_THROW_ON_ERROR);
            return Result::success($content);
        } catch (\JsonException) {
            return Result::failure('Not valid JSON');
        }
    }

    public function name(): string {
        return 'direct';
    }

    public function priority(): int {
        return 100; // Highest priority - try first
    }
}

// MarkdownJsonStrategy.php
class MarkdownJsonStrategy implements ExtractionStrategy
{
    public function extract(string $content): Result {
        $pattern = '/^```(?:json)?\s*\n?(.*?)\n?```$/s';

        if (preg_match($pattern, trim($content), $matches)) {
            $json = trim($matches[1]);

            try {
                json_decode($json, flags: JSON_THROW_ON_ERROR);
                return Result::success($json);
            } catch (\JsonException) {
                return Result::failure('Invalid JSON in code block');
            }
        }

        return Result::failure('No markdown code block found');
    }

    public function name(): string {
        return 'markdown';
    }

    public function priority(): int {
        return 90;
    }
}

// BracketMatchingStrategy.php
class BracketMatchingStrategy implements ExtractionStrategy
{
    public function extract(string $content): Result {
        $trimmed = trim($content);
        $firstOpen = strpos($trimmed, '{');
        $lastClose = strrpos($trimmed, '}');

        if ($firstOpen === false || $lastClose === false || $lastClose < $firstOpen) {
            return Result::failure('No braces found');
        }

        $json = substr($trimmed, $firstOpen, $lastClose - $firstOpen + 1);

        try {
            json_decode($json, flags: JSON_THROW_ON_ERROR);
            return Result::success($json);
        } catch (\JsonException) {
            return Result::failure('Invalid JSON between braces');
        }
    }

    public function name(): string {
        return 'brackets';
    }

    public function priority(): int {
        return 80;
    }
}

// SmartBraceMatchingStrategy.php
class SmartBraceMatchingStrategy implements ExtractionStrategy
{
    public function extract(string $content): Result {
        // Existing findJSONLikeStrings logic
        // Returns first valid JSON with smart brace/quote handling
    }

    public function name(): string {
        return 'smart_braces';
    }

    public function priority(): int {
        return 70;
    }
}
```

**Step 3: Refactor JsonParser**

```php
class JsonParser
{
    /** @var array<ExtractionStrategy> */
    private array $strategies = [];

    public function __construct(array $strategies = null)
    {
        $this->strategies = $strategies ?? $this->defaultStrategies();
        $this->sortStrategiesByPriority();
    }

    /**
     * Add custom extraction strategy
     *
     * @param ExtractionStrategy $strategy
     * @return $this
     */
    public function addStrategy(ExtractionStrategy $strategy): self {
        $this->strategies[] = $strategy;
        $this->sortStrategiesByPriority();
        return $this;
    }

    /**
     * Remove strategy by name
     *
     * @param string $name
     * @return $this
     */
    public function removeStrategy(string $name): self {
        $this->strategies = array_filter(
            $this->strategies,
            fn($s) => $s->name() !== $name
        );
        return $this;
    }

    public function findCompleteJson(string $input): string {
        foreach ($this->strategies as $strategy) {
            $result = $strategy->extract($input);

            if ($result->isSuccess()) {
                $json = $result->unwrap();

                // Try parsing with all parsers
                $data = $this->tryParse($json);
                if ($data !== null) {
                    return json_encode($data);
                }
            }
        }

        return '';
    }

    private function defaultStrategies(): array {
        return [
            new DirectJsonStrategy(),
            new MarkdownJsonStrategy(),
            new BracketMatchingStrategy(),
            new SmartBraceMatchingStrategy(),
        ];
    }

    private function sortStrategiesByPriority(): void {
        usort($this->strategies, fn($a, $b) => $b->priority() <=> $a->priority());
    }

    // Existing tryParse, findPartialJson methods remain...
}
```

### Custom Strategy Example

**User can now create custom extractors:**

```php
use Cognesy\Utils\Json\Extraction\ExtractionStrategy;
use Cognesy\Utils\Result\Result;

/**
 * Extract JSON from XML CDATA sections
 */
class XmlCdataJsonStrategy implements ExtractionStrategy
{
    public function extract(string $content): Result {
        // Extract from <![CDATA[...]]>
        if (preg_match('/<!\[CDATA\[(.*?)\]\]>/s', $content, $matches)) {
            $json = trim($matches[1]);

            try {
                json_decode($json, flags: JSON_THROW_ON_ERROR);
                return Result::success($json);
            } catch (\JsonException) {
                return Result::failure('Invalid JSON in CDATA');
            }
        }

        return Result::failure('No CDATA found');
    }

    public function name(): string {
        return 'xml_cdata';
    }

    public function priority(): int {
        return 85; // Between markdown and brackets
    }
}

// Usage
$parser = new JsonParser();
$parser->addStrategy(new XmlCdataJsonStrategy());

$json = $parser->findCompleteJson($response);
```

### Integration with StructuredOutput

**Allow custom parser configuration:**

```php
// In StructuredOutput or config
$output = StructuredOutput::create()
    ->with(...)
    ->withJsonParser(
        new JsonParser([
            new CustomYamlStrategy(),
            new DirectJsonStrategy(),
        ])
    )
    ->get();
```

### Testing

```php
it('uses default strategies', function() {
    $parser = new JsonParser();

    $json = $parser->findCompleteJson('```json
    {"name": "John"}
    ```');

    expect($json)->toBe('{"name":"John"}');
});

it('can add custom strategy', function() {
    $parser = new JsonParser();
    $parser->addStrategy(new XmlCdataJsonStrategy());

    $json = $parser->findCompleteJson('<![CDATA[{"name":"John"}]]>');

    expect($json)->toBe('{"name":"John"}');
});

it('respects priority order', function() {
    $parser = new JsonParser([
        new LowPriorityStrategy(),
        new HighPriorityStrategy(),
    ]);

    // HighPriorityStrategy should be tried first
});

it('can remove default strategy', function() {
    $parser = new JsonParser();
    $parser->removeStrategy('markdown');

    // Markdown extraction should now fail
});
```

### Documentation

**New file:** `docs/advanced/custom_extractors.md`

```markdown
# Custom JSON Extractors

InstructorPHP allows you to add custom JSON extraction strategies for
handling non-standard response formats.

## Creating a Custom Extractor

Implement the `ExtractionStrategy` interface:

```php
use Cognesy\Utils\Json\Extraction\ExtractionStrategy;
use Cognesy\Utils\Result\Result;

class MyCustomStrategy implements ExtractionStrategy
{
    public function extract(string $content): Result {
        // Your extraction logic

        if ($extracted = $this->myExtractionLogic($content)) {
            return Result::success($extracted);
        }

        return Result::failure('Could not extract');
    }

    public function name(): string {
        return 'my_custom';
    }

    public function priority(): int {
        return 50; // 0-100, higher = earlier
    }
}
```

## Adding Custom Strategies

```php
$parser = new JsonParser();
$parser->addStrategy(new MyCustomStrategy());
```

## Examples

See: `examples/A02_Advanced/CustomExtractors/`
```

**New example:** `examples/A02_Advanced/CustomExtractors/run.php`

### Backward Compatibility

**CRITICAL:** Existing code must work unchanged:

```php
// v1.3 and earlier - still works
$parser = new JsonParser();
$json = $parser->findCompleteJson($input);

// Uses default strategies automatically
```

**Only new capability added:**
```php
// v1.4 - new capability
$parser = new JsonParser();
$parser->addStrategy(new CustomStrategy()); // ← NEW
```

### Deliverables

- ✅ `ExtractionStrategy` interface
- ✅ 4 default strategy implementations
- ✅ Refactored `JsonParser` with plugin support
- ✅ `addStrategy()` and `removeStrategy()` methods
- ✅ Priority-based ordering
- ✅ 5+ tests
- ✅ Documentation guide
- ✅ Working example
- ✅ Backward compatible

**Timeline:** 2 weeks

---

## v1.5: Enhanced Error Feedback (Week 6-7) 📊 IMPROVEMENT

**Goal:** Better error messages for LLM self-correction

**Effort:** 2 weeks (1 developer)
**Value:** MEDIUM - Improves retry success rate
**Risk:** LOW - Additive only

### Design

**Step 1: Create formatter interface**

**New file:** `packages/instructor/src/Retry/ErrorMessageFormatter.php`

```php
<?php

namespace Cognesy\Instructor\Retry;

/**
 * Formats validation errors for LLM feedback
 */
interface ErrorMessageFormatter
{
    /**
     * Format error for LLM consumption
     *
     * @param string $error Validation error message
     * @param array $context Additional context (provider, model, etc.)
     * @return string Formatted error message
     */
    public function format(string $error, array $context = []): string;
}
```

**Step 2: Implement formatters**

```php
// DefaultErrorMessageFormatter.php
class DefaultErrorMessageFormatter implements ErrorMessageFormatter
{
    public function format(string $error, array $context = []): string {
        return <<<TEXT
There was a problem with your previous response:

{$error}

Please generate the correct structured output.
TEXT;
    }
}

// DetailedErrorMessageFormatter.php
class DetailedErrorMessageFormatter implements ErrorMessageFormatter
{
    public function format(string $error, array $context = []): string {
        return <<<TEXT
Your previous response had validation errors:

{$error}

Please review the schema and correct the following:
1. Ensure all required fields are present
2. Verify data types match the schema
3. Check that values meet any constraints (min/max, patterns, etc.)

Generate the corrected structured output now.
TEXT;
    }
}

// ProviderSpecificErrorFormatter.php
class ProviderSpecificErrorFormatter implements ErrorMessageFormatter
{
    public function format(string $error, array $context = []): string {
        $provider = $context['provider'] ?? 'unknown';

        return match($provider) {
            'openai' => $this->formatForOpenAI($error),
            'anthropic' => $this->formatForAnthropic($error),
            'google' => $this->formatForGoogle($error),
            default => (new DefaultErrorMessageFormatter())->format($error),
        };
    }

    private function formatForOpenAI(string $error): string {
        return <<<TEXT
ERROR: Previous response validation failed.

{$error}

ACTION REQUIRED: Generate valid JSON matching the provided schema.
TEXT;
    }

    private function formatForAnthropic(string $error): string {
        return <<<TEXT
<error>
Your previous response had validation issues:

{$error}
</error>

<task>
Please review the schema and generate a corrected response.
</task>
TEXT;
    }

    private function formatForGoogle(string $error): string {
        return <<<TEXT
**Validation Error**

{$error}

**Action**: Provide corrected output matching the schema.
TEXT;
    }
}
```

**Step 3: Integrate with retry policy**

```php
// In RetryPolicy or similar
class RetryPolicy
{
    public function __construct(
        private ErrorMessageFormatter $formatter = null,
    ) {
        $this->formatter ??= new DefaultErrorMessageFormatter();
    }

    public function prepareRetry(
        StructuredOutputExecution $execution,
    ): StructuredOutputExecution {
        $lastError = $execution->lastError();

        // Format error for LLM
        $errorFeedback = $this->formatter->format($lastError, [
            'provider' => $execution->providerName(),
            'model' => $execution->modelName(),
            'attempt' => $execution->attemptNumber(),
        ]);

        // Add as user message
        return $execution->addMessage(new UserMessage($errorFeedback));
    }
}
```

**Step 4: Configuration**

```php
// Allow custom formatter
$output = StructuredOutput::create()
    ->with(...)
    ->withErrorFormatter(new DetailedErrorMessageFormatter())
    ->get();

// Or provider-specific
$output = StructuredOutput::create()
    ->with(...)
    ->withErrorFormatter(new ProviderSpecificErrorFormatter())
    ->get();
```

### Usage Examples

**1. Detailed errors:**
```php
$user = StructuredOutput::create()
    ->with(
        messages: 'Extract user data',
        responseModel: User::class,
    )
    ->withErrorFormatter(new DetailedErrorMessageFormatter())
    ->get();

// On validation failure, LLM receives:
// "Your previous response had validation errors:
//
//  Field 'age' must be positive number, got: -5
//
//  Please review the schema and correct the following:
//  1. Ensure all required fields are present
//  2. Verify data types match the schema
//  3. Check that values meet any constraints (min/max, patterns, etc.)
//
//  Generate the corrected structured output now."
```

**2. Provider-specific:**
```php
$user = StructuredOutput::create()
    ->using('anthropic', 'claude-3-5-sonnet-20241022')
    ->with(
        messages: 'Extract user data',
        responseModel: User::class,
    )
    ->withErrorFormatter(new ProviderSpecificErrorFormatter())
    ->get();

// On validation failure, LLM receives Claude-optimized format:
// "<error>
//  Your previous response had validation issues:
//
//  Field 'email' is not a valid email address
//  </error>
//
//  <task>
//  Please review the schema and generate a corrected response.
//  </task>"
```

**3. Custom formatter:**
```php
class MyCustomFormatter implements ErrorMessageFormatter {
    public function format(string $error, array $context = []): string {
        $attempt = $context['attempt'] ?? 1;

        return "ATTEMPT {$attempt} FAILED: {$error}\n\nTry again with corrections.";
    }
}

$user = StructuredOutput::create()
    ->with(...)
    ->withErrorFormatter(new MyCustomFormatter())
    ->get();
```

### Testing

```php
it('uses default formatter by default', function() {
    $formatter = new DefaultErrorMessageFormatter();
    $message = $formatter->format('Field X is invalid');

    expect($message)->toContain('There was a problem');
    expect($message)->toContain('Field X is invalid');
});

it('provides detailed error guidance', function() {
    $formatter = new DetailedErrorMessageFormatter();
    $message = $formatter->format('Age must be positive');

    expect($message)->toContain('validation errors');
    expect($message)->toContain('1. Ensure all required fields');
});

it('formats for specific provider', function() {
    $formatter = new ProviderSpecificErrorFormatter();

    $anthropicMessage = $formatter->format('Error', ['provider' => 'anthropic']);
    expect($anthropicMessage)->toContain('<error>');

    $openaiMessage = $formatter->format('Error', ['provider' => 'openai']);
    expect($openaiMessage)->toContain('ERROR:');
});

it('allows custom formatters', function() {
    $formatter = new class implements ErrorMessageFormatter {
        public function format(string $error, array $context = []): string {
            return "CUSTOM: {$error}";
        }
    };

    $message = $formatter->format('Test');
    expect($message)->toBe('CUSTOM: Test');
});
```

### Documentation

**New section in:** `docs/essentials/validation.md`

```markdown
## Error Message Formatting

When validation fails during retry, you can customize how error messages
are presented to the LLM for self-correction.

### Built-in Formatters

**Default (Simple):**
```php
$output = StructuredOutput::create()
    ->with(...)
    ->withErrorFormatter(new DefaultErrorMessageFormatter())
    ->get();
```

**Detailed (Helpful):**
```php
$output = StructuredOutput::create()
    ->with(...)
    ->withErrorFormatter(new DetailedErrorMessageFormatter())
    ->get();
```

**Provider-Specific (Optimized):**
```php
$output = StructuredOutput::create()
    ->with(...)
    ->withErrorFormatter(new ProviderSpecificErrorFormatter())
    ->get();
```

### Custom Formatters

Create your own:

```php
class MyFormatter implements ErrorMessageFormatter {
    public function format(string $error, array $context = []): string {
        return "Fix this: {$error}";
    }
}

$output = StructuredOutput::create()
    ->withErrorFormatter(new MyFormatter())
    ->get();
```
```

**New example:** `examples/A02_Advanced/ErrorFormatting/run.php`

### Deliverables

- ✅ `ErrorMessageFormatter` interface
- ✅ 3 built-in formatters (Default, Detailed, ProviderSpecific)
- ✅ Integration with retry policy
- ✅ Configuration methods
- ✅ 4+ tests
- ✅ Documentation
- ✅ Working example
- ✅ Backward compatible (default behavior unchanged)

**Timeline:** 2 weeks

---

## Release Timeline

| Version | Feature | Weeks | Cumulative |
|---------|---------|-------|------------|
| v1.2 | Documentation Sprint | 2 | Week 2 |
| v1.3 | Raw Mode | 1 | Week 3 |
| v1.4 | Pluggable Extraction | 2 | Week 5 |
| v1.5 | Enhanced Error Feedback | 2 | Week 7 |

**Total:** 7 weeks to complete all v1.x improvements

---

## Success Metrics

### v1.2 (Documentation)
- ✅ 5+ new documentation pages
- ✅ 2+ new examples
- ✅ Zero reported confusion about arrays vs objects
- ✅ Zero code changes

### v1.3 (Raw Mode)
- ✅ `rawMode()` adopted by 20%+ of users
- ✅ Zero backward compatibility issues
- ✅ Positive feedback on flexibility

### v1.4 (Pluggable Extraction)
- ✅ 3+ community-contributed extractors
- ✅ Zero extraction failures with default strategies
- ✅ Backward compatibility maintained

### v1.5 (Error Feedback)
- ✅ 10%+ improvement in retry success rate
- ✅ Measurable reduction in retry attempts
- ✅ Positive user feedback

---

## Risk Mitigation

### R1: Breaking Changes Accidentally Introduced

**Mitigation:**
- Comprehensive test suite for backward compatibility
- Feature flags for new functionality
- Beta releases before stable
- Community testing period

### R2: Performance Regression

**Mitigation:**
- Benchmark suite for extraction strategies
- Performance tests for raw mode overhead
- Profiling during development

### R3: Incomplete Documentation

**Mitigation:**
- Technical writer review
- Community feedback on docs
- Real user testing of examples

### R4: Low Adoption of New Features

**Mitigation:**
- Clear migration guides
- Prominent documentation
- Blog posts and tutorials
- Community engagement

---

## Deferred to v2.0

These were identified but require architectural changes:

1. **Format Abstraction (YAML/XML)** - Requires schema visitor refactoring
2. **Source Abstraction (CLI/file)** - Requires pipeline restructuring
3. **Event System Overhaul** - Breaking changes to event API
4. **Pipeline Middleware** - Architectural change

**Rationale:** These changes are valuable but require breaking changes or major refactoring. Better to do them properly in v2.0 than rush in v1.x.

---

## Conclusion

**v1.x improvements deliver:**
- ✅ Better documentation (fixes confusion)
- ✅ Raw mode (Prism use case)
- ✅ Pluggable extractors (extensibility)
- ✅ Better error feedback (improved success rate)
- ✅ 100% backward compatible
- ✅ Deliverable in 7 weeks

**After v1.x, we'll be ready for v2.0 with:**
- Solid foundation of documented features
- User feedback on raw mode and extractors
- Clear understanding of what users actually need
- No tech debt from rushed v1.x features

**Recommendation:** Execute v1.2-v1.5 in order, gather feedback, then plan v2.0 based on real user needs.
