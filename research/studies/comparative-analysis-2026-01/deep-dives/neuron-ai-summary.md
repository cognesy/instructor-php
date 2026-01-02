# NeuronAI Summary (From Initial Exploration)

**Status:** High-level summary only
**Date:** 2026-01-02

---

## Key Characteristics

### 1. Schema Generation: Reflection + Attributes

**File:** `src/StructuredOutput/JsonSchema.php`

- Uses PHP reflection to introspect classes
- Supports `#[SchemaProperty]` attribute for metadata
- Automatic required field detection (non-nullable types without defaults)
- Circular reference prevention via `$processedClasses` tracking
- Enum support (backed and non-backed)
- Array item type from PHPDoc (`@var Type[]`)

### 2. Deserialization: Custom Implementation

**File:** `src/StructuredOutput/Deserializer/Deserializer.php`

**Key Features:**
- `newInstanceWithoutConstructor()` - Creates object without calling constructor
- Property name normalization (camelCase ↔ snake_case)
- Type casting for basic types (string, int, float, bool)
- DateTime/DateTimeImmutable handling
- Enum support via `BackedEnum::tryFrom()`
- Discriminator-based polymorphism for arrays with multiple types
- Custom `__classname__` discriminator field

### 3. JSON Extraction: Multiple Strategies

**File:** `src/StructuredOutput/JsonExtractor.php`

**Four Extraction Strategies (fallback chain):**
1. Direct parsing (try as-is)
2. Markdown code block extraction (````json...```)
3. Bracket extraction (find first `{` to last `}`)
4. Smart brace matching (handles escaped quotes)

### 4. Validation: Attribute-Based Framework

**Files:** `src/StructuredOutput/Validation/`

**Built-in Rules:**
- `#[NotBlank]` - Ensures value is not empty
- `#[Email]` - Email format validation
- `#[ArrayOf]` - Type-safe array validation
- `#[Enum]` - Allowed values validation
- `#[Length]`, `#[Count]` - Size constraints
- `#[GreaterThan]`, `#[LowerThan]` - Numeric comparisons
- `#[IPAddress]`, `#[Json]` - Format validation

**Validator:**
```php
$violations = Validator::validate($obj);
// Returns array of error strings
```

### 5. Retry with Error Feedback

**File:** `src/HandleStructured.php`

```php
do {
    if (trim($error) !== '') {
        $correctionMessage = new UserMessage(
            "There was a problem... generating the following errors: $error
            Try to generate the correct JSON structure."
        );
        $this->addToChatHistory($correctionMessage);
    }

    $response = $this->resolveProvider()->structured($messages, $class, $schema);
    $this->addToChatHistory($response);

    // Process and validate
    $output = $this->processResponse($response, $schema, $class);
    return $output;

} catch (Exception $ex) {
    $error = $ex->getMessage();
    $maxRetries--;
} while ($maxRetries >= 0);
```

**Error Feedback Loop:**
- Failed response → extract errors → append as user message → retry

### 6. Provider-Specific Schema Adaptation

**Examples:**

**OpenAI:**
```php
$this->parameters['response_format'] = [
    'type' => 'json_schema',
    'json_schema' => [
        'strict' => true,
        'name' => sanitizeClassName($className),
        'schema' => $response_format,
    ],
];
```

**Anthropic:**
```php
$this->system .= "# OUTPUT CONSTRAINTS\n"
    . "Your response must be a JSON string following this schema:\n"
    . json_encode($response_format);
```

**Gemini:**
```php
$this->parameters['generationConfig']['response_schema'] = $this->adaptSchema($response_format);
$this->parameters['generationConfig']['response_mime_type'] = 'application/json';
```

**Ollama:**
```php
$this->parameters['format'] = $response_format;
```

---

## Comparison to InstructorPHP

| Aspect | NeuronAI | InstructorPHP |
|--------|----------|---------------|
| **Deserialization** | Custom implementation | Symfony Serializer |
| **Validation** | Custom attribute framework | Symfony Validator + custom |
| **Retry** | Simple loop with error feedback | Policy object with state machine |
| **JSON Extraction** | 4 fallback strategies | Similar multi-strategy |
| **Streaming** | Not integrated with structured | Full integration with partials |
| **Architecture** | Monolithic handlers | Layered with clear separation |

---

## Strengths

1. **Self-contained** - No external serialization dependencies
2. **Error feedback** - Explicit error messages to LLM
3. **Flexible JSON extraction** - Multiple fallback strategies
4. **Provider adapters** - Clean per-provider customization
5. **Validation attributes** - Simple validation DSL

## Weaknesses

1. **No streaming for structured** - Streaming exists but separate
2. **Manual validation** - Attribute-based but limited
3. **Less sophisticated retry** - Simple loop vs policy object
4. **Custom deserializer** - Reinvents Symfony Serializer wheel

---

**Core Philosophy:** Batteries-included with custom implementations for full control.
