# Tool Discovery & Critical Gaps Analysis

**Date**: 2025-01-06
**Context**: Analysis of existing capabilities vs requirements for agent tool discovery
**Related**: See `2025-01-06-laravel-job-integration.md` for full implementation plan

---

## Executive Summary

This document captures the critical gap analysis performed before discovering that AgentSpec already covers most needs. The architectural patterns and priorities identified here remain valid, even though the implementation is simpler than originally planned.

**Key Finding**: Most infrastructure already exists, just needs integration and minor enhancements.

---

## What Already EXISTS (Good News!)

### 1. SkillLibrary - Claude-Compatible Skill Loading

**Location**: `packages/addons/src/Agent/Capabilities/Skills/SkillLibrary.php`

**Already Implemented**:
- ✅ Loads SKILL.md with YAML frontmatter
- ✅ Progressive loading (metadata first, body on-demand)
- ✅ Handles resources directory (scripts/, references/, assets/)
- ✅ Supports both legacy `.md` and standard `SKILL.md` format
- ✅ Lazy loading - only loads full skill when accessed

**Example**:
```php
$library = new SkillLibrary('./skills');

// List skills (metadata only)
$skills = $library->listSkills();
// [
//   ['name' => 'code-review', 'description' => 'Reviews code for quality...'],
//   ['name' => 'security-audit', 'description' => 'Analyzes security...'],
// ]

// Load full skill (on demand)
$skill = $library->getSkill('code-review');
// Returns Skill object with full content + resources
```

**Claude Compatibility**: Already fully compatible with Claude skill format!

### 2. LoadSkillTool - Working Skill Loader

**Location**: `packages/addons/src/Agent/Capabilities/Skills/LoadSkillTool.php`

**Already Implemented**:
- ✅ Lists available skills
- ✅ Loads skill content
- ✅ Already implemented as a tool
- ✅ Integrated with SkillLibrary

**Usage**:
```php
$tool = new LoadSkillTool($library);

// List skills
$result = $tool(['list_skills' => true]);
// "Available skills:
//  - [code-review]: Reviews code for quality
//  - [security-audit]: Analyzes security issues"

// Load specific skill
$result = $tool(['skill_name' => 'code-review']);
// Returns full skill content wrapped in <skill> tags
```

**Current Limitation**: Skills are loaded for knowledge, not executed as tools.

### 3. Two-Level Disclosure - Just Implemented

**Location**:
- `packages/addons/src/Agent/Contracts/ToolInterface.php`
- `packages/addons/src/Agent/Tools/BaseTool.php`

**Just Implemented**:
- ✅ `metadata()` method - Level 1 (browse/discovery)
- ✅ `fullSpec()` method - Level 2 (full documentation)
- ✅ Auto-extraction of namespace from tool name
- ✅ Auto-extraction of summary from description
- ✅ Backward compatible with existing tools

**Example**:
```php
class FileReadTool extends BaseTool {
    public function __construct() {
        parent::__construct(
            name: 'file.read',
            description: 'Reads a file from the filesystem. Supports line ranges.'
        );
    }
}

$tool = new FileReadTool();

// Level 1: Metadata (10-30 tokens)
$metadata = $tool->metadata();
// [
//   'name' => 'file.read',
//   'summary' => 'Reads a file from the filesystem.',
//   'namespace' => 'file'
// ]

// Level 2: Full spec (50-200 tokens)
$spec = $tool->fullSpec();
// [
//   'name' => 'file.read',
//   'description' => 'Reads a file from the filesystem. Supports line ranges.',
//   'parameters' => [...],
//   'returns' => 'string'
// ]
```

**Token Efficiency**: 60-85% context reduction through progressive disclosure.

### 4. AgentRegistry - Manages Agent Specs

**Location**: `packages/addons/src/Agent/Registry/AgentRegistry.php`

**Already Implemented**:
- ✅ Loads agents from AGENT.md files
- ✅ Parses frontmatter (YAML metadata)
- ✅ Returns AgentSpec objects
- ✅ Validates agent specifications
- ✅ Lists available agents

