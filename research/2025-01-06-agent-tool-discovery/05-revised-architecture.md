# Revised Architecture: Namespaced Tools & Claude Compatibility

**Date**: 2025-01-06
**Status**: Implementation Plan
**Priority**: HIGH - Claude compatibility is CRITICAL

## Executive Summary

Based on discussion and research into tool-calling patterns, we are revising the tool discovery architecture to:

1. **Adopt canonical namespaces** (e.g., `file.read`, `task.code_review`) with a **provider-safe alias** layer
2. **Simplify to 2 disclosure levels** (List → Full Spec, skip middle level)
3. **Add semantic search** via `find_tool(purpose="...")` with a **local index** (no nested LLM call by default)
4. **Treat agents as `task.*` tools** (dispatching to `AgentRegistry`)
5. **Support Claude-compatible skill/agent formats** (frontmatter-first, metadata.yaml optional)
6. **Gate tools per step** to reduce context and enforce policy (progressive exposure)

---

## Key Design Decisions

### 1. Namespace Pattern (Dot Notation)

**Old approach**:
```
read_file
write_file
discover_file_tools
```

**New approach**:
```
file.read
file.write
file.search
tool.list(namespace="file")  # Discovery within namespace
```

**Benefits**:
- Clear hierarchy
- Familiar (Python, JavaScript, Java conventions)
- Easy grouping and filtering
- Scalable to nested namespaces (`s3.file.read`)

### 1.1 Provider Constraints & Aliasing (Critical)

Most modern tool-calling APIs **do not accept dots** in tool names, and many restrict names to a short ASCII pattern (letters, numbers, underscore, dash). To stay portable across OpenAI, Anthropic, and local drivers:

- **Canonical name**: `file.read`, `task.code_review` (used internally)
- **Provider alias**: `file_read`, `task_code_review` (used in tool schemas)

**Rule**: Always register tools with a canonical name, then generate a provider-safe alias per driver. The alias must be **stable and reversible** to map tool calls back to the canonical name.

Example mapping:
```
canonical: file.read        -> alias: file_read
canonical: task.code_review -> alias: task_code_review
canonical: s3.file.list     -> alias: s3_file_list
```

This lets us keep namespaces in our registry and UX while remaining compatible with provider constraints.

### 2. Two-Level Disclosure (Not Three)

**Design constraint**: Tool-calling models only see the tools we pass **each step**. Progressive disclosure must happen by **tool gating** + **metadata-only listing**, not by magical late-loading inside a single step.

We still want two levels:
- **Level 1**: Metadata list (name + short summary, plus tags/capabilities)
- **Level 2**: Full spec (parameters, examples, errors, and optional skill content)

**Our simplified approach**:
```
Level 1 (Browse):
  tool.list(namespace="file") → [{name: "file.read", summary: "Read file contents"}, ...]

Level 2 (Full Spec):
  tool.describe(name="file.read") → {description, parameters, examples, errors}
  OR full spec included automatically when the tool is enabled for that step

**Note**: The model can only call tools that are included in the current step's tool list. "Full spec" here means the tool schema passed to the model, not a separate late-loaded document.
```

**Removed**: Middle "details" level - not necessary for usability and adds token overhead.

### 3. Semantic Tool Search

**New tool**: `find_tool(purpose="...")`

```python
# Instead of browsing namespaces
find_tool(purpose="read a file")
→ Suggests: file.read (relevance: 0.95)

find_tool(purpose="search codebase for functions")
→ Suggests: file.search (relevance: 0.82), task.explore (relevance: 0.78)
```

**Implementation**: Prefer **local embeddings + vector index** (or BM25 + keyword) to avoid nested model calls and reduce latency. LLM-based matching can be a fallback outside the agent loop if needed.

### 4. Agents as `task.*` Tools

**Consistent with Claude Code's "Task tool" pattern**, but implemented as a thin wrapper over `AgentRegistry` and `SpawnSubagentTool`:

```python
# Old (special abstraction)
discover_agents()
spawn_subagent(name="code_reviewer")

# New (agents ARE tools)
task.list()
task.code_review(target="src/")
task.explore(query="find API routes")
task.plan(feature="OAuth implementation")
```

**Benefits**:
- No special "agent" vs "tool" distinction at the API boundary
- Action-oriented naming for discovery and UX
- Maps cleanly to existing `AgentRegistry` and `UseSubagents`

### 5. Skills = Documented Tool Bundles

**Insight**: Skills are not fundamentally different from tools. They're tools with:
- Rich documentation
- Associated resources (scripts, data)
- Context information

**Implementation**: Skills are tools that bundle context.

### 6. Context Management & Tool Gating

**Reality**: Tools are part of the **prompt context**. The model only knows the tools we pass **this step**.

**Rules**:
- **Gate tools per step**: pass only what the model needs (or what policy allows).
- **Avoid stuffing long skill content into tool descriptions**. Inject skill content into **system or scratchpad context** only when activated.
- **Cache tool schemas**; reuse and only diff when the tool list changes.

**Benefits**:
- Lower token usage and faster inference
- Cleaner reasoning surface for the model
- Stronger policy enforcement (e.g., no write tools in read-only modes)

---

## CRITICAL: Claude Ecosystem Compatibility

### Requirement

**We MUST accept Claude's agent description and skill packaging standards.**

There are **thousands** of Claude skills and agents already created by the community. We need compatibility to leverage this ecosystem.

### Claude Skill Standard Format (Practical Compatibility)

In practice, **the de-facto format is a `SKILL.md` with YAML frontmatter**. Some repos also include `metadata.yaml`, but it is not required. We should be **lenient** and support both.

**Minimal structure (most common)**:
```
my-skill/
├── SKILL.md              # Main skill definition (frontmatter + content)
└── resources/            # Optional bundled resources
```

**SKILL.md format (frontmatter-first)**:
```markdown
---
name: code-reviewer
description: Reviews code for quality, bugs, and security issues
tags: [code-quality, review, security]
version: 1.0.0
author: community
tools: [file.read, file.search]
constraints: [read-only]
---

# Code Reviewer Skill

This skill performs comprehensive code review...
```

**Optional metadata.yaml** (if present, merge with frontmatter, frontmatter wins):
```yaml
name: code-reviewer
description: Reviews code for quality, bugs, and security issues
version: 1.0.0
author: community
tags: [code-quality, review, security]
capabilities:
  - code-analysis
  - bug-detection
  - security-analysis
constraints:
  - read-only
tools:
  - file.read
  - file.search
```

### Our Compatibility Requirements

1. **Load Claude skills from standard format**
   - Parse SKILL.md frontmatter
   - Merge metadata.yaml if present (frontmatter wins)
   - Bundle resources if present (best-effort, optional)

2. **Expose as `task.*` tools**
   - `task.code_review()` from code-reviewer skill
   - `task.plan()` from planner skill
   - `task.explore()` from explorer skill

3. **Support skill metadata**
   - Version tracking
   - Author attribution
   - Tag-based filtering
   - Capability declarations

4. **Progressive loading**
   - Level 1: Load metadata only (frontmatter)
   - Level 2: Load full SKILL.md content on demand
   - Resources load on-demand

5. **Agent format compatibility**
   - Preserve the existing `AgentRegistry` markdown format in `packages/addons/AGENT.md`
   - Allow mapping `agent` specs to `task.*` wrappers without breaking current `spawn_subagent`

---

## Built-in Tool Conventions

### Provider-Safe Discovery & Help

Tool-calling APIs do not support "methods" like `.help()`; they only see **tools**. So discovery and documentation must be **explicit tools**:

```python
# Discovery
tool.list(namespace="file")        # returns metadata list
tool.list(tags=["security"])       # optional filters

# Documentation
tool.describe(name="file.read")    # returns full spec (canonical name)

# Execution (provider-safe alias)
file_read(path="/config.yaml")
file_write(path="/out.txt", content="")
```

**Recommended pattern**:
- **Canonical names** in metadata and docs: `file.read`, `task.code_review`
- **Provider alias** in actual tool schema: `file_read`, `task_code_review`
- **`tool.list` returns both** (canonical name + alias) to aid debugging
- **Single discoverability surface**: `tool.list`, `tool.describe`

### Built-in Tool Namespaces (Canonical)

```python
# File operations (canonical)
file.read
file.write
file.search
file.dir

# Task/Agent operations
task.list
task.code_review
task.explore
task.plan
```

**Alias examples (provider-safe tool names)**:
```
file.read        -> file_read
task.code_review -> task_code_review
```

### Nested Namespaces

Nested namespaces are fine **internally**, but keep aliases flat for providers:
```
s3.file.read   -> s3_file_read
api.github.repo.get -> api_github_repo_get
```

**Convention**: Keep canonical depth at 2–3 levels and avoid deep trees unless necessary.

---

## Implementation Plan

### Phase 1: Core Infrastructure (Week 1)

#### 1.1 Tool Namespace Support

**Goal**: Support dot notation for tools

**Tasks**:
- [ ] Create `ToolNamespace` class
  - Parse tool names with dots (`file.read` → namespace: `file`, action: `read`)
  - Support nested namespaces (`s3.file.read` → namespace: `s3.file`, action: `read`)
  - Validate namespace/action naming rules
- [ ] Add `ToolNameAdapter` per provider
  - Canonical name ↔ provider alias mapping
  - Enforce provider name constraints (length, allowed chars)

- [ ] Update `ToolRegistry`
  - Support namespace-based registration
  - Support namespace-based lookup
  - Add `listByNamespace(string $namespace): array`

- [ ] Create discovery tools
  - `tool.list(namespace=...)` → list tools in namespace
  - `tool.describe(name=...)` → get tool documentation

**Implementation**:
```php
// packages/instructor/src/Tools/ToolNamespace.php
class ToolNamespace
{
    public static function parse(string $fullName): array {
        // Parse "file.read" → ["file", "read"]
        // Parse "s3.file.read" → ["s3.file", "read"]
    }

    public static function validate(string $name): bool {
        // Valid: lowercase, dots, underscores
        // Invalid: spaces, special chars
    }
}

// Enhanced ToolRegistry
class ToolRegistry
{
    public function register(string $name, Tool $tool): void {
        // Support both "file.read" and legacy "read_file"
    }

    public function listByNamespace(string $namespace): array {
        // Get all tools in namespace
        // e.g., "file" → [file.read, file.write, file.search]
    }

    public function getNamespaces(): array {
        // Get all unique namespaces
        // ["file", "db", "task", "batch"]
    }
}

// ToolNameAdapter (provider-safe aliasing)
final class ToolNameAdapter
{
    public function toAlias(string $canonical): string {
        // file.read -> file_read (provider-safe)
    }

    public function toCanonical(string $alias): string {
        // file_read -> file.read
    }
}
```

#### 1.2 Two-Level Disclosure

**Goal**: Simplify from 3 levels to 2

**Tasks**:
- [ ] Remove Level 2 (middle "details" level)
- [ ] Update `ToolSpec` to support Level 1 (metadata) and Level 2 (full spec)
- [ ] Implement `tool.list` and `tool.describe`
- [ ] Gate tools per step (only pass the minimal tool list)

**Implementation**:
```php
class ToolSpec
{
    // Level 1: Metadata
    public function metadata(): array {
        return [
            'name' => $this->name,
            'summary' => $this->shortDescription,
        ];
    }

    // Level 2: Full spec
    public function fullSpec(): array {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'parameters' => $this->parameters,
            'usage' => $this->usage,
            'examples' => $this->examples,
            'errors' => $this->errors,
            'notes' => $this->notes,
        ];
    }
}
```

