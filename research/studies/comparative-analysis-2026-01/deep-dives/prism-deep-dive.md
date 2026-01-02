# Prism Deep Dive Analysis

**Status:** Completed core analysis
**Date:** 2026-01-02
**Purpose:** Understand Prism's manual schema building and array-based structured outputs

---

## Architecture Overview: Manual Builder Pattern

**Core Philosophy:** Developer-controlled schema construction with NO automatic PHP object deserialization.

### Key Characteristics

1. **Manual Schema Building** - Developers construct schemas programmatically
2. **Array-Only Responses** - No automatic deserialization to PHP objects
3. **Strategy Pattern** - Provider-specific structured output strategies
4. **Laravel-Centric** - Heavy use of traits, collections, service providers
5. **Simplicity First** - Minimal magic, explicit control

---

## 1. Entry Point: Prism Facade

**File:** `src/Prism.php`

```php
// Lines 15-48
class Prism {
    use Macroable;

    public function text(): PendingTextRequest
    public function structured(): PendingStructuredRequest  // Our focus
    public function embeddings(): PendingEmbeddingRequest
    public function image(): PendingImageRequest
    public function audio(): PendingAudioRequest
    public function moderation(): PendingModerationRequest
}
```

**Usage:**
```php
Prism::structured()
    ->using('anthropic', 'claude-3-5-sonnet-20241022')
    ->withSchema(
        new ObjectSchema(
            name: 'user',
            description: 'User data',
            properties: [
                new StringSchema('name', 'User name'),
                new NumberSchema('age', 'User age'),
            ],
            requiredFields: ['name'],
        )
    )
    ->withPrompt('Extract user info')
    ->asStructured();  // Returns Response with array data
```

---

## 2. Schema System: Manual Construction

**File:** `src/Schema/ObjectSchema.php`

### ObjectSchema - Manual Builder

```php
// Lines 11-27
class ObjectSchema implements Schema {
    use NullableSchema;

    /**
     * @param  array<int, Schema>  $properties
     * @param  array<int, string>  $requiredFields
     */
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly array $properties,  // Array of Schema objects
        public readonly array $requiredFields = [],
        public readonly bool $allowAdditionalProperties = false,
        public readonly bool $nullable = false,
    ) {}
```

### JSON Schema Conversion

```php
// Lines 35-48
public function toArray(): array {
    $properties = $this->propertiesArray();

    return Arr::whereNotNull([
        'description' => $this->description,
        'type' => $this->nullable
            ? $this->castToNullable('object')  // ['object', 'null']
            : 'object',
        'properties' => $properties === [] ? null : $properties,
        'required' => $this->requiredFields,
        'additionalProperties' => $this->allowAdditionalProperties,
    ]);
}

// Lines 53-59
protected function propertiesArray(): array {
    return collect($this->properties)
        ->keyBy(fn (Schema $parameter): string => $parameter->name())
        ->map(fn (Schema $parameter): array => $parameter->toArray())
        ->toArray();
}
```

### Schema Hierarchy

```
Schema (interface)
├── StringSchema
├── NumberSchema
├── BooleanSchema
├── ArraySchema
├── EnumSchema
├── ObjectSchema
├── AnyOfSchema (union types)
└── RawSchema (arbitrary JSON schema)
```

**Key Difference from InstructorPHP:**
- No reflection
- No automatic schema generation
- Developers manually construct every property
- Simple `toArray()` conversion

---

## 3. Request Pipeline

**File:** `src/Structured/PendingRequest.php`

### Trait Composition

```php
// Lines 24-37
class PendingRequest {
    use ConfiguresClient;
    use ConfiguresGeneration;
    use ConfiguresModels;
    use ConfiguresProviders;
    use ConfiguresStructuredOutput;
    use ConfiguresTools;
    use HasMessages;
    use HasPrompts;
    use HasProviderOptions;
    use HasProviderTools;
    use HasSchema;
    use HasTools;
```

### Execution Flow

