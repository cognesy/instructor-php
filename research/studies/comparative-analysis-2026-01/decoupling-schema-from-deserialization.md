What# Deep Refactoring: Decoupling Schema from Deserialization

**Date:** 2026-01-02
**Purpose:** Architectural analysis for separating schema specification from deserialization
**Status:** Research document for v2.0 planning

---

## Key Discovery: Array Deserialization Already Exists!

**File:** `packages/instructor/src/Deserialization/Deserializers/SymfonyDeserializer.php`

```php
class SymfonyDeserializer implements CanDeserializeClass
{
    // From JSON string → Object
    public function fromJson(string $jsonData, string $dataType): mixed { ... }

    // From Array → Object (ALREADY EXISTS!)
    public function fromArray(array $data, string $dataType): mixed {
        return $this->denormalizeObject($this->serializer(), $data, $dataType);
    }

    // Object → Array
    public function toArray(object $object): array { ... }
}
```

**Implication:** The two-step process (JSON→Array→Object) is already supported internally!

---

## Deserialization Contract Hierarchy

```
┌─────────────────────────────────────────────────────────────────┐
│                 Deserialization Contracts                        │
├─────────────────────────────────────────────────────────────────┤
│ CanDeserializeResponse                                          │
│   └─▶ deserialize(text, ResponseModel) → Result                 │
│       High-level: orchestrates entire response deserialization  │
│       Uses ResponseModel to determine class + validation        │
├─────────────────────────────────────────────────────────────────┤
│ CanDeserializeClass                                             │
│   └─▶ fromJson(jsonData, dataType) → object                     │
│       Mid-level: JSON string to typed object                    │
│       Implementations: SymfonyDeserializer                      │
│       ALSO HAS: fromArray(data, dataType) → object              │
├─────────────────────────────────────────────────────────────────┤
│ CanDeserializeSelf                                              │
│   └─▶ fromJson(jsonData, toolName) → static                     │
│       Object-level: object deserializes itself                  │
│       Examples: Scalar, Sequence, Structure                     │
└─────────────────────────────────────────────────────────────────┘
```

---

## Current Architecture Analysis

### ResponseModel: The Multipurpose Object

**File:** `packages/instructor/src/Data/ResponseModel.php`

ResponseModel currently serves **three distinct responsibilities**:

```
┌─────────────────────────────────────────────────────────────────┐
│                       ResponseModel                              │
├─────────────────────────────────────────────────────────────────┤
│ 1. SCHEMA PROVIDER (for LLM)                                    │
│    - toJsonSchema()        → JSON Schema for LLM                │
│    - responseFormat()      → Output mode formatting             │
│    - toolCallSchema()      → Tool call wrapping                 │
│    - schemaName()          → Naming for API                     │
├─────────────────────────────────────────────────────────────────┤
│ 2. DESERIALIZATION TARGET                                       │
│    - returnedClass()       → Target class for deserialization   │
│    - instance()            → Pre-created instance to hydrate    │
│    - schema                → Internal Schema for type info      │
├─────────────────────────────────────────────────────────────────┤
│ 3. CONFIGURATION HOLDER                                         │
│    - config()              → StructuredOutputConfig             │
│    - toolName()            → Tool naming                        │
│    - toolDescription()     → Tool description                   │
└─────────────────────────────────────────────────────────────────┘
```

### ResponseModelFactory: Input Polymorphism

**File:** `packages/instructor/src/Creation/ResponseModelFactory.php`

The factory accepts multiple input types (`fromAny()`):

```php
// 1. Class string → Creates schema from reflection
StructuredOutput::with(responseModel: User::class)

// 2. Object instance → Uses instance, creates schema from class
StructuredOutput::with(responseModel: new User())

// 3. Array (JSON Schema) → Uses as-is, needs x-php-class for deserialization
StructuredOutput::with(responseModel: ['type' => 'object', 'x-php-class' => User::class, ...])

// 4. CanProvideJsonSchema → Object provides its own schema
StructuredOutput::with(responseModel: new Scalar('value', 'description'))

// 5. CanProvideSchema → Object provides Schema object
StructuredOutput::with(responseModel: new Sequence(User::class))

// 6. CanHandleToolSelection → Object defines multiple tools
StructuredOutput::with(responseModel: new ToolSelection([...]))
```