**Example**:
```php
$registry = new AgentRegistry('./packages/addons/agents');

// List agents
$agents = $registry->listSpecs();

// Get specific agent
$spec = $registry->getSpec('code-reviewer');
// Returns AgentSpec with:
// - name, description, systemPrompt
// - tools (array of tool names)
// - skills (array of skill names)
// - metadata
```

**Key Discovery**: AgentSpec already has serialization support (`toArray()`)!

### 5. Tools Collection - Manages Tool Lists

**Location**: `packages/addons/src/Agent/Collections/Tools.php`

**Already Implemented**:
- ✅ Readonly collection of tools
- ✅ Add/remove tools immutably
- ✅ Merge tool collections
- ✅ Check tool existence
- ✅ Get tool by name
- ✅ Convert to tool schema

**Example**:
```php
$tools = new Tools(
    new ReadFileTool(),
    new WriteFileTool()
);

// Check if tool exists
$tools->has('file.read'); // true

// Get tool
$tool = $tools->get('file.read');

// Get all tool schemas
$schemas = $tools->toToolSchema();
```

**Current Limitation**: No namespace-based filtering or discovery mechanisms.

---

## CRITICAL Gaps (Must Have)

### Gap 1: Tool Discovery Mechanism ⭐ HIGHEST PRIORITY

**Problem**: Tools exist but there's no way for agents to discover what tools are available without injecting everything into context.

**Missing Components**:
```php
// Need: tool.list tool
tool.list() → returns metadata for all tools
tool.list(namespace: 'file') → returns file.* tools only

// Need: tool.describe tool
tool.describe(name: 'file.read') → returns fullSpec for tool
```

**Why Critical**: Without discovery, we can't scale beyond ~10-20 tools.

**Impact**:
- Current: Must inject all 50+ tool schemas into every request
- With Discovery: Inject 3 discovery tools + agent fetches what it needs

**Token Savings**:
```
Without Discovery:
  50 tools × 150 tokens = 7,500 tokens per request

With Discovery:
  3 discovery tools × 50 tokens = 150 tokens base
  + Agent fetches 3 tools × 150 tokens = 450 tokens
  Total: 600 tokens (92% reduction)
```

**Implementation Need**:
```php
// 1. ToolRegistry - Central registry with factory support
class ToolRegistry {
    private array $tools = [];
    private array $toolFactories = [];

    public function register(string $name, ToolInterface $tool): void;
    public function registerFactory(string $name, callable $factory): void;
    public function get(string $name): ToolInterface;
    public function has(string $name): bool;
    public function names(): array;
    public function listMetadata(?string $namespace = null): array;
}

// 2. ToolListTool - Discovery tool
class ToolListTool extends BaseTool {
    public function __construct(private ToolRegistry $registry) {
        parent::__construct(
            name: 'tool.list',
            description: 'List available tools with metadata'
        );
    }

    public function __invoke(?string $namespace = null): array {
        return $this->registry->listMetadata($namespace);
    }
}

// 3. ToolDescribeTool - Documentation tool
class ToolDescribeTool extends BaseTool {
    public function __construct(private ToolRegistry $registry) {
        parent::__construct(
            name: 'tool.describe',
            description: 'Get full specification for a tool'
        );
    }

    public function __invoke(string $name): array {
        $tool = $this->registry->get($name);
        return $tool->fullSpec();
    }
}
```

**Estimated Effort**: 4-6 hours

### Gap 2: Namespace Pattern Implementation ⭐ HIGH PRIORITY

**Problem**: Tools still use underscore naming (`read_file`, `write_file`) instead of dot notation (`file.read`, `file.write`).

**Current State**:
```php
// Tools registered with underscores
$agent->withTools(
    new ReadFileTool(),      // name: 'read_file'
    new WriteFileTool(),     // name: 'write_file'
    new SearchFilesTool(),   // name: 'search_files'
);
```

**Desired State**:
```php
// Tools registered with namespace pattern
$agent->withTools(
    new ReadFileTool(),      // name: 'file.read'
    new WriteFileTool(),     // name: 'file.write'
    new SearchFilesTool(),   // name: 'file.search'
);
```

