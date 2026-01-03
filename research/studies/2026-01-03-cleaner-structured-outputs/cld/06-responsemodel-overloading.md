# P2: ResponseModel Overloading

## Problem Statement

`ResponseModel` mixes multiple responsibilities:

1. **Schema definition** - JSON schema, property names
2. **Type information** - Target class, instance
3. **Configuration** - Duplicates from StructuredOutputConfig
4. **Output formatting** - Tool calls, response format
5. **Runtime state** - Property values, OutputFormat

This creates tight coupling and makes the class hard to understand.

## Evidence

### 1. Constructor Complexity

```php
public function __construct(
    string $class,
    mixed  $instance,
    Schema $schema,
    array  $jsonSchema,
    string $schemaName,
    string $schemaDescription,
    string $toolName,
    string $toolDescription,
    bool   $useObjectReferences = false,
    ?StructuredOutputConfig $config = null,  // Contains duplicates!
)
```

### 2. Duplicated Data

```php
class ResponseModel {
    // Schema metadata
    private string $schemaName;         // Also in config!
    private string $schemaDescription;  // Also in config!

    // Tool settings
    private string $toolName;           // Also in config!
    private string $toolDescription;    // Also in config!

    // Configuration
    private StructuredOutputConfig $config;  // Contains all of the above!
    private ?OutputFormat $outputFormat;
}
```

### 3. Accessor Complexity (Priority Resolution)

```php
public function toolName() : string {
    return $this->toolName ?: ($this->config->toolName() ?: 'extract_data');
}

public function toolDescription() : string {
    return $this->toolDescription ?: ($this->config->toolDescription() ?: '');
}

public function schemaName() : string {
    return $this->schemaName ?: ($this->schema()->name() ?: 'default_schema');
}
```

Three sources of truth with fallback chains!

### 4. Format Generation Methods

The class also generates output formats:

```php
public function toolCallSchema() : ?array { ... }
public function responseFormat() : array { ... }
public function toolChoice() : string|array { ... }
```

These are presentation concerns, not model concerns.

### 5. Runtime State Mutation

```php
public function setPropertyValues(array $values) : void {
    foreach ($values as $name => $value) {
        if (property_exists($this->instance, $name)) {
            $this->instance->$name = $value;
        }
    }
}
```

Mutable state in what should be an immutable specification.

## Impact

- **Confusing priority chains** - Which toolName is used?
- **Hidden coupling** - Changes to Config affect ResponseModel
- **Mixed paradigms** - Immutable-style `with*` + mutable `setPropertyValues`
- **Presentation in domain** - Schema formatting doesn't belong here
- **Hard to test** - Need full config setup to test schema logic

## Proposed Solution

### Split Responsibilities

```php
// 1. Schema specification (immutable, core)
final readonly class SchemaSpec {
    public function __construct(
        public Schema $schema,
        public array $jsonSchema,
        public string $name,
        public string $description = '',
        public bool $useObjectReferences = false,
    ) {}

    public function propertyNames(): array { ... }
}

// 2. Output target (immutable, per-request)
final readonly class OutputTarget {
    public function __construct(
        public string $class,
        public ?object $instance = null,
        public ?OutputFormat $format = null,
    ) {}

    public function shouldReturnArray(): bool { ... }
    public function targetClass(): string { ... }
}

// 3. Tool specification (immutable, for Tools mode)
final readonly class ToolSpec {
    public function __construct(
        public string $name = 'extract_data',
        public string $description = 'Extract data based on instructions',
    ) {}
}

// 4. Format builders (services, not data)
final readonly class LLMFormatBuilder {
    public function __construct(
        private SchemaSpec $schema,
        private ?ToolSpec $tool = null,
    ) {}

    public function toolCallSchema(): array { ... }
    public function responseFormat(OutputMode $mode): array { ... }
    public function toolChoice(): string|array { ... }
}

// 5. Simplified ResponseModel (composition)
final readonly class ResponseModel {
    public function __construct(
        public SchemaSpec $schema,
        public OutputTarget $output,
        public ?ToolSpec $tool = null,
    ) {}

    // Delegating accessors
    public function schemaName(): string {
        return $this->schema->name;
    }

    public function toolName(): string {
        return $this->tool?->name ?? 'extract_data';
    }

    public function shouldReturnArray(): bool {
        return $this->output->shouldReturnArray();
    }
}
```

### Benefits

1. **Clear responsibilities** - Each class does one thing
2. **Single source of truth** - No priority chains
3. **Immutable throughout** - No mutation methods
4. **Testable** - Each component testable in isolation
5. **Presentation separated** - Format building is a service

## Alternative: Just Remove Duplication

Simpler approach - delegate everything to config:

```php
class ResponseModel {
    public function __construct(
        private Schema $schema,
        private array $jsonSchema,
        private mixed $instance,
        private StructuredOutputConfig $config,
        private ?OutputFormat $outputFormat = null,
    ) {}

    // Always delegate to config (single source of truth)
    public function toolName(): string {
        return $this->config->toolName();
    }

    public function toolDescription(): string {
        return $this->config->toolDescription();
    }

    public function schemaName(): string {
        return $this->config->schemaName();
    }
}
```

## File Impact

### Full Split (Option A)

```
Data/
├── SchemaSpec.php (new)
├── OutputTarget.php (new)
├── ToolSpec.php (new)
├── ResponseModel.php (simplified)
Core/
└── LLMFormatBuilder.php (new, extracted from ResponseModel)
```

### Simple Dedup (Option B)

```
Data/
└── ResponseModel.php (modified - remove duplicate properties)
```

## Migration Path

### For Full Split

1. Create new value objects (additive)
2. Create factory method on ResponseModel to build from new types
3. Update StructuredOutputExecutionBuilder to use new types
4. Gradually migrate consumers
5. Remove deprecated ResponseModel constructor

### For Simple Dedup

1. Remove duplicate properties from ResponseModel
2. Update accessors to always delegate to config
3. Update callers that passed duplicates in constructor
4. Remove unused constructor parameters

## Risk Assessment

- **Option A (Full Split)**: Medium risk, significant changes but cleaner result
- **Option B (Simple Dedup)**: Low risk, quick win with moderate improvement

## Estimated Effort

- Option A: 12-16 hours
- Option B: 4 hours

**Recommendation**: Start with Option B, consider Option A as part of larger refactoring.

## Success Metrics

- No duplicated data between ResponseModel and StructuredOutputConfig
- Single source of truth for each configuration value
- No priority chain fallback logic in accessors
- Presentation logic extracted from data class