### Self-Deserializing Objects Pattern

**Scalar class** (`packages/instructor/src/Extras/Scalar/Scalar.php`):
```php
class Scalar implements CanProvideJsonSchema, CanDeserializeSelf, CanTransformSelf, CanValidateSelf
{
    public mixed $value = null;

    // 1. Provides its own JSON Schema
    public function toJsonSchema(): array { ... }

    // 2. Deserializes itself from JSON
    public function fromJson(string $jsonData, ?string $toolName = null): static {
        $array = Json::decode($jsonData);  // ← JSON → Array
        $this->value = $array[$this->name];  // ← Array → Object property
        return $this;
    }

    // 3. Transforms result (unwraps to scalar)
    public function transform(): mixed {
        return $this->value;
    }
}
```

**Key Insight:** Even in self-deserializing objects, there's an implicit two-step process:
1. JSON string → Associative array (`Json::decode`)
2. Array → Object properties (hydration)

---

## The Problem: Tight Coupling

### Current Flow (Sync Mode)

```
┌──────────────────────────────────────────────────────────────────────────┐
│                    StructuredOutput Processing Pipeline                   │
└──────────────────────────────────────────────────────────────────────────┘

User Input                    ResponseModel              LLM Request
     │                             │                          │
     ▼                             ▼                          ▼
┌─────────────┐    ┌────────────────────────┐    ┌────────────────────┐
│responseModel│───▶│ ResponseModelFactory   │───▶│ RequestMaterializer│
│(multipurpose)│   │ - creates ResponseModel│    │ - uses jsonSchema()│
└─────────────┘    │ - creates instance     │    │ - uses toolName()  │
                   │ - creates schema       │    └────────────────────┘
                   └────────────────────────┘              │
                              │                            ▼
                              │                     ┌──────────────┐
                              │                     │  LLM API     │
                              │                     └──────────────┘
                              │                            │
                              ▼                            ▼
┌──────────────────────────────────────────────────────────────────────────┐
│                      Response Processing Pipeline                         │
│                                                                          │
│  LLM Response ──▶ Extract JSON ──▶ Deserialize ──▶ Validate ──▶ Transform│
│       │               │                │              │            │     │
│       ▼               ▼                ▼              ▼            ▼     │
│  InferenceResponse  JsonParser    ResponseModel   Validator   Transformer│
│                                   .returnedClass()                       │
│                                   .instance()                            │
└──────────────────────────────────────────────────────────────────────────┘
                                         │
                                         ▼
                                   Typed Object (User)
```

**Problems:**

1. **Schema and Target Class are bundled** - Cannot specify schema without specifying class
2. **x-php-class is required** - Array schemas need class info for deserialization
3. **No "array only" mode** - Pipeline always produces objects
4. **Inflexible extraction** - Cannot get canonical array before deserialization

---

## Proposed Architecture: Three-Layer Separation

### Layer 1: Schema Specification

**Purpose:** Define what to request from LLM (JSON Schema for response format)

```php
interface SchemaSpecification {
    public function toJsonSchema(): array;
    public function schemaName(): string;
    public function schemaDescription(): string;
}
```

**Implementations:**
- `ClassBasedSchema` - Generated via reflection from PHP class
- `ArraySchema` - Provided directly as array
- `JsonSchemaBuilder` - Fluent builder API
- `DynamicSchema` - Runtime-constructed schema

### Layer 2: Response Extraction

**Purpose:** Extract and parse structured data from LLM response into canonical form (array)

```php
interface ResponseExtractor {
    /**
     * Extract structured data from LLM response.
     * Returns canonical associative array representation.
     */
    public function extract(InferenceResponse $response, OutputMode $mode): Result<array>;
}
```