```php
// Lines 47-56
public function asStructured(): Response {
    $request = $this->toRequest();

    try {
        return $this->provider->structured($request);  // Delegate to provider
    } catch (RequestException $e) {
        $this->provider->handleRequestException($request->model(), $e);
    }
}

// Lines 58-80
public function toRequest(): Request {
    // Validation
    if ($this->messages && $this->prompt) {
        throw PrismException::promptOrMessages();
    }
    if (! $this->schema instanceof Schema) {
        throw new PrismException('A schema is required for structured output');
    }

    // Build messages
    $messages = $this->messages;
    if ($this->prompt) {
        $messages[] = new UserMessage($this->prompt, $this->additionalContent);
    }

    return new Request(
        systemPrompts: $this->systemPrompts,
        model: $this->model,
        schema: $this->schema,  // Schema is just passed through
        mode: $this->mode,
        // ... other params
    );
}
```

---

## 4. Response: Array-Only, No Deserialization

**File:** `src/Structured/Response.php`

### Response Structure

```php
// Lines 14-34
readonly class Response {
    /**
     * @param  Collection<int, Step>  $steps
     * @param  array<mixed>  $structured  ← KEY: Array, not object
     * @param  array<int, ToolCall>  $toolCalls
     * @param  array<int, ToolResult>  $toolResults
     * @param  array<string,mixed>  $additionalContent
     */
    public function __construct(
        public Collection $steps,
        public string $text,
        public ?array $structured,  // Always an array
        public FinishReason $finishReason,
        public Usage $usage,
        public Meta $meta,
        public array $toolCalls = [],
        public array $toolResults = [],
        public array $additionalContent = []
    ) {}
}
```

**No Deserialization** - Developers must manually map arrays to objects if desired:
```php
$response = Prism::structured()->asStructured();
$data = $response->structured;  // ['name' => 'John', 'age' => 30]
// Developer manually creates object:
$user = new User($data['name'], $data['age']);
```

---

## 5. Response Building & JSON Extraction

**File:** `src/Structured/ResponseBuilder.php`

### Simple JSON Parsing

```php
// Lines 50-57
protected function extractFinalStructuredData(Step $finalStep): array {
    if ($this->shouldDecodeFromText($finalStep)) {
        return $this->decodeObject($finalStep->text);  // Parse from text
    }
    return $finalStep->structured;  // Already parsed by provider
}

// Lines 68-81
protected function decodeObject(string $responseText): array {
    try {
        // Strip markdown code blocks
        $pattern = '/^```(?:json)?\s*\n?(.*?)\n?```$/s';
        if (preg_match($pattern, trim($responseText), $matches)) {
            $responseText = trim($matches[1]);
        }

        return json_decode($responseText, true, flags: JSON_THROW_ON_ERROR);
    } catch (\JsonException) {
        throw PrismStructuredDecodingException::make($responseText);
    }
}
```

**Key Feature:** Simple regex-based extraction, no complex parsing strategies like InstructorPHP.

### Multi-Step Aggregation

```php
// Lines 29-45
public function toResponse(): Response {
    /** @var Step $finalStep */
    $finalStep = $this->steps->last();

    return new Response(
        steps: $this->steps,
        text: $finalStep->text,
        structured: $this->extractFinalStructuredData($finalStep),
        finishReason: $finalStep->finishReason,
        usage: $this->calculateTotalUsage(),  // Sum across all steps
        meta: $finalStep->meta,
        toolCalls: $this->aggregateToolCalls(),  // Flatten all tool calls
        toolResults: $this->aggregateToolResults(),
        additionalContent: $finalStep->additionalContent,
    );
}

// Lines 86-92
protected function aggregateToolCalls(): array {
    return $this->steps
        ->flatMap(fn (Step $step): array => $step->toolCalls)
        ->values()
        ->toArray();
}
```

**Design:** Supports multi-step agentic workflows (tool calling loops).

---

## 6. Provider Strategy Pattern

**Files:** `src/Providers/Anthropic/Handlers/StructuredStrategies/`

### Four Strategies for Anthropic

1. **NativeOutputFormatStructuredStrategy** - Uses `output_format` (newest)
2. **JsonModeStructuredStrategy** - Uses JSON mode instructions
3. **ToolStructuredStrategy** - Function calling mode
4. **AnthropicStructuredStrategy** - Base interface