**Why Critical**:
- Can't organize tools by domain
- Can't filter tools efficiently (`tool.list(namespace='file')`)
- Doesn't match Claude Code patterns
- Hard to discover related tools

**Provider-Safe Aliasing Need**:

Most tool-calling APIs don't accept dots in tool names:
```php
// Canonical name (internal)
'file.read' → Used in registry, documentation, agent specs

// Provider alias (external)
'file_read' → Used in actual tool schemas sent to LLM

// Need bidirectional mapping
ToolNameAdapter::toAlias('file.read')      → 'file_read'
ToolNameAdapter::toCanonical('file_read')  → 'file.read'
```

**Implementation Need**:
```php
class ToolNamespace {
    public static function parse(string $name): array {
        // 'file.read' → ['namespace' => 'file', 'action' => 'read']
        // 's3.file.read' → ['namespace' => 's3.file', 'action' => 'read']
    }

    public static function toAlias(string $canonical): string {
        // 'file.read' → 'file_read'
        // 's3.file.read' → 's3_file_read'
    }

    public static function toCanonical(string $alias): string {
        // 'file_read' → 'file.read'
        // 's3_file_read' → 's3.file.read'
    }
}
```

**Migration Strategy**:
```php
// Register tools with both names (backward compatibility)
$registry->register('file.read', $tool);
$registry->register('read_file', $tool); // Alias

// Gradually deprecate underscore names
```

**Estimated Effort**: 4-6 hours

### Gap 3: Skills as `task.*` Tools ⭐ HIGH PRIORITY

**Problem**: Skills are loaded via `load_skill` but not exposed as direct executable tools.

**Current Workflow**:
```php
// Step 1: Load skill for knowledge
load_skill(skill_name: 'code-review')
// Returns: "You are an expert code reviewer... [full skill content]"

// Step 2: Agent reads content, then works
// Agent has skill knowledge but must manually apply it
```

**Desired Workflow**:
```php
// Direct skill invocation as tool
task.code_review(target: 'src/Authentication')
// Agent executes skill logic, returns structured result
```

**Why Critical**:
- Skills should be executable tools, not just documentation
- Enables agent-to-agent delegation via skill invocation
- Better for Laravel jobs (skills are naturally serializable)
- Matches Claude Code's task.* pattern

**Implementation Need**:

1. **SkillTool Wrapper** - Convert Skill → ToolInterface
```php
class SkillTool extends BaseTool
{
    public function __construct(private Skill $skill) {
        parent::__construct(
            name: "task.{$skill->name}",
            description: $skill->description
        );
    }

    public function __invoke(mixed ...$args): mixed {
        // Option A: Inject skill content into current agent's context
        // Option B: Spawn subagent with skill context
        // Option C: Execute skill-specific logic (if skill contains code)

        // For now, inject content and let agent work with it
        return $this->skill->render();
    }

    #[\Override]
    public function metadata(): array {
        return [
            ...parent::metadata(),
            'tags' => $this->skill->metadata['tags'] ?? [],
            'capabilities' => $this->skill->metadata['capabilities'] ?? [],
        ];
    }
}
```

2. **Auto-Register Skills in ToolRegistry**
```php
class SkillRegistry
{
    public function registerSkillsInToolRegistry(
        SkillLibrary $library,
        ToolRegistry $registry
    ): void {
        foreach ($library->listSkills() as $skillMeta) {
            // Register factory (for Laravel job reconstruction)
            $registry->registerFactory(
                "task.{$skillMeta['name']}",
                fn() => new SkillTool($library->getSkill($skillMeta['name']))
            );
        }
    }
}
```

3. **Service Provider Integration**
```php
class AgentServiceProvider extends ServiceProvider
{
    public function boot(ToolRegistry $registry, SkillLibrary $library): void
    {
        // Auto-register all skills as task.* tools
        $skillRegistry = new SkillRegistry();
        $skillRegistry->registerSkillsInToolRegistry($library, $registry);
    }
}
```