**Implementations:**
- `JsonExtractor` - Current JSON extraction (4 strategies + 3 parsers)
- `YamlExtractor` - Future: YAML extraction
- `XmlExtractor` - Future: XML extraction

**Output:** Always `array` - the canonical intermediate representation.

### Layer 3: Hydration/Deserialization

**Purpose:** Convert canonical array into target type (object, array, or custom)

```php
interface ResponseHydrator {
    /**
     * Convert canonical array to target type.
     *
     * @param array $data Canonical array from extraction
     * @param HydrationTarget $target What to hydrate into
     * @return mixed The hydrated result
     */
    public function hydrate(array $data, HydrationTarget $target): mixed;
}

interface HydrationTarget {
    public function targetType(): string;  // 'array', 'object', 'custom'
}
```

**Implementations:**
- `ArrayHydrator` - Returns array as-is (no conversion)
- `ObjectHydrator` - Deserializes to class (current Symfony-based)
- `SelfHydrator` - Delegates to `CanDeserializeSelf` objects
- `StructureHydrator` - Creates dynamic Structure objects

---

## New ResponseModel Design

### Split into Focused Components

```php
/**
 * Schema specification for LLM request.
 * Does NOT know about deserialization target.
 */
final readonly class RequestSchema implements SchemaSpecification
{
    public function __construct(
        private array $jsonSchema,
        private string $name,
        private string $description,
        private OutputMode $outputMode,
    ) {}

    public function toJsonSchema(): array { return $this->jsonSchema; }
    public function schemaName(): string { return $this->name; }
    public function schemaDescription(): string { return $this->description; }

    // Factory methods
    public static function fromClass(string $class): self { ... }
    public static function fromArray(array $schema): self { ... }
    public static function fromBuilder(JsonSchemaBuilder $builder): self { ... }
}

/**
 * Hydration target specification.
 * Does NOT know about schema.
 */
final readonly class HydrationSpec implements HydrationTarget
{
    public function __construct(
        private string $type,           // 'array', 'object', 'self', 'structure'
        private ?string $class = null,  // For object type
        private ?object $instance = null, // For self-deserializing
    ) {}

    public function targetType(): string { return $this->type; }
    public function targetClass(): ?string { return $this->class; }
    public function targetInstance(): ?object { return $this->instance; }

    // Factory methods
    public static function array(): self {
        return new self('array');
    }

    public static function object(string $class): self {
        return new self('object', $class);
    }

    public static function selfDeserializing(object $instance): self {
        return new self('self', get_class($instance), $instance);
    }
}
```

### Schema vs JsonSchema: Critical Architectural Distinction

**IMPORTANT:** There's a fundamental difference between `Schema` and `JsonSchema`:

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                        Schema Architecture                                   │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│   Schema (Internal Canonical Representation)                                │
│   ├── packages/schema/src/Data/Schema/                                      │
│   ├── ObjectSchema, ArraySchema, ScalarSchema, EnumSchema, etc.            │
│   └── Language-agnostic structure description                               │
│                           │                                                 │
│                           ▼                                                 │
│              ┌────────────┴────────────┐                                    │
│              │    Schema Visitors      │                                    │
│              └────────────┬────────────┘                                    │
│                           │                                                 │
│         ┌─────────────────┼─────────────────┐                              │
│         ▼                 ▼                 ▼                              │
│   ┌───────────┐    ┌───────────┐    ┌───────────┐                         │
│   │JSON Schema│    │XML Schema │    │  Future   │                         │
│   │ (current) │    │ (future?) │    │ Formats   │                         │
│   └───────────┘    └───────────┘    └───────────┘                         │
│                                                                             │
│   Schema Definition Languages (Output Formats for LLMs)                    │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

**Key Points:**
- **`Schema`** = Internal canonical representation (what InstructorPHP works with internally)
- **`JsonSchema`** = ONE specific schema definition language (what current LLMs use)
- **`SchemaToJsonSchema`** = Visitor that converts Schema → JSON Schema format