#### 1.3 Namespace Help Pattern

**Goal**: Implement explicit discovery tools (`tool.list`, `tool.describe`)

**Tasks**:
- [ ] Create `ToolList` and `ToolDescribe` tools
- [ ] Support namespace filtering, tags, capability filters
- [ ] Return metadata only by default; full spec on demand

**Implementation**:
```php
class ToolList implements Tool
{
    public function __invoke(array $args): array {
        $namespace = $args['namespace'] ?? null;
        return $this->registry->listByNamespace($namespace);
    }
}

class ToolDescribe implements Tool
{
    public function __invoke(array $args): array {
        $name = $args['name'];
        return $this->registry->get($name)->fullSpec();
    }
}
```

**Decision**: Use explicit `tool.list` + `tool.describe` to avoid provider name constraints and method-like call ambiguity.

---

### Phase 2: Claude Compatibility (Week 2) **CRITICAL**

#### 2.1 Claude Skill Format Support

**Goal**: Load Claude skills from standard format

**Tasks**:
- [ ] Create `SkillLoader` to parse SKILL.md files
  - Parse frontmatter (YAML metadata)
  - Parse markdown content
  - Load bundled resources from `resources/` directory (best-effort)
- [ ] Extend existing `SkillLibrary` / `LoadSkillTool` (in `packages/addons`) instead of new loaders when possible

- [ ] Create `SkillSpec` extending `ToolSpec`
  - Additional fields: version, author, tags
  - Resource bundling support
  - Capability declarations

- [ ] Support skill loading from:
  - Local filesystem
  - Optional Git-based repositories (future)
  - Optional skill registries (future)

**Implementation**:
```php
// packages/addons/src/Agent/Skills/SkillLoader.php
class SkillLoader
{
    public function loadFromFile(string $path): SkillSpec {
        // Parse SKILL.md
        $content = file_get_contents($path);

        // Extract frontmatter
        preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', $content, $matches);
        $metadata = Yaml::parse($matches[1]);
        // If metadata.yaml exists, merge it (frontmatter wins)
        $body = $matches[2];

        return new SkillSpec(
            name: $metadata['name'],
            description: $metadata['description'],
            version: $metadata['version'] ?? '1.0.0',
            author: $metadata['author'] ?? 'unknown',
            tags: $metadata['tags'] ?? [],
            capabilities: $metadata['capabilities'] ?? [],
            constraints: $metadata['constraints'] ?? [],
            tools: $metadata['tools'] ?? [],
            content: $body,
            resources: $this->loadResources(dirname($path)),
        );
    }

    public function loadFromDirectory(string $dir): array {
        // Load all SKILL.md files from directory
        // Supports Claude skill repository format
    }

    public function loadFromGithub(string $repo, string $path): SkillSpec {
        // Load skill from GitHub repository
        // Format: "owner/repo/path/to/SKILL.md"
    }
}
```

#### 2.2 Skill Registry

**Goal**: Register Claude skills as `task.*` tools

**Tasks**:
- [ ] Create `SkillRegistry` extending `ToolRegistry`
  - Support version tracking
  - Support tag-based filtering
  - Support capability-based search
- [ ] Integrate with existing `AgentRegistry` so skills and agents share discovery paths

- [ ] Auto-register skills as `task.*` tools
  - `task.code_review` from code-reviewer.SKILL.md
  - `task.explore` from explorer.SKILL.md
  - etc.

**Implementation**:
```php
// packages/addons/src/Agent/Skills/SkillRegistry.php
class SkillRegistry extends ToolRegistry
{
    public function registerSkill(SkillSpec $skill): void {
        $toolName = "task.{$skill->name}";
        $tool = new SkillTool($skill);
        $this->register($toolName, $tool);
    }

    public function findByTag(string $tag): array {
        // Find skills by tag
    }

    public function findByCapability(string $capability): array {
        // Find skills by capability
    }

    public function loadFromDirectory(string $dir): void {
        $loader = new SkillLoader();
        $skills = $loader->loadFromDirectory($dir);

        foreach ($skills as $skill) {
            $this->registerSkill($skill);
        }
    }
}
```

