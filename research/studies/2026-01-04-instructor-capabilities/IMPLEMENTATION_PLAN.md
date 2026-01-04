# SubagentRegistry Implementation Plan

## Revised Design Principles

### Code-First Runtime Configuration
**Primary use case:** Define subagents via code at runtime
**Secondary use case:** Load from files for convenience

### Removed Rigid Abstractions
- ❌ **AgentType enum** - Too rigid, replaced with flexible SubagentSpec
- ❌ **DefaultAgentCapability** - Too rigid, replaced with SubagentRegistry

### Core Features
1. ✅ **Preload skills** into system prompt
2. ⏭️ **Skip permissions** - Not supported yet
3. ✅ **Nested subagents** with `max_depth` limit (default: 3)
4. ✅ **Model selection** - Support LLMConfig, named presets, and 'inherit'
5. ✅ **Tool validation** at registration time
6. ✅ **Sequential execution** - Keep simple for now

## Implementation Steps

### Step 1: SubagentSpec Data Structure

**File:** `packages/addons/src/Agent/Subagents/SubagentSpec.php`

**Features:**
- Simple readonly class
- Support code-first construction
- Tool filtering logic
- Model resolution (LLMConfig | string preset | 'inherit' | null)
- Skill loading support

**Usage:**
```php
// Code-first
$spec = new SubagentSpec(
    name: 'code-reviewer',
    description: 'Reviews code for quality and security',
    systemPrompt: 'You are an expert code reviewer...',
    tools: ['read_file', 'search_files', 'grep'],
    model: LLMConfig::fromArray([...]),  // or 'anthropic' or 'inherit'
    skills: ['php-standards', 'security'],
);
```

### Step 2: SubagentRegistry

**File:** `packages/addons/src/Agent/Subagents/SubagentRegistry.php`

**Features:**
- Register specs programmatically
- Load from files (optional)
- Validate on registration
- Simple get/has/all interface

**Usage:**
```php
$registry = new SubagentRegistry();

// Code-first registration
$registry->register(new SubagentSpec(...));

// Optional: Load from file
$registry->loadFromFile('.claude/agents/reviewer.md');

// Optional: Auto-discover
$registry->loadFromDirectory('.claude/agents');
```

### Step 3: SubagentSpecParser (Optional)

**File:** `packages/addons/src/Agent/Subagents/SubagentSpecParser.php`

**Features:**
- Parse YAML frontmatter + markdown body
- Reuse SkillLibrary's parsing logic
- Support JSON format

**Usage:**
```php
$parser = new SubagentSpecParser();
$spec = $parser->parseMarkdownFile('.claude/agents/reviewer.md');
```

### Step 4: Modified SpawnSubagentTool

**File:** `packages/addons/src/Agent/Tools/Subagent/SpawnSubagentTool.php`

**Features:**
- Accept SubagentRegistry instead of AgentCapability
- Support nested subagents with depth tracking
- Preload skills into system prompt
- Handle model inheritance

**Usage:**
```php
$tool = new SpawnSubagentTool(
    parentAgent: $agent,
    registry: $registry,
    skillLibrary: $skills,
    parentLlmProvider: $llmProvider,  // For 'inherit'
    currentDepth: 0,
    maxDepth: 3,
);
```

### Step 5: Update AgentFactory

**File:** `packages/addons/src/Agent/AgentFactory.php`

**Features:**
- Accept SubagentRegistry parameter
- Auto-create default registry if not provided
- Pass maxSubagentDepth parameter

**Usage:**
```php
$registry = new SubagentRegistry();
$registry->register(new SubagentSpec(...));

$agent = AgentFactory::codingAgent(
    workDir: '/project',
    subagentRegistry: $registry,
    maxSubagentDepth: 3,
);
```

### Step 6: Examples and Tests

**Examples:**
- Code-first subagent registration
- File-based loading
- Nested subagent execution
- Model inheritance

**Tests:**
- SubagentSpec validation
- SubagentRegistry operations
- Tool filtering
- Skill preloading
- Depth limiting

## File Structure

```
packages/addons/src/Agent/
├── Subagents/
│   ├── SubagentSpec.php              # NEW - Data structure
│   ├── SubagentRegistry.php          # NEW - Registry service
│   ├── SubagentSpecParser.php        # NEW - File parsing
│   ├── AgentCapability.php           # KEEP - For backward compat
│   └── DefaultAgentCapability.php    # DEPRECATE - Mark as deprecated
├── Tools/Subagent/
│   ├── SpawnSubagentTool.php         # MODIFY - Use registry
│   ├── ResearchSubagentTool.php      # KEEP - Specialized tool
│   └── SelfCriticSubagentTool.php    # KEEP - Specialized tool
├── Enums/
│   └── AgentType.php                 # DEPRECATE - Mark as deprecated
└── AgentFactory.php                  # MODIFY - Add registry support

.claude/agents/                        # OPTIONAL - Example specs
├── code-reviewer.md
├── test-generator.md
└── api-designer.md
```

## Implementation Order

1. ✅ **SubagentSpec class** - Core data structure
2. ✅ **SubagentRegistry class** - Registry service
3. ✅ **SubagentSpecParser class** - File parsing
4. ✅ **Modify SpawnSubagentTool** - Use registry, add depth tracking
5. ✅ **Update AgentFactory** - Add registry parameter
6. ✅ **Create examples** - Code-first and file-based
7. ✅ **Write tests** - Comprehensive test coverage
8. ✅ **Update documentation** - AGENT.md
9. ✅ **Deprecation markers** - Add @deprecated to old classes