**Example: NativeOutputFormatStructuredStrategy**
```php
public function mutatePayload(array $payload): array {
    $schemaArray = $this->request->schema()->toArray();

    $payload['output_format'] = [
        'type' => 'json_schema',
        'schema' => $schemaArray,
    ];

    return $payload;
}
```

**Key Insight:** Each provider has multiple strategies for different API capabilities.

---

## 7. Request Structure

**File:** `src/Structured/Request.php`

```php
// Lines 32-52
public function __construct(
    protected array $systemPrompts,
    protected string $model,
    protected string $providerKey,
    protected ?string $prompt,
    protected array $messages,
    protected ?int $maxTokens,
    protected int|float|null $temperature,
    protected int|float|null $topP,
    protected array $clientOptions,
    protected array $clientRetry,  // Laravel HTTP client retry config
    protected Schema $schema,  // Schema object (not JSON)
    protected StructuredMode $mode,  // JSON | TOOL | TEXT
    protected array $tools,
    protected string|ToolChoice|null $toolChoice,
    protected int $maxSteps,  // For agentic loops
    array $providerOptions = [],
    protected array $providerTools = [],
) {}
```

---

## Key Design Decisions

### Why No Deserialization?

**Pros:**
- Simplicity - No complex type resolution
- Explicit - Developer controls mapping
- Flexibility - Can use any deserialization library
- Performance - No reflection overhead
- Debuggability - Clear data flow

**Cons:**
- Boilerplate - Manual mapping code
- Error-prone - Easy to miss fields
- No type safety - Arrays are untyped

### Why Manual Schema Building?

**Pros:**
- Control - Exact schema specification
- No magic - What you write is what you get
- Cross-library - Can build schemas from any source
- Provider-agnostic - Not tied to PHP class structure

**Cons:**
- Verbose - More code to write
- Duplication - Schema separate from class definition
- Maintenance - Changes require updating schema AND class

---

## Comparison to InstructorPHP

| Aspect | Prism | InstructorPHP |
|--------|-------|---------------|
| **Schema Generation** | Manual builder pattern | Automatic reflection |
| **Deserialization** | None (arrays only) | Symfony Serializer |
| **Type Safety** | Developer responsibility | Full type validation |
| **Complexity** | Simple, explicit | Complex, automated |
| **Performance** | Minimal overhead | Reflection overhead |
| **Developer Experience** | Verbose but clear | Concise but magical |
| **Validation** | None built-in | Symfony Validator |
| **Retry** | HTTP client level | Integrated with validation |

---

## File Map

### Core
- ✅ `Prism.php` - Main facade
- ✅ `Structured/PendingRequest.php` - Request builder
- ✅ `Structured/Request.php` - Request DTO
- ✅ `Structured/Response.php` - Response DTO
- ✅ `Structured/ResponseBuilder.php` - Multi-step aggregation
- ✅ `Structured/Step.php` - Single response step

### Schema
- ✅ `Schema/ObjectSchema.php` - Object schema builder
- ✅ `Schema/StringSchema.php` - String schema
- ✅ `Schema/NumberSchema.php` - Number schema
- ✅ `Schema/BooleanSchema.php` - Boolean schema
- ✅ `Schema/ArraySchema.php` - Array schema
- ✅ `Schema/EnumSchema.php` - Enum schema
- ✅ `Schema/AnyOfSchema.php` - Union types
- ✅ `Schema/RawSchema.php` - Raw JSON schema

### Provider Strategies
- ⏸️ `Providers/Anthropic/Handlers/StructuredStrategies/` - Strategy pattern

---

## Summary

**Prism's Philosophy:** Simplicity and explicit control over magic and automation.

**Best For:**
- Developers who want full control
- Simple use cases without complex validation
- Laravel applications (native integration)
- When you already have a deserialization strategy

**Not Ideal For:**
- Complex domain models with validation
- Type-safe applications
- Rapid prototyping (too verbose)
- Teams that value automation over control