**Result**:
```php
// Skills become first-class tools
tool.list(namespace: 'task')
// Returns:
// [
//   {name: 'task.code_review', summary: 'Reviews code for quality...'},
//   {name: 'task.security_audit', summary: 'Analyzes security...'},
//   {name: 'task.feature_planning', summary: 'Plans feature implementation...'},
// ]

// Direct invocation
task.code_review(target: 'src/')
```

**Estimated Effort**: 3-4 hours

---

## Recommended Implementation Order

### Phase A: Tool Discovery (Week 1) ⭐ CRITICAL

**Why First**: Foundation for everything else. Enables scaling and Laravel job reconstruction.

**Components**:
1. **ToolRegistry** (2 hours)
   - Central registry with namespace support
   - Factory-based tool creation (for job reconstruction)
   - Metadata listing for discovery

2. **Discovery Tools** (2 hours)
   - `tool.list` - Browse available tools
   - `tool.describe` - Get tool documentation

3. **Integration** (1 hour)
   - Update AgentBuilder to use registry
   - Register core tools
   - Write tests

**Total**: 4-6 hours

**Deliverables**:
- ✅ ToolRegistry with factory support
- ✅ tool.list and tool.describe tools
- ✅ Integrated with AgentBuilder
- ✅ Test coverage

### Phase B: Skills as Tools (Week 1-2) ⭐ HIGH PRIORITY

**Why Second**: Leverages existing SkillLibrary, naturally serializable, enables task.* pattern.

**Components**:
1. **SkillTool Wrapper** (1 hour)
   - Convert Skill → ToolInterface
   - Handle skill invocation

2. **Auto-Registration** (1 hour)
   - Load all skills from SkillLibrary
   - Register as task.* tools in ToolRegistry

3. **Testing** (1 hour)
   - Test skill loading
   - Test skill invocation
   - Test discovery

**Total**: 3-4 hours

**Deliverables**:
- ✅ SkillTool implementation
- ✅ Auto-registration in service provider
- ✅ Skills accessible as task.* tools
- ✅ Test coverage

### Phase C: Namespace Support (Week 2) ⭐ MEDIUM PRIORITY

**Why Third**: Improves organization but not blocking. Can migrate tools incrementally.

**Components**:
1. **Namespace Parsing** (2 hours)
   - ToolNamespace utility class
   - Parse canonical names
   - Generate provider-safe aliases

2. **Tool Migration** (2 hours)
   - Update core tools to use dot notation
   - Keep underscore aliases for compatibility
   - Update examples

3. **Testing** (1 hour)
   - Test namespace parsing
   - Test alias generation
   - Test backward compatibility

**Total**: 4-6 hours

**Deliverables**:
- ✅ ToolNamespace utility
- ✅ Core tools using dot notation
- ✅ Backward compatibility maintained
- ✅ Test coverage

---

## What to SKIP (Non-Essential)

These features are nice-to-have but not critical for MVP:

### 1. Semantic Search `find_tool(purpose="...")`

**Why Skip**:
- Complex to implement (embeddings, vector search)
- Can be added later as enhancement
- Namespace filtering + tool.list covers most needs

**Future Implementation**: When we have 100+ tools, add semantic search layer on top of registry.

### 2. Nested Toolsets

**Why Skip**:
- Complex abstraction
- Namespace pattern solves same problem more simply
- No compelling use case yet

**Alternative**: Use namespace pattern (`s3.file.read`) instead of nested toolsets.

### 3. Git-Based Skill Loading

**Why Skip**:
- Local filesystem is sufficient
- Adds complexity (git cloning, version management)
- SkillLibrary already handles filesystem well

**Future Implementation**: Add as optional SkillLibrary enhancement when needed.

### 4. Skill Versioning

**Why Skip**:
- Not needed for MVP
- SkillLibrary doesn't track versions yet
- Can add when we have multiple skill versions

**Future Implementation**: Add version field to Skill class, support version resolution in SkillLibrary.

### 5. Multi-Level Namespace Depth

**Why Skip**:
- Keep it simple (2 levels max: `namespace.action`)
- More levels add complexity without clear benefit
- Can extend later if needed

**Rule**: Stick to 2 levels unless compelling use case emerges.

---

## Priority Shift: Before vs After Laravel Jobs