## Model Selection Design

### SubagentSpec Model Field

```php
class SubagentSpec {
    public function __construct(
        // ... other fields
        public LLMConfig|string|null $model = null,
    ) {}
}
```

**Supported values:**
- `LLMConfig` object - Runtime configuration
- `'anthropic'` - Named preset from config/llm.php
- `'inherit'` - Use parent's LLM provider
- `null` - Use default preset

### Model Resolution Logic

```php
class SpawnSubagentTool {
    private ?LLMProvider $parentLlmProvider;

    public function __construct(
        // ... other params
        ?LLMProvider $parentLlmProvider = null,
    ) {
        $this->parentLlmProvider = $parentLlmProvider;
    }

    private function createSubagent(SubagentSpec $spec): Agent {
        $llmProvider = $this->resolveLlmProvider($spec->model);
        $driver = new ToolCallingDriver(llm: $llmProvider);
        // ...
    }

    private function resolveLlmProvider(LLMConfig|string|null $model): LLMProvider {
        return match(true) {
            $model instanceof LLMConfig => LLMProvider::fromConfig($model),
            $model === 'inherit' => $this->parentLlmProvider ?? LLMProvider::new(),
            is_string($model) => LLMProvider::using($model),
            default => LLMProvider::new(),
        };
    }
}
```

### Example Usage

```php
// Runtime LLMConfig
$spec = new SubagentSpec(
    model: new LLMConfig(
        apiUrl: 'https://api.anthropic.com/v1',
        apiKey: getenv('ANTHROPIC_API_KEY'),
        model: 'claude-sonnet-4-20250514',
        // ...
    ),
);

// Named preset
$spec = new SubagentSpec(
    model: 'anthropic',  // From config/llm.php
);

// Inherit from parent
$spec = new SubagentSpec(
    model: 'inherit',  // Use same as parent agent
);

// Use default
$spec = new SubagentSpec(
    model: null,  // Uses defaultPreset from config
);
```

## Backward Compatibility

### Deprecation Strategy

**Phase 1 (Current):**
- Keep AgentType and DefaultAgentCapability
- Mark with @deprecated annotations
- Update docs to show only SubagentSpec

**Phase 2 (Next minor):**
- Add runtime deprecation warnings
- Provide migration guide

**Phase 3 (Next major):**
- Remove deprecated classes
- Clean up code

### Migration Path

**Old way:**
```php
$capability = new DefaultAgentCapability();
$tool = new SpawnSubagentTool($agent, $capability);

// Usage
spawn_subagent(agent_type: 'code', prompt: '...')
```

**New way:**
```php
$registry = new SubagentRegistry();
$registry->register(new SubagentSpec(
    name: 'code',
    description: 'Full coding capabilities',
    systemPrompt: 'You are a coding assistant...',
    tools: ['bash', 'read_file', 'write_file', 'edit_file', 'todo_write'],
));
$tool = new SpawnSubagentTool($agent, $registry);

// Usage
spawn_subagent(subagent: 'code', prompt: '...')
```

## Testing Strategy

### Unit Tests

**SubagentSpecTest.php:**
- Construction validation
- Tool filtering
- Model resolution
- Skill detection

**SubagentRegistryTest.php:**
- Registration
- Retrieval
- Validation
- File loading

**SubagentSpecParserTest.php:**
- YAML parsing
- Markdown extraction
- JSON parsing
- Error handling

### Integration Tests

**SpawnSubagentToolTest.php:**
- Subagent creation
- Skill preloading
- Model inheritance
- Depth limiting
- Nested execution

### Example Tests

**ExampleSubagentsTest.php:**
- Code-first examples work
- File-based examples work
- All examples are valid

## Documentation Updates

### AGENT.md Updates

1. Remove AgentType enum references
2. Add SubagentRegistry section
3. Add SubagentSpec examples
4. Update SpawnSubagentTool docs
5. Add migration guide

### New Sections

```markdown
## Subagent Registry

### Defining Subagents

#### Code-First (Recommended)

```php
use Cognesy\Addons\Agent\Subagents\{SubagentSpec, SubagentRegistry};

$registry = new SubagentRegistry();
$registry->register(new SubagentSpec(
    name: 'code-reviewer',
    description: 'Expert code reviewer',
    systemPrompt: 'You are a senior code reviewer...',
    tools: ['read_file', 'search_files'],
    model: 'anthropic',
    skills: ['php-standards'],
));
```

#### File-Based (Optional)

**.claude/agents/code-reviewer.md:**
```markdown
---
name: code-reviewer
description: Expert code reviewer
tools: read_file, search_files
model: anthropic
skills: php-standards
---

You are a senior code reviewer...
```

```php
$registry = new SubagentRegistry();
$registry->loadFromFile('.claude/agents/code-reviewer.md');
```

### Model Selection

Subagents support flexible model configuration...
```

## Success Criteria

- ✅ Code-first subagent definition works
- ✅ File-based loading works (optional)
- ✅ Skills are preloaded into system prompt
- ✅ Nested subagents respect max_depth
- ✅ Model inheritance works correctly
- ✅ Tool validation catches errors
- ✅ All tests pass
- ✅ Documentation is complete
- ✅ Examples are clear and working
- ✅ Backward compatibility maintained

## Non-Goals (Deferred)

- ❌ Parallel subagent execution
- ❌ Permission system
- ❌ Subagent result streaming
- ❌ Cross-subagent communication
- ❌ Subagent state persistence

These can be added in future iterations without breaking changes.
