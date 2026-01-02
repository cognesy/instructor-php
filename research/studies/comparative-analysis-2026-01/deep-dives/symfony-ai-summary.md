# Symfony-AI Summary (From Initial Exploration)

**Status:** High-level summary only
**Date:** 2026-01-02

---

## Key Characteristics

### 1. Schema Generation: Symfony TypeInfo Integration

**File:** `src/platform/src/Contract/JsonSchema/Factory.php`

**Uses:**
- `Symfony\Component\TypeInfo\TypeResolver` - Resolves types from properties/parameters
- Automatic type inference from constructor parameters
- PHPDoc extraction for descriptions
- Full nullable type support

**Type Mapping:**
```php
private function getTypeSchema(Type $type): array {
    // BackedEnum
    if ($type instanceof BackedEnumType) {
        return $this->buildEnumSchema($type->getClassName());
    }

    // Union types
    if ($type instanceof UnionType) {
        return ['anyOf' => $variants];
    }

    // Builtin types
    switch (true) {
        case $type->isIdentifiedBy(TypeIdentifier::INT):
            return ['type' => 'integer'];
        case $type->isIdentifiedBy(TypeIdentifier::FLOAT):
            return ['type' => 'number'];
        // ... etc
    }
}
```

### 2. Schema Refinement via Attributes

**File:** `src/platform/src/Contract/JsonSchema/Attribute/With.php`

```php
#[With(
    pattern: '^[A-Z]{2}[0-9]{3}$',  // String constraints
    minLength: 5,
    maxLength: 5,
    minimum: 0,  // Number constraints
    maximum: 100,
    minItems: 1,  // Array constraints
    maxItems: 10,
    enum: ['a', 'b', 'c'],  // Allowed values
)]
public string $code;
```

**Comprehensive constraints for all JSON Schema validation keywords.**

### 3. Deserialization: Symfony Serializer

**File:** `src/platform/src/Serializer/StructuredOutputSerializer.php`

```php
class StructuredOutputSerializer extends Serializer {
    public function __construct() {
        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
        $discriminator = new ClassDiscriminatorFromClassMetadata($classMetadataFactory);
        $propertyInfo = new PropertyInfoExtractor([
            new PhpDocExtractor(),
            new ReflectionExtractor()
        ]);

        $normalizers = [
            new BackedEnumNormalizer(),
            new ObjectNormalizer(
                propertyTypeExtractor: $propertyInfo,
                classDiscriminatorResolver: $discriminator,
            ),
            new ArrayDenormalizer(),
        ];

        parent::__construct($normalizers, [new JsonEncoder()]);
    }
}
```

**Features:**
- Respects `#[SerializedName]` and `#[Ignore]` attributes
- Discriminated unions via `#[DiscriminatorMap]`
- BackedEnum support
- Constructor property promotion support

### 4. Result Types

**Files:** `src/platform/src/Result/`

**Multiple Result Types:**
- `TextResult` - Plain text responses
- `ObjectResult` - Deserialized structured output
- `ToolCallResult` - Function calling
- `StreamResult` - Generator-based streaming
- `BinaryResult` - Images/audio
- `VectorResult` - Embeddings
- `ChoiceResult` - Multiple alternatives

### 5. Event-Driven Architecture

**File:** `src/platform/src/StructuredOutput/PlatformSubscriber.php`

```php
public function processResult(ResultEvent $event): void {
    $options = $event->getOptions();

    if (!isset($options[self::RESPONSE_FORMAT])) {
        return;  // Not a structured output request
    }

    // Streaming check
    if (true === ($options['stream'] ?? false)) {
        throw new InvalidArgumentException(
            'Streamed responses are not supported for structured output.'
        );
    }

    // Wrap with structured output converter
    $deferred = $event->getDeferredResult();
    $converter = new ResultConverter(
        $deferred->getResultConverter(),
        $this->serializer,
        $this->outputType ?? null
    );

    $event->setDeferredResult(
        new DeferredResult($converter, $deferred->getRawResult(), $options)
    );
}
```

**Event Subscribers:**
- Hook into request/response lifecycle
- Transform results based on response format
- Apply serialization automatically

### 6. Polymorphic Types via DiscriminatorMap

```php
#[DiscriminatorMap(
    typeProperty: 'type',
    mapping: [
        'circle' => Circle::class,
        'rectangle' => Rectangle::class,
    ]
)]
abstract class Shape {
    public string $type;
}

class Circle extends Shape {
    public function __construct(
        public float $radius,
    ) {
        $this->type = 'circle';
    }
}
```

**Schema Generation:**
```json
{
  "anyOf": [
    {
      "type": "object",
      "properties": {
        "type": {"const": "circle"},
        "radius": {"type": "number"}
      }
    },
    {
      "type": "object",
      "properties": {
        "type": {"const": "rectangle"},
        "width": {"type": "number"},
        "height": {"type": "number"}
      }
    }
  ]
}
```

---

## Comparison to InstructorPHP

| Aspect | Symfony-AI | InstructorPHP |
|--------|------------|---------------|
| **Schema Generation** | Symfony TypeInfo | Custom TypeDetails |
| **Type Resolution** | TypeResolver component | Reflection + PHPDoc |
| **Deserialization** | Symfony Serializer | Symfony Serializer (same) |
| **Attributes** | `#[With]` for constraints | Custom validation attributes |
| **Polymorphism** | `#[DiscriminatorMap]` | Discriminator field |
| **Architecture** | Event-driven subscribers | Direct pipeline |
| **Integration** | Deep Symfony ecosystem | Framework-agnostic |

---

## Strengths

1. **Symfony Ecosystem** - Full integration with Symfony components
2. **Type Safety** - TypeInfo provides robust type resolution
3. **Attribute-Rich** - Comprehensive `#[With]` constraints
4. **Discriminated Unions** - Clean polymorphism support
5. **Event-Driven** - Flexible request/response transformation

## Weaknesses

1. **No Streaming** - Structured outputs don't support streaming
2. **Symfony-Specific** - Tightly coupled to Symfony ecosystem
3. **No Retry** - Exception-based, no auto-retry
4. **Complex Setup** - Requires understanding Symfony architecture

---

**Core Philosophy:** Leverage Symfony ecosystem for type-safe structured outputs.
