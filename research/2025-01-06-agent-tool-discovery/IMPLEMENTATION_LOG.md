# Implementation Log: Two-Level Tool Disclosure

**Date**: 2025-01-06
**Phase**: Phase 1.2 - Two-Level Disclosure
**Status**: ✅ Completed

## Changes Made

### 1. Updated `ToolInterface` Contract

**File**: `packages/addons/src/Agent/Contracts/ToolInterface.php`

Added two new methods to support progressive disclosure:

```php
/**
 * Level 1: Metadata - minimal information for browsing/discovery
 * Returns: name, summary, tags (optional), namespace (optional)
 * Target: ~10-30 tokens
 */
public function metadata(): array;

/**
 * Level 2: Full specification - complete tool documentation
 * Returns: name, description, parameters, usage, examples, errors, notes
 * Target: ~50-200 tokens depending on complexity
 */
public function fullSpec(): array;
```

### 2. Updated `BaseTool` Implementation

**File**: `packages/addons/src/Agent/Tools/BaseTool.php`

**Added methods**:

1. **`metadata()`** - Level 1 disclosure
   - Extracts namespace from tool name (e.g., `file.read` → namespace: `file`)
   - Generates summary from description (first sentence or line)
   - Returns minimal metadata for browsing
   - Can be overridden to add tags, capabilities, constraints

2. **`fullSpec()`** - Level 2 disclosure
   - Returns complete tool documentation
   - Includes name, description, parameters, returns
   - Can be overridden to add examples, errors, notes

3. **Helper methods**:
   - `extractNamespace(string $name): ?string` - Parse namespace from dot notation
   - `extractSummary(string $description): string` - Generate summary from description

### 3. Created Demonstration Example

**File**: `packages/addons/examples/Tools/TwoLevelDisclosureDemo.php`

Demonstrates:
- Two example tools: `FileReadTool` and `TaskCodeReviewTool`
- Level 1 (metadata) usage
- Level 2 (fullSpec) usage with overriding
- Token comparison showing reduction

## Design Decisions

### Why Two Levels (Not Three)?

Based on Claude Code analysis:
- Tool-calling models only see tools passed **per step**
- Progressive disclosure happens via **tool gating** + **metadata-only listing**
- Middle "details" level adds overhead without clear benefit
- Two levels provide clean separation: browse vs full details

### Level 1: Metadata

**Purpose**: Quick browsing and filtering
**Contents**:
- `name` - Tool name (canonical with dots)
- `summary` - One-line description (auto-extracted from first sentence)
- `namespace` - Extracted from name (e.g., `file` from `file.read`)
- `tags` (optional) - For filtering
- `capabilities` (optional) - For semantic search
- `constraints` (optional) - For policy enforcement

**Token target**: 10-30 tokens per tool

### Level 2: Full Specification

**Purpose**: Complete documentation for using the tool
**Contents**:
- `name` - Tool name
- `description` - Full description
- `parameters` - JSON Schema of parameters
- `returns` - Return type
- `usage` (optional) - Example usage string
- `examples` (optional) - Array of code examples with descriptions
- `errors` (optional) - Array of possible errors
- `notes` (optional) - Additional notes

**Token target**: 50-200 tokens depending on complexity

## Backward Compatibility

✅ **Fully backward compatible**

- `toToolSchema()` unchanged - existing code works
- New methods added to interface - existing implementations get default behavior from `BaseTool`
- Tools can gradually adopt new methods by overriding in subclasses

## Usage Examples

### Basic Tool (Uses Defaults)

```php
class SimpleReadTool extends BaseTool
{
    public function __construct()
    {
        parent::__construct(
            name: 'file.read',
            description: 'Reads a file from the filesystem.'
        );
    }

    public function __invoke(string $file_path): string
    {
        return file_get_contents($file_path);
    }
}

$tool = new SimpleReadTool();

// Level 1: Metadata (auto-generated)
$metadata = $tool->metadata();
// ['name' => 'file.read', 'summary' => 'Reads a file from the filesystem.', 'namespace' => 'file']

// Level 2: Full spec (auto-generated from parameters)
$fullSpec = $tool->fullSpec();
// ['name' => 'file.read', 'description' => 'Reads a file...', 'parameters' => [...], 'returns' => 'mixed']
```

### Enhanced Tool (Override for Rich Metadata)

```php
class FileReadTool extends BaseTool
{
    public function __construct()
    {
        parent::__construct(
            name: 'file.read',
            description: 'Reads a file from the filesystem. Supports line ranges for large files.'
        );
    }

    public function __invoke(
        string $file_path,
        ?int $offset = null,
        ?int $limit = null
    ): string {
        // Implementation
    }

    #[\Override]
    public function metadata(): array
    {
        return [
            ...parent::metadata(),
            'tags' => ['file', 'io', 'read'],
            'constraints' => ['read-only'],
        ];
    }

    #[\Override]
    public function fullSpec(): array
    {
        return [
            ...parent::fullSpec(),
            'usage' => 'file.read(file_path="/config.yaml")',
            'examples' => [
                ['code' => 'file.read(file_path="/var/log/app.log")', 'description' => 'Read entire file'],
                ['code' => 'file.read(file_path="/var/log/app.log", offset=100, limit=50)', 'description' => 'Read lines 100-150'],
            ],
            'notes' => [
                'For files > 10MB, use offset/limit to read in chunks',
            ],
        ];
    }
}
```

## Token Savings Example

For a typical tool with 50 tools available:

**Without progressive disclosure** (inject all full specs):
```
50 tools × 150 tokens (average full spec) = 7,500 tokens
```

**With progressive disclosure** (show metadata, load full spec on demand):
```
Level 1: 50 tools × 20 tokens (metadata) = 1,000 tokens
Level 2: 3 relevant tools × 150 tokens = 450 tokens
Total: 1,450 tokens

Savings: 81% reduction
```

## Next Steps

### Immediate (Remaining Phase 1 Tasks)

- [ ] Phase 1.1: Create `ToolNamespace` class for parsing
- [ ] Phase 1.1: Create `ToolNameAdapter` for provider-safe aliases
- [ ] Phase 1.1: Update `ToolRegistry` with namespace support
- [ ] Phase 1.3: Create `tool.list` discovery tool
- [ ] Phase 1.3: Create `tool.describe` documentation tool

### Future Phases

- [ ] Phase 2: Claude Compatibility - Skill loading and registration
- [ ] Phase 3: Semantic Search - `find_tool(purpose="...")` implementation
- [ ] Phase 4: Migration - Convert existing tools to namespace pattern
- [ ] Phase 5: Testing - Comprehensive test suite

## Testing Notes

When tests are ready to run:

```bash
# Test basic functionality
vendor/bin/pest packages/addons/tests/Unit/Tools/

# Test two-level disclosure
php packages/addons/examples/Tools/TwoLevelDisclosureDemo.php
```

Expected output shows:
- Level 1 metadata for both tools (minimal)
- Level 2 full specs (complete documentation)
- Token comparison showing 70-90% reduction

## References

- Implementation Plan: `./05-revised-architecture.md`
- Progressive Disclosure Spec: `./02-progressive-disclosure.md`
- Discovery as Tool Pattern: `./01-discovery-as-tool.md`