**Current Reality (v1.x):** JSON Schema is the only format LLMs support.
**Future Possibility (v2.0+):** LLMs might support XML Schema, YAML Schema, Protocol Buffers, or custom formats.

**Implication for API Design:**
- `withResponseSchema(Schema)` - Works with internal representation (future-proof)
- `withResponseJsonSchema(JsonSchema)` - Works with specific output format (current LLM needs)
- `withResponseClass(string)` - Creates Schema via reflection, then converts to JSON Schema

---

### New StructuredOutput API (User's Preferred Design)

**Design Principle:** Leverage and adjust existing API methods rather than creating entirely new API surface.

#### Schema Specification Methods (Layer 1)

```php
// DEPRECATE - multipurpose, confusing
->withResponseModel(string|array|object $responseModel)   // ← Deprecate
->withResponseObject(object $responseObject)              // ← Deprecate

// MODIFY - accept JsonSchema builder or contract (specific output format)
->withResponseJsonSchema(JsonSchema|CanProvideJsonSchema $schema)  // ← Needs change
// Note: JsonSchema is a SCHEMA DEFINITION LANGUAGE, not internal representation

// KEEP - explicit class-based schema (creates internal Schema, converts to JSON Schema)
->withResponseClass(string $class)                        // ← Exists, keep as-is

// NEW - Internal Schema object or contract (canonical representation)
->withResponseSchema(Schema|CanProvideSchema $schema)     // ← New
// Note: Schema is INTERNAL REPRESENTATION, converted to JSON Schema (or other format) for LLM

// NEW - Extract class from object instance
->withResponseClassFrom(object $object)                   // ← New (calls get_class())
```

#### Output Format Methods (Layer 2)

```php
// NEW - Hydrate into specific class (may differ from schema source)
->intoInstanceOf(string $class)                          // ← New (class-string)

// NEW - Hydrate into self-deserializing object
->intoObject(CanDeserializeResponse|CanDeserializeSelf|CanDeserializeClass $object)  // ← New

// NEW - Return raw associative array (no deserialization)
->intoArray()                                            // ← New
```

#### API Usage Examples

```php
// CURRENT API (v1.x) - multipurpose responseModel (still works, deprecated)
$user = StructuredOutput::create()
    ->with(
        messages: 'Extract user',
        responseModel: User::class,  // ← Bundles schema + deserialization target
    )
    ->get();

// NEW API (v2.0) - explicit schema + explicit output format
$user = StructuredOutput::create()
    ->with(messages: 'Extract user')
    ->withResponseClass(User::class)      // ← Schema from class reflection
    ->intoInstanceOf(User::class)         // ← Hydrate to same class
    ->get();

// When schema class == target class, intoInstanceOf can be omitted (default behavior)
$user = StructuredOutput::create()
    ->with(messages: 'Extract user')
    ->withResponseClass(User::class)      // ← Schema + default hydration target
    ->get();

// Raw array extraction (no deserialization)
$data = StructuredOutput::create()
    ->with(messages: 'Extract user')
    ->withResponseClass(User::class)      // ← Schema for LLM guidance
    ->intoArray()                         // ← Return raw array, no hydration
    ->get();
// Returns: ['name' => 'John', 'age' => 30]

// Manual JsonSchema (no PHP class needed for schema)
$data = StructuredOutput::create()
    ->with(messages: 'Extract data')
    ->withResponseJsonSchema(
        JsonSchema::object('User', [
            JsonSchema::string('name'),
            JsonSchema::integer('age'),
        ])
    )
    ->intoArray()                         // ← No x-php-class needed!
    ->get();

// Different schema and hydration target
$user = StructuredOutput::create()
    ->with(messages: 'Extract user')
    ->withResponseSchema($customSchema)   // ← Schema from Schema object
    ->intoInstanceOf(UserDTO::class)      // ← Different target class
    ->get();

// Self-deserializing object (Scalar, Sequence, etc.)
$value = StructuredOutput::create()
    ->with(messages: 'Extract rating')
    ->withResponseJsonSchema($scalarSchema)
    ->intoObject(new Scalar('rating', 'User rating 1-5'))  // ← Self-deserializing
    ->get();

// Extract class from prototype object
$user = StructuredOutput::create()
    ->with(messages: 'Extract user')
    ->withResponseClassFrom(new User())   // ← Schema from get_class()
    ->get();
```

