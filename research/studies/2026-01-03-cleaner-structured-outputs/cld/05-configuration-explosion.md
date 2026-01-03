# P2: Configuration Explosion

## Problem Statement

`StructuredOutputConfig` has 14 constructor parameters and is growing:

```php
public function __construct(
    ?OutputMode $outputMode = null,
    ?string $outputClass = null,
    ?bool $useObjectReferences = null,
    ?int $maxRetries = null,
    ?string $schemaName = null,
    ?string $schemaDescription = null,
    ?string $toolName = null,
    ?string $toolDescription = null,
    ?array $modePrompts = null,
    ?string $retryPrompt = null,
    ?array $chatStructure = null,
    ?bool $defaultToStdClass = null,
    ?string $deserializationErrorPrompt = null,
    ?bool $throwOnTransformationFailure = null,
    ?string $responseIterator = null,
)
```

This creates a "configuration god object" that's hard to understand and extend.

## Evidence

### 1. Mixed Concerns in Single Config

| Parameter | Concern |
|-----------|---------|
| `outputMode`, `outputClass` | Output specification |
| `maxRetries`, `retryPrompt` | Retry behavior |
| `schemaName`, `schemaDescription` | Schema metadata |
| `toolName`, `toolDescription` | Tool mode settings |
| `modePrompts`, `chatStructure` | Prompt construction |
| `useObjectReferences` | Schema generation |
| `defaultToStdClass`, `deserializationErrorPrompt` | Deserialization |
| `throwOnTransformationFailure` | Transformation |
| `responseIterator` | Implementation detail |

### 2. Corresponding `with*` Methods

Every parameter has a corresponding mutator:

```php
public function withOutputMode(?OutputMode $outputMode): static
public function withMaxRetries(int $maxRetries): static
public function withSchemaName(string $schemaName): static
public function withToolName(string $toolName): static
public function withToolDescription(string $toolDescription): static
public function withUseObjectReferences(bool $useObjectReferences): static
public function withRetryPrompt(string $retryPrompt): static
public function withModePrompt(OutputMode $mode, string $prompt): static
public function withModePrompts(array $modePrompts): static
public function withChatStructure(array $chatStructure): static
public function withDefaultOutputClass(string $defaultOutputClass): static
public function withResponseIterator(string $responseIterator): static
// ... plus generic with() method with 14 parameters
```

### 3. Duplication with ResponseModel

`ResponseModel` also carries configuration:

```php
class ResponseModel {
    private StructuredOutputConfig $config;  // Duplicate!
    private string $toolName;                 // Duplicate!
    private string $toolDescription;          // Duplicate!
    private string $schemaName;               // Duplicate!
    private string $schemaDescription;        // Duplicate!
}
```

### 4. Magic String Defaults

Default values are scattered:

```php
// In StructuredOutputConfig
$this->toolName = $toolName ?? 'extracted_data';

// In ResponseModel
public function toolName() : string {
    return $this->toolName ?: ($this->config->toolName() ?: 'extract_data');
}
```

Note: Different defaults! `'extracted_data'` vs `'extract_data'`

## Impact

- **Hard to understand** - What does each option do?
- **Hard to extend** - Every new option requires 3+ changes
- **Inconsistent defaults** - Different values in different places
- **Testing complexity** - Many parameter combinations
- **API surface area** - Too many configuration methods exposed

## Proposed Solution

### Split by Concern

Replace single config with focused value objects:

```php
// Core processing config (rarely changed)
final readonly class ProcessingConfig {
    public function __construct(
        public int $maxRetries = 0,
        public string $retryPrompt = "JSON generated incorrectly, fix following errors:\n",
        public bool $throwOnTransformationFailure = false,
    ) {}
}

// Output specification (per-request)
final readonly class OutputSpec {
    public function __construct(
        public OutputMode $mode = OutputMode::Tools,
        public ?string $targetClass = null,
        public bool $returnArray = false,
    ) {}
}

// Tool mode settings (when using Tools mode)
final readonly class ToolConfig {
    public function __construct(
        public string $name = 'extract_data',
        public string $description = 'Extract data based on instructions',
    ) {}
}

// Schema metadata
final readonly class SchemaConfig {
    public function __construct(
        public string $name = 'default_schema',
        public string $description = '',
        public bool $useObjectReferences = false,
    ) {}
}

// Prompt templates (usually static)
final readonly class PromptConfig {
    public function __construct(
        public array $modePrompts = [...],
        public array $chatStructure = [...],
    ) {}
}
```

### Simplified Main Config

```php
final readonly class StructuredOutputConfig {
    public function __construct(
        public ProcessingConfig $processing = new ProcessingConfig(),
        public OutputSpec $output = new OutputSpec(),
        public ToolConfig $tool = new ToolConfig(),
        public SchemaConfig $schema = new SchemaConfig(),
        public PromptConfig $prompts = new PromptConfig(),
    ) {}
}
```

### Usage

```php
// Before
$config = new StructuredOutputConfig(
    outputMode: OutputMode::Tools,
    maxRetries: 3,
    toolName: 'my_tool',
    toolDescription: 'Extracts user data',
    useObjectReferences: true,
);

// After
$config = new StructuredOutputConfig(
    processing: new ProcessingConfig(maxRetries: 3),
    output: new OutputSpec(mode: OutputMode::Tools),
    tool: new ToolConfig(name: 'my_tool', description: 'Extracts user data'),
    schema: new SchemaConfig(useObjectReferences: true),
);
```

## Benefits

1. **Grouped by concern** - Related settings together
2. **Smaller interfaces** - Each config has 2-4 parameters
3. **Better discoverability** - IDE autocomplete by category
4. **Easier testing** - Test each config group independently
5. **Clearer defaults** - Single source of truth per concern

## Alternative: Builder Pattern

```php
$config = StructuredOutputConfig::builder()
    ->maxRetries(3)
    ->outputMode(OutputMode::Tools)
    ->toolName('my_tool')
    ->build();
```

This maintains the flat API but adds validation and fluent construction.

## File Impact

### New Files

```
Config/
├── ProcessingConfig.php
├── OutputSpec.php
├── ToolConfig.php
├── SchemaConfig.php
├── PromptConfig.php
└── StructuredOutputConfig.php (simplified)
```

### Modified Files

- `ResponseModel.php` - Use config objects instead of duplicating
- `StructuredOutput.php` - Update config construction
- `ResponseIteratorFactory.php` - Access nested configs
- Various pipeline stages - Access specific configs

## Migration Path

1. Create new config value objects (additive)
2. Update `StructuredOutputConfig` to compose them internally
3. Maintain backward compatibility with existing constructor
4. Gradually migrate callers to new structure
5. Deprecate flat constructor parameters

## Risk Assessment

- **Low risk** - Can maintain backward compatibility
- **Incremental** - Can be done in phases
- **Non-breaking** - Old constructor can delegate to new structure

## Estimated Effort

- Create config value objects: 2 hours
- Update StructuredOutputConfig: 2 hours
- Update consumers: 4 hours
- Remove duplication in ResponseModel: 2 hours
- **Total: 10 hours**

## Success Metrics

- No config class with >5 constructor parameters
- Single source of truth for each default value
- Related settings grouped together
- Reduced duplication between Config and ResponseModel