#### 2.3 Progressive Skill Loading

**Goal**: Load skill metadata upfront, full content on-demand, and only pass minimal tool schemas per step

**Tasks**:
- [ ] Load only frontmatter initially (~100 tokens)
- [ ] Load full SKILL.md when skill invoked (<5k tokens)
- [ ] Load resources on-demand
- [ ] Inject skill content into system/context only when activated

**Implementation**:
```php
class SkillTool implements Tool
{
    private bool $contentLoaded = false;
    private ?string $fullContent = null;

    public function __invoke(array $args): mixed {
        // Lazy load full content when invoked
        if (!$this->contentLoaded) {
            $this->fullContent = $this->loadFullContent();
            $this->contentLoaded = true;
        }

        // Execute skill with full context (inject content into prompt/context)
        return $this->executeSkill($args);
    }

    public function getSpec(): ToolSpec {
        // Return only metadata (Level 1)
        // Don't load full content unless invoked
        return $this->spec->metadata();
    }
}
```

#### 2.4 Skill Discovery

**Goal**: Browse and discover available skills

**Tasks**:
- [ ] `tool.list(namespace="task")` lists all available skills (metadata only)
- [ ] `tool.describe(name="task.code_review")` shows full skill documentation
- [ ] `find_tool(purpose="review code")` suggests relevant skills

**Implementation**:
```php
class ToolList implements Tool
{
    public function __invoke(array $args): array {
        $namespace = $args['namespace'] ?? null;
        return $this->registry->listByNamespace($namespace);
    }
}
```

---

### Phase 3: Semantic Search (Week 3)

#### 3.1 `find_tool(purpose)` Implementation

**Goal**: Semantic tool discovery

**Tasks**:
- [ ] Create `FindToolTool` with semantic matching
- [ ] Build local index from tool metadata (embeddings or BM25)
- [ ] Rebuild index on registry changes
- [ ] Return ranked results with relevance scores

**Implementation**:
```php
class FindToolTool implements Tool
{
    public function __construct(
        private ToolRegistry $registry,
        private ToolSearchIndex $index,  // Local vector/keyword index
    ) {}

    public function __invoke(array $args): array {
        $purpose = $args['purpose'];

        // Query local index (vector + keyword fallback)
        $matches = $this->index->search($purpose, limit: 3);
        return ['matches' => $matches];
    }
}

class ToolMatch
{
    public function __construct(
        public string $name,
        public float $relevance,  // 0.0 - 1.0
        public string $reason,
    ) {}
}
```

**Usage**:
```php
find_tool(purpose: "read configuration files")
→ [
  {name: "file.read", relevance: 0.95, reason: "Directly reads file contents"},
  {name: "file.search", relevance: 0.72, reason: "Can find config files"},
]
```

---

### Phase 4: Migration & Examples (Week 4)

#### 4.1 Migrate Existing Tools to Namespace Pattern

**Tasks**:
- [ ] Migrate file tools: `read_file` → `file.read`
- [ ] Migrate agent tools: `spawn_subagent` → `task.*`
- [ ] Update all examples
- [ ] Maintain backward compatibility (keep old names as aliases)
- [ ] Generate provider-safe aliases (`file.read` → `file_read`)

**Implementation**:
```php
// Backward compatibility
$registry->register('file.read', $readFileTool);
$registry->register('read_file', $readFileTool);  // Alias for compatibility
```

#### 4.2 Load Community Skills

**Tasks**:
- [ ] Create `skills/` directory for community skills
- [ ] Load skills from directory on agent build
- [ ] Document how to add new skills
- [ ] Create example skills following Claude format