#### Method Resolution Rules

**Schema Resolution Priority:**
1. `withResponseJsonSchema()` - Use provided JsonSchema directly
2. `withResponseSchema()` - Use provided Schema object
3. `withResponseClass()` - Generate schema via reflection
4. `withResponseClassFrom()` - Generate schema via reflection from get_class()
5. (deprecated) `withResponseModel()` - Infer from multipurpose parameter

**Output Format Resolution:**
1. `intoArray()` - Return canonical array (no hydration)
2. `intoInstanceOf($class)` - Hydrate to specified class
3. `intoObject($object)` - Delegate to object's deserialization
4. Default: Hydrate to schema source class (if available)

---

## Backward Compatibility Strategy

### Keep Current API Working

```php
// v1.x style STILL WORKS in v2.0 (deprecated but functional)
$user = StructuredOutput::create()
    ->with(
        messages: 'Extract user',
        responseModel: User::class,  // ← Infers both schema AND hydration target
    )
    ->get();

// Also still works
$user = StructuredOutput::create()
    ->withResponseModel(User::class)  // ← Deprecated, triggers warning
    ->with(messages: 'Extract user')
    ->get();
```

### Deprecation Strategy

```php
// HandlesRequestBuilder trait - add deprecation notices
public function withResponseModel(string|array|object $responseModel): static {
    trigger_error(
        'withResponseModel() is deprecated. Use withResponseClass(), ' .
        'withResponseJsonSchema(), or withResponseSchema() instead.',
        E_USER_DEPRECATED
    );
    // ... existing implementation for backward compatibility
}

public function withResponseObject(object $responseObject): static {
    trigger_error(
        'withResponseObject() is deprecated. Use withResponseClassFrom() ' .
        'with intoObject() instead.',
        E_USER_DEPRECATED
    );
    // ... existing implementation for backward compatibility
}
```

---

## Processing Pipeline Changes

### Current Pipeline

```
InferenceResponse
    │
    ├──▶ findJsonData(mode) ──▶ JSON string
    │
    ├──▶ ResponseDeserializer.deserialize(json, responseModel)
    │         │
    │         ├──▶ CanDeserializeSelf? → instance.fromJson()
    │         │
    │         └──▶ SymfonyDeserializer → class instance
    │
    ├──▶ ResponseValidator.validate(object, responseModel)
    │
    └──▶ ResponseTransformer.transform(object, responseModel)
            │
            └──▶ Final value (object or transformed)
```

### New Pipeline

```
InferenceResponse
    │
    ├──▶ ResponseExtractor.extract(response, mode)
    │         │
    │         └──▶ Canonical Array (intermediate representation)
    │                    │
    │                    ├──▶ [asArray mode] ──▶ Return array directly
    │                    │
    │                    └──▶ [hydrate mode] ──▶ ResponseHydrator.hydrate(array, spec)
    │                                                  │
    │                                                  ├──▶ ArrayHydrator → array
    │                                                  ├──▶ ObjectHydrator → class instance
    │                                                  ├──▶ SelfHydrator → self-deserializing
    │                                                  └──▶ StructureHydrator → Structure
    │
    ├──▶ ResponseValidator.validate(result, validationSpec)
    │         │
    │         ├──▶ [array mode] → Schema-based validation (JSON Schema)
    │         └──▶ [object mode] → Symfony Validator + custom
    │
    └──▶ ResponseTransformer.transform(result, transformSpec)
            │
            └──▶ Final value
```

---

## Key Interfaces

### Complete Interface Definitions