### Original Priority (Tool Discovery Focus)

Before considering Laravel job requirements:

```
Priority 1: Tool Discovery (tool.list, tool.describe)
Priority 2: Skills as Tools
Priority 3: Namespace Support
Priority 4: Semantic Search
```

**Rationale**: Focus on agent UX and context efficiency.

### Revised Priority (Laravel Job Focus)

After considering Laravel job/worker deployment:

```
Priority 1: ToolRegistry with Reconstruction ⭐ CRITICAL
Priority 2: AgentSpec Serialization ⭐ CRITICAL
Priority 3: AgentFactory (reconstruction) ⭐ CRITICAL
Priority 4: ExecuteAgentJob Implementation ⭐ CRITICAL
Priority 5: Skills as Tools (naturally serializable) ⭐ HIGH
Priority 6: Tool Discovery (builds on registry) ⭐ HIGH
Priority 7: Namespace Support (organization) ⭐ MEDIUM
```

**Rationale**: Can't deploy to Laravel without serialization working. Everything must be reconstructible from serialized specs.

### Why The Shift?

**Key Insight**: Laravel jobs require:
1. **Serialization** - Store agent specs in queue (Redis/DB)
2. **Reconstruction** - Rebuild exact agent in worker process
3. **Resumability** - Pause for human input, then continue

This fundamentally changes what's "critical" vs "nice-to-have":

- **ToolRegistry** becomes critical (enables reconstruction)
- **Factory pattern** becomes critical (worker rebuilds tools)
- **AgentSpec enhancement** becomes critical (store tool names, not instances)
- **Tool discovery** becomes high (builds on registry foundation)

**Bottom Line**: Must solve serialization first, then add discovery.

---

## Token Efficiency Analysis

### Without Progressive Disclosure (Current State)

Inject all tools into every request:

```
50 tools × 150 tokens (avg full spec) = 7,500 tokens per request
```

**Problems**:
- Wasted tokens on irrelevant tools
- Approaches context limits with many tools
- No way to scale beyond ~50 tools

### With Progressive Disclosure (Implemented)

Tools support `metadata()` and `fullSpec()`:

```
Level 1 (metadata):
  50 tools × 20 tokens = 1,000 tokens

Level 2 (full spec, selective):
  3 relevant tools × 150 tokens = 450 tokens

Total: 1,450 tokens (81% reduction)
```

**Benefits**:
- 81% token savings
- Agent browses, then fetches details
- Scales to 100+ tools

### With Discovery Tools (Proposed)

Agent discovers tools on-demand:

```
Base context:
  3 discovery tools × 50 tokens = 150 tokens

Discovery phase:
  tool.list() returns metadata = 1,000 tokens

Detail phase:
  tool.describe('file.read') × 3 = 450 tokens

Total: 1,600 tokens (79% reduction)
```

**Benefits**:
- Minimal base context
- Agent-driven discovery
- Unlimited scalability

### Comparison Table

| Approach | Base Tokens | Discovery Tokens | Detail Tokens | Total | Savings |
|----------|-------------|------------------|---------------|-------|---------|
| **Inject All** | 7,500 | 0 | 0 | 7,500 | 0% |
| **Progressive Disclosure** | 1,000 | 0 | 450 | 1,450 | 81% |
| **Discovery Tools** | 150 | 1,000 | 450 | 1,600 | 79% |

**Conclusion**: Both progressive disclosure and discovery tools achieve ~80% token reduction. Discovery tools scale better but require registry infrastructure.

---

## Implementation Estimates

### Time Estimates by Phase

| Phase | Components | Estimated Hours | Priority |
|-------|-----------|-----------------|----------|
| **Phase A: Tool Discovery** | ToolRegistry + Discovery Tools | 4-6 hours | ⭐ CRITICAL |
| **Phase B: Skills as Tools** | SkillTool + Auto-Registration | 3-4 hours | ⭐ HIGH |
| **Phase C: Namespace Support** | ToolNamespace + Migration | 4-6 hours | ⭐ MEDIUM |
| **Testing** | Comprehensive test coverage | 4-6 hours | ⭐ HIGH |
| **Documentation** | Architecture + API docs | 2-3 hours | MEDIUM |
| **Total** | All phases | **17-25 hours** | - |