**Directory structure**:
```
skills/
├── code-reviewer/
│   ├── SKILL.md
│   └── resources/
├── explorer/
│   ├── SKILL.md
│   └── resources/
├── planner/
│   ├── SKILL.md
│   └── resources/
└── security-analyzer/
    ├── SKILL.md
    └── resources/
```

**Usage**:
```php
$skillRegistry = new SkillRegistry();
$skillRegistry->loadFromDirectory(__DIR__ . '/skills');

$agent = AgentBuilder::base()
    ->withRegistry($skillRegistry)
    ->build();

// Now agent can use:
// task.code_review()
// task.explore()
// task.plan()
// task.security_analyze()
```

#### 4.3 Update Documentation

**Tasks**:
- [ ] Update all examples to use namespaced tools
- [ ] Document Claude skill compatibility
- [ ] Create guide for loading community skills
- [ ] Create guide for creating new skills

---

### Phase 5: Testing & Validation (Week 5)

#### 5.1 Unit Tests

**Tasks**:
- [ ] Test namespace parsing
- [ ] Test help tool generation
- [ ] Test skill loading from SKILL.md
- [ ] Test progressive loading
- [ ] Test semantic search

#### 5.2 Integration Tests

**Tasks**:
- [ ] Test loading real Claude skills
- [ ] Test agent using namespaced tools
- [ ] Test backward compatibility
- [ ] Test find_tool() with real queries

#### 5.3 Performance Tests

**Tasks**:
- [ ] Measure context usage with progressive loading
- [ ] Measure skill loading performance
- [ ] Optimize semantic search

---

## Success Metrics

### Functional Requirements

- [ ] All tools accessible via namespace pattern (`file.read`, `db.query`)
- [ ] Discovery tools work (`tool.list`, `tool.describe`)
- [ ] Claude skills load from standard SKILL.md format
- [ ] Progressive loading measurably reduces context (target: 60–85%)
- [ ] Semantic search works (`find_tool(purpose="...")`)
- [ ] Backward compatibility maintained
- [ ] Provider-safe aliases generated for all tools

### Performance Requirements

- [ ] Skill metadata loading < 100 tokens
- [ ] Full skill loading < 5k tokens
- [ ] find_tool() response < 2 seconds
- [ ] Skill loading from directory < 1 second for 100 skills

### Compatibility Requirements

- [ ] Can load any Claude skill following Anthropic's format
- [ ] Skills work identically to Claude Code
- [ ] Skill metadata parsed correctly
- [ ] Resources loaded on-demand

---

## Critical Path

**MUST HAVE** (Core functionality):
1. Namespace pattern implementation
2. Claude skill format support
3. Discovery tools (`tool.list`, `tool.describe`)
4. Progressive loading

**SHOULD HAVE** (Enhanced UX):
1. Semantic search (`find_tool()`)
2. Nested namespaces (3 levels deep)
3. Skill version tracking

**NICE TO HAVE** (Future enhancements):
1. Skill package registry
2. GitHub skill loading
3. Skill hot-reloading

---

## References

- [Anthropic Agent Skills Best Practices](https://platform.claude.com/docs/en/agents-and-tools/agent-skills/best-practices)
- [Progressive Disclosure in Claude Skills](https://www.mcpjam.com/blog/claude-agent-skills)
- [Claude Tool Use API](https://platform.claude.com/docs/en/agents-and-tools/tool-use/overview)
- [Tool Search Feature](https://www.anthropic.com/engineering/advanced-tool-use)

---

## Next Actions

1. **Review this plan** with team
2. **Validate Claude skill format** - download and test real skills
3. **Prototype namespace pattern** - proof of concept
4. **Begin Phase 1** - Core infrastructure
5. **Prioritize Phase 2** - Claude compatibility is CRITICAL for ecosystem access
6. **Map to existing components** - `AgentRegistry`, `SkillLibrary`, `LoadSkillTool`