```php
<?php

namespace Cognesy\Instructor\Schema;

/**
 * Provides JSON Schema for LLM request formatting.
 */
interface SchemaSpecification
{
    public function toJsonSchema(): array;
    public function schemaName(): string;
    public function schemaDescription(): string;
}

namespace Cognesy\Instructor\Extraction;

use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Utils\Result\Result;

/**
 * Extracts structured data from LLM response.
 * Returns canonical array representation.
 */
interface ResponseExtractor
{
    /**
     * @return Result<array> Success with parsed array or failure
     */
    public function extract(InferenceResponse $response, OutputMode $mode): Result;
}

namespace Cognesy\Instructor\Hydration;

use Cognesy\Utils\Result\Result;

/**
 * Specifies target for hydration.
 */
interface HydrationTarget
{
    public function targetType(): string;  // 'array', 'object', 'self', 'structure'
    public function targetClass(): ?string;
    public function targetInstance(): ?object;
}

/**
 * Converts canonical array to target type.
 */
interface ResponseHydrator
{
    /**
     * @return Result<mixed> Hydrated result
     */
    public function hydrate(array $data, HydrationTarget $target): Result;
}

namespace Cognesy\Instructor\Validation;

/**
 * Validates extracted/hydrated data.
 */
interface ResponseValidator
{
    /**
     * @param mixed $data Array or object to validate
     * @param ValidationSpec $spec Validation configuration
     * @return Result<mixed> Validated data or failure
     */
    public function validate(mixed $data, ValidationSpec $spec): Result;
}
```

---

## Migration Path

### Phase 1: Add New API Methods (v1.3/v1.4)

**Goal:** Add new schema specification and output format methods alongside existing API.

**Files to modify:**

**1. `packages/instructor/src/Traits/HandlesRequestBuilder.php`**

Add new schema specification methods:
```php
// NEW: Schema from Schema object or contract
public function withResponseSchema(Schema|CanProvideSchema $schema): static {
    // Store schema specification separately
    $this->schemaSpec = $schema;
    return $this;
}

// NEW: Extract class from object instance
public function withResponseClassFrom(object $object): static {
    return $this->withResponseClass(get_class($object));
}

// MODIFY: Accept JsonSchema builder
public function withResponseJsonSchema(JsonSchema|CanProvideJsonSchema $jsonSchema): static {
    $this->schemaSpec = $jsonSchema;
    return $this;
}
```

Add new output format methods:
```php
// NEW: Hydrate into specific class
public function intoInstanceOf(string $class): static {
    $this->outputFormat = new OutputFormat('class', $class);
    return $this;
}

// NEW: Hydrate into self-deserializing object
public function intoObject(
    CanDeserializeResponse|CanDeserializeSelf|CanDeserializeClass $object
): static {
    $this->outputFormat = new OutputFormat('object', get_class($object), $object);
    return $this;
}

// NEW: Return raw array (no deserialization)
public function intoArray(): static {
    $this->outputFormat = new OutputFormat('array');
    return $this;
}
```

**2. `packages/instructor/src/Data/OutputFormat.php`** (NEW)

```php
final readonly class OutputFormat
{
    public function __construct(
        public string $type,           // 'array', 'class', 'object'
        public ?string $class = null,  // Target class for 'class' type
        public ?object $instance = null, // Instance for 'object' type
    ) {}

    public static function array(): self {
        return new self('array');
    }

    public static function class(string $class): self {
        return new self('class', $class);
    }

    public static function object(object $instance): self {
        return new self('object', get_class($instance), $instance);
    }

    public function isArray(): bool {
        return $this->type === 'array';
    }

    public function isClass(): bool {
        return $this->type === 'class';
    }

    public function isObject(): bool {
        return $this->type === 'object';
    }
}
```

**3. `packages/instructor/src/Data/ResponseModel.php`**