### Critical Path (Minimum Viable Implementation)

**Must Have** (Week 1):
- ToolRegistry (4 hours)
- Discovery Tools (2 hours)
- SkillTool (2 hours)
- Basic Testing (2 hours)

**Total**: 10 hours

**Result**: Agent can discover tools, skills work as task.* tools, foundation for Laravel jobs.

### Full Implementation (Weeks 1-2)

**Week 1** (10 hours):
- ToolRegistry with factories
- Discovery tools (tool.list, tool.describe)
- SkillTool wrapper
- Basic integration testing

**Week 2** (10 hours):
- Namespace support
- Tool migration
- Comprehensive testing
- Documentation

**Total**: 20 hours

**Result**: Complete tool discovery system with namespace support and full test coverage.

---

## Integration Points

### How Components Work Together

```
┌─────────────────────────────────────────────────────────┐
│                    AgentBuilder                          │
│  Creates agents with tools and capabilities              │
└─────────────────────────────────────────────────────────┘
                         ↓ uses
┌─────────────────────────────────────────────────────────┐
│                    ToolRegistry                          │
│  Central registry with factory-based reconstruction      │
│  • register(name, tool)                                  │
│  • registerFactory(name, factory)                        │
│  • get(name) → tool                                      │
│  • listMetadata() → metadata for all tools               │
└─────────────────────────────────────────────────────────┘
       ↓ provides tools          ↓ registers in
┌──────────────────┐    ┌─────────────────────────────────┐
│  Discovery Tools │    │       Core Tools                 │
│  • tool.list     │    │  • file.read, file.write        │
│  • tool.describe │    │  • task.code_review             │
└──────────────────┘    └─────────────────────────────────┘
                                 ↓ includes
                        ┌─────────────────────────────────┐
                        │       SkillLibrary              │
                        │  Loads skills from filesystem   │
                        │  • listSkills() → metadata      │
                        │  • getSkill(name) → Skill       │
                        └─────────────────────────────────┘
                                 ↓ wrapped by
                        ┌─────────────────────────────────┐
                        │       SkillTool                 │
                        │  Converts Skill → ToolInterface │
                        │  Registered as task.* tools     │
                        └─────────────────────────────────┘
```

### Data Flow

1. **Boot Time**:
   ```
   ServiceProvider
   → Register core tools in ToolRegistry
   → Load skills from SkillLibrary
   → Wrap skills in SkillTool
   → Register as task.* tools
   → Register discovery tools
   ```

2. **Agent Creation**:
   ```
   AgentBuilder
   → withToolRegistry($registry)
   → Adds tool.list, tool.describe
   → Agent can discover tools at runtime
   ```

3. **Runtime Discovery**:
   ```
   Agent needs tool
   → Calls tool.list(namespace='file')
   → Gets metadata for file tools
   → Calls tool.describe('file.read')
   → Gets full specification
   → Uses file.read tool
   ```

4. **Laravel Job**:
   ```
   Job serialized with AgentSpec
   → Worker deserializes
   → AgentFactory::fromSpec(spec)
   → Resolves tools from ToolRegistry
   → Reconstructs agent
   → Executes
   ```

---

## Success Criteria

### Must Have (MVP)

- ✅ **ToolRegistry exists** - Central tool management
- ✅ **Discovery tools work** - tool.list and tool.describe
- ✅ **Skills as tools** - task.* tools registered
- ✅ **Factory support** - Tools can be reconstructed for jobs
- ✅ **Basic tests** - Core functionality covered

### Should Have (Full Implementation)

- ✅ **Namespace support** - Dot notation working
- ✅ **Provider aliasing** - Canonical ↔ provider mapping
- ✅ **Tool migration** - Core tools using namespaces
- ✅ **Comprehensive tests** - All edge cases covered
- ✅ **Documentation** - Architecture and API docs

### Nice to Have (Future)