Extend to store output format separately:
```php
class ResponseModel implements CanProvideJsonSchema
{
    // ... existing properties ...

    private ?OutputFormat $outputFormat = null;

    public function withOutputFormat(OutputFormat $format): self {
        $clone = clone $this;
        $clone->outputFormat = $format;
        return $clone;
    }

    public function outputFormat(): OutputFormat {
        // Default: class-based if returnedClass available, otherwise array
        return $this->outputFormat ?? (
            $this->class !== null
                ? OutputFormat::class($this->class)
                : OutputFormat::array()
        );
    }

    public function shouldReturnArray(): bool {
        return $this->outputFormat()->isArray();
    }
}
```

### Phase 2: Modify Processing Pipeline (v1.4/v1.5)

**Goal:** Pipeline respects output format specification.

**Files to modify:**

**1. `packages/instructor/src/Core/ResponseGenerator.php`**

```php
public function makeResponse(
    InferenceResponse $response,
    ResponseModel $responseModel,
    OutputMode $mode
): Result {
    if ($response->hasValue()) {
        return Result::success($response->value());
    }

    // Extract JSON to canonical array
    $json = $response->findJsonData($mode)->toString();
    if ($json === '') {
        return Result::failure('No JSON found in response');
    }

    $canonicalArray = json_decode($json, true);
    if ($canonicalArray === null && json_last_error() !== JSON_ERROR_NONE) {
        return Result::failure('Failed to parse JSON: ' . json_last_error_msg());
    }

    // Check output format
    if ($responseModel->shouldReturnArray()) {
        // Skip deserialization, return canonical array
        return Result::success($canonicalArray);
    }

    // Continue with normal deserialization pipeline
    $pipeline = $this->makeResponsePipeline($responseModel);
    return $pipeline->executeWith(ProcessingState::with($json))->result();
}
```

**2. `packages/instructor/src/PendingStructuredOutput.php`**

Ensure `toArray()` now leverages `intoArray()`:
```php
public function toArray(): array {
    // Set output format to array
    $this->intoArray();

    return match(true) {
        $this->execution->isStreamed() => $this->stream()->finalResponse()
            ->findJsonData($this->execution->outputMode())->toArray(),
        default => $this->getResponse()->value() // Now returns array
    };
}
```

### Phase 3: Deprecation and Documentation (v2.0)

**Goal:** Deprecate old methods, finalize new API.

1. Add `@deprecated` annotations to `withResponseModel()` and `withResponseObject()`
2. Add deprecation warnings (E_USER_DEPRECATED)
3. Update all examples to use new API
4. Create migration guide
5. Eventually remove deprecated methods in v3.0

**Files to create:**
- `docs/migration/v1-to-v2.md` - Migration guide
- `docs/essentials/schema_specification.md` - New API docs
- `docs/essentials/output_formats.md` - Output format docs

**Files to update:**
- All example files to use new API patterns
- `README.md` with new API examples

---

## Streaming Considerations

### Current Streaming

```
LLM Stream
    │
    ├──▶ Accumulate partials
    │
    ├──▶ DeserializeAndDeduplicate (uses ResponseDeserializer)
    │
    └──▶ Emit partial objects
```

### New Streaming

```
LLM Stream
    │
    ├──▶ Accumulate partials
    │
    ├──▶ ExtractPartial (returns partial array)
    │         │
    │         └──▶ [asArray mode] ──▶ Emit partial arrays
    │
    ├──▶ HydratePartial (optional)
    │         │
    │         └──▶ Emit partial objects
    │
    └──▶ Deduplicate and emit
```

**New streaming methods:**

```php
$stream = StructuredOutput::create()
    ->with(messages: 'Extract users')
    ->schema(User::class)
    ->asArray()
    ->stream();

foreach ($stream->partials() as $partialArray) {
    // ['name' => 'Jo', 'age' => null]
    // ['name' => 'John', 'age' => 30]
}

$finalArray = $stream->finalValue();
// ['name' => 'John Doe', 'age' => 30]
```

---

## Benefits

### For Users

1. **Simpler mental model** - Schema and deserialization are separate concepts
2. **More flexibility** - Use schema without committing to class
3. **Array-first workflows** - No forced deserialization
4. **Mix and match** - Different schema and target class

### For Codebase

1. **Single Responsibility** - Each component has one job
2. **Testability** - Can test extraction without deserialization
3. **Extensibility** - Easy to add YAML/XML extractors
4. **Clarity** - Clear data flow through pipeline

### For Future Features

1. **Format abstraction** - YAML, XML extractors plug in easily
2. **Source abstraction** - CLI/file sources produce same canonical array
3. **Validation modes** - Array validation vs object validation
4. **Custom hydrators** - User-defined hydration strategies

---

## Risks and Mitigations

### Risk 1: Breaking Changes

**Mitigation:**
- Maintain full backward compatibility for v2.0
- Deprecation warnings for old patterns
- Clear migration guide

### Risk 2: Complexity Increase

**Mitigation:**
- Default behavior unchanged (responseModel still works)
- Progressive disclosure (simple API for simple cases)
- Comprehensive documentation

### Risk 3: Performance Overhead

**Mitigation:**
- Canonical array is already computed internally
- No extra parsing step (just exposing intermediate)
- Lazy hydration (only when requested)

---

## Summary

### Current State
- ResponseModel bundles schema + deserialization + config
- x-php-class required for array schemas
- No way to get raw arrays without deserialization
- Multipurpose `withResponseModel()` method handles all cases

### Critical Architectural Insight: Schema ≠ JsonSchema

```
Schema (Internal)  →  Visitor  →  JsonSchema | XmlSchema | Future formats
     ↑                              ↓
  Canonical              Schema Definition Languages
  Representation              (LLM output formats)
```

- **`Schema`** = Internal canonical representation (language-agnostic)
- **`JsonSchema`** = ONE schema definition language (what current LLMs use)
- **v1.x:** JSON Schema is the only LLM format we support
- **v2.0+:** Architecture ready for XML Schema, YAML Schema, Protocol Buffers, etc.

### Proposed State (User's Preferred Design)

**Schema Specification Methods (Explicit, Single-Purpose):**
- `withResponseClass(string $class)` - Keep, creates Schema via reflection
- `withResponseJsonSchema(JsonSchema|CanProvideJsonSchema)` - Modify, specific SDL
- `withResponseSchema(Schema|CanProvideSchema)` - New, internal representation
- `withResponseClassFrom(object)` - New, convenience for prototype objects
- `withResponseModel()` - Deprecate (multipurpose)
- `withResponseObject()` - Deprecate

**Output Format Methods (New Layer):**
- `intoInstanceOf(string $class)` - Hydrate to specified class
- `intoObject(CanDeserialize* $object)` - Delegate to self-deserializing object
- `intoArray()` - Return canonical array (no deserialization)

**New Supporting Class:**
- `OutputFormat` - Encapsulates output format specification

### Key Changes
1. Separate schema specification from output format specification
2. Add explicit output format methods (`intoArray()`, `intoInstanceOf()`, `intoObject()`)
3. Extend `ResponseModel` to store `OutputFormat` separately
4. Modify pipeline to respect output format (skip deserialization for array mode)
5. Remove x-php-class requirement when using `intoArray()`
6. Deprecate multipurpose methods, keep explicit ones
7. Maintain full backward compatibility

### Implementation Timeline
- **v1.3/v1.4:** Add new API methods (schema specification + output format)
- **v1.4/v1.5:** Modify pipeline to respect output format
- **v2.0:** Deprecate old methods, finalize API, documentation
- **v3.0:** Remove deprecated methods (optional)

### Files to Create
- `packages/instructor/src/Data/OutputFormat.php`
- `docs/migration/v1-to-v2.md`
- `docs/essentials/schema_specification.md`
- `docs/essentials/output_formats.md`

### Files to Modify
- `packages/instructor/src/Traits/HandlesRequestBuilder.php`
- `packages/instructor/src/Data/ResponseModel.php`
- `packages/instructor/src/Core/ResponseGenerator.php`
- `packages/instructor/src/PendingStructuredOutput.php`