- ⭕ **Semantic search** - find_tool(purpose="...")
- ⭕ **Skill versioning** - Multiple versions supported
- ⭕ **Git-based loading** - Load skills from repos
- ⭕ **Toolset abstraction** - Nested tool collections

---

## Risk Mitigation

### Risk 1: Breaking Changes

**Risk**: Namespace pattern breaks existing tools.

**Mitigation**:
- Keep underscore aliases during transition
- Gradual migration, one namespace at a time
- Comprehensive backward compatibility tests

### Risk 2: Performance Overhead

**Risk**: ToolRegistry lookups add latency.

**Mitigation**:
- Cache tool instances in registry
- Factory only called once per tool
- Benchmark and optimize if needed

### Risk 3: Serialization Issues

**Risk**: Some tools can't be serialized/reconstructed.

**Mitigation**:
- Document which tools support factories
- Provide clear error messages
- Fallback to simpler tools in jobs

### Risk 4: Discovery Complexity

**Risk**: Agents struggle with tool discovery.

**Mitigation**:
- Clear tool naming conventions
- Good tool descriptions
- Examples in tool.list output

---

## Lessons Learned

### 1. Always Check Existing Code First

**Mistake**: Proposed AgentBlueprint without checking if AgentSpec was sufficient.

**Learning**: Always read existing implementations before designing new abstractions.

### 2. Serialization Changes Everything

**Mistake**: Initially prioritized discovery over serialization.

**Learning**: For Laravel jobs, serialization is foundation - build everything on top of it.

### 3. Simpler is Better

**Mistake**: Over-engineered with 3-level disclosure, nested toolsets, etc.

**Learning**: Start simple (2 levels, flat namespaces), add complexity only when needed.

### 4. Leverage Existing Infrastructure

**Insight**: SkillLibrary already does everything we need for skills.

**Action**: Don't reinvent - wrap existing Skill in SkillTool.

---

## Appendices

### A. Related Documentation

- **Laravel Job Integration**: `./2025-01-06-laravel-job-integration.md`
- **Tool Discovery Architecture**: `./2025-01-06-agent-tool-discovery/05-revised-architecture.md`
- **Progressive Disclosure**: `./2025-01-06-agent-tool-discovery/02-progressive-disclosure.md`
- **Two-Level Implementation**: `./2025-01-06-agent-tool-discovery/IMPLEMENTATION_LOG.md`

### B. Existing Code Locations

**Core Classes**:
- `packages/addons/src/Agent/Registry/AgentSpec.php`
- `packages/addons/src/Agent/Registry/AgentRegistry.php`
- `packages/addons/src/Agent/Capabilities/Skills/SkillLibrary.php`
- `packages/addons/src/Agent/Capabilities/Skills/LoadSkillTool.php`
- `packages/addons/src/Agent/Contracts/ToolInterface.php`
- `packages/addons/src/Agent/Tools/BaseTool.php`
- `packages/addons/src/Agent/Collections/Tools.php`

**To Be Created**:
- `packages/addons/src/Agent/Tools/ToolRegistry.php`
- `packages/addons/src/Agent/Tools/ToolListTool.php`
- `packages/addons/src/Agent/Tools/ToolDescribeTool.php`
- `packages/addons/src/Agent/Capabilities/Skills/SkillTool.php`
- `packages/addons/src/Agent/Tools/ToolNamespace.php` (optional)

### C. Quick Reference

**Tool Discovery Commands**:
```php
// List all tools
tool.list()

// List tools in namespace
tool.list(namespace: 'file')

// Get tool details
tool.describe(name: 'file.read')

// List skills
tool.list(namespace: 'task')

// Get skill details
tool.describe(name: 'task.code_review')
```

**ToolRegistry Usage**:
```php
// Register tool instance
$registry->register('file.read', new ReadFileTool());

// Register tool factory (for jobs)
$registry->registerFactory('file.read', fn() => new ReadFileTool());

// Get tool
$tool = $registry->get('file.read');

// List metadata
$metadata = $registry->listMetadata('file');
```

---

**Document Version**: 1.0
**Last Updated**: 2025-01-06
**Status**: Reference Material
**Related**: See Laravel Job Integration document for implementation plan
