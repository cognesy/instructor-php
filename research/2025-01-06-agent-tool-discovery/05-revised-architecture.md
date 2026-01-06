# Revised Architecture: Namespaced Tools & Claude Compatibility

**Date**: 2025-01-06
**Status**: Implementation Plan
**Priority**: HIGH - Claude compatibility is CRITICAL

## Executive Summary

Based on discussion and research into Anthropic's patterns, we are revising the tool discovery architecture to:

1. **Use dot notation namespacing** (`file.read`, `task.code_review`)
2. **Simplify to 2 disclosure levels** (List → Full Spec, skip middle level)
3. **Add semantic search** via `find_tool(purpose="...")`
4. **Treat agents as `task.*` tools** (consistent with Claude Code)
5. **CRITICAL: Accept Claude's skill/agent description standards** for ecosystem compatibility

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
file.list         # Discovery within namespace
```

**Benefits**:
- Clear hierarchy
- Familiar (Python, JavaScript, Java conventions)
- Easy grouping and filtering
- Scalable to nested namespaces (`s3.file.read`)

### 2. Two-Level Disclosure (Not Three)

**Research finding**: Anthropic uses 2 effective levels for skills:
- **Level 1**: Metadata (name + description)
- **Level 2**: Full content (loads when invoked)

**Our simplified approach**:
```
Level 1 (Browse):
  file.list() → [{name: "read", summary: "Read file contents"}, ...]

Level 2 (Full Spec):
  file.read.help() → {description, parameters, examples, errors}
  OR spec loaded automatically when calling file.read()
```

**Removed**: Middle "details" level - not necessary based on Anthropic's pattern.

### 3. Semantic Tool Search

**New tool**: `find_tool(purpose="...")`

```python
# Instead of browsing namespaces
find_tool(purpose="read a file")
→ Suggests: file.read (relevance: 0.95)

find_tool(purpose="search codebase for functions")
→ Suggests: file.search (relevance: 0.82), task.explore (relevance: 0.78)
```

**Implementation**: Use embeddings or LLM-based semantic matching.

### 4. Agents as `task.*` Tools

**Consistent with Claude Code's "Task tool" pattern**:

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
- No special "agent" vs "tool" distinction
- Action-oriented naming
- Matches Claude Code terminology

### 5. Skills = Documented Tool Bundles

**Insight**: Skills are not fundamentally different from tools. They're tools with:
- Rich documentation
- Associated resources (scripts, data)
- Context information

**Implementation**: Skills are tools that bundle context.

---

## CRITICAL: Claude Ecosystem Compatibility

### Requirement

**We MUST accept Claude's agent description and skill packaging standards.**

There are **thousands** of Claude skills and agents already created by the community. We need compatibility to leverage this ecosystem.

### Claude Skill Standard Format

Based on [Anthropic's skill documentation](https://platform.claude.com/docs/en/agents-and-tools/agent-skills/best-practices):

**File structure**:
```
my-skill/
├── SKILL.md              # Main skill definition
├── metadata.yaml         # Skill metadata (name, description, version)
├── resources/            # Optional bundled resources
│   ├── examples/
│   ├── scripts/
│   └── data/
└── tests/                # Optional skill tests
```

**SKILL.md format**:
```markdown
---
name: code-reviewer
description: Reviews code for quality, bugs, and security issues
version: 1.0.0
author: community
tags: [code-quality, review, security]
---

# Code Reviewer Skill

This skill performs comprehensive code review...

## When to Use

Use this skill when you need to:
- Review pull requests
- Analyze code quality
- Find security vulnerabilities

## How It Works

1. Analyzes code structure
2. Checks for common patterns
3. Reports findings with severity

## Usage Examples

```python
task.code_review(target="src/api/")
```

## Available Commands

- `review`: Full code review
- `quick_check`: Fast quality scan
- `security_scan`: Security-focused analysis
```

**Metadata format** (metadata.yaml or frontmatter):
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
  - grep
```

### Our Compatibility Requirements

1. **Load Claude skills from standard format**
   - Parse SKILL.md files
   - Load metadata.yaml or frontmatter
   - Bundle resources if present

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
   - Level 1: Load metadata only (~100 tokens)
   - Level 2: Load full SKILL.md when invoked (<5k tokens)
   - Resources load on-demand

---

## Built-in Tool Conventions

### Proposed Convention: Python-like with Help

**Pattern**: `namespace.action` with `.help()` for documentation

```python
# List tools in namespace
filesystem.help()
→ Lists all filesystem tools (read, write, dir, search, etc.)

# Execute tool
filesystem.dir(path="/home/user")
→ Lists directory contents

# Get help for specific tool
filesystem.dir.help()
→ Shows parameters, examples, usage for dir

# Alternative: Help as parameter
filesystem.dir(help=True)
→ Same as .help()
```

### Comparison of Conventions

#### Option 1: Python-like (Recommended)

```python
# Namespace discovery
filesystem.help()              # or filesystem.list()

# Tool execution
filesystem.dir(path="/tmp")
filesystem.read(file="/etc/hosts")

# Tool help
filesystem.dir.help()
filesystem.read.help()
```

**Pros**:
- Familiar to Python developers
- Clean, readable
- `.help()` is standard Python convention

**Cons**:
- Mixing execution and meta (help) in same namespace

#### Option 2: CLI-like

```bash
# Namespace discovery
filesystem --help
filesystem --list

# Tool execution
filesystem dir --path=/tmp
filesystem read --file=/etc/hosts

# Tool help
filesystem dir --help
```

**Pros**:
- Familiar to CLI users

**Cons**:
- Flags (`--help`) less natural in function calls
- More verbose

#### Option 3: REST-like

```python
# Namespace discovery
GET filesystem/

# Tool execution
POST filesystem/dir data={path: "/tmp"}
POST filesystem/read data={file: "/etc/hosts"}

# Tool help
OPTIONS filesystem/dir
```

**Pros**:
- Clear HTTP verb semantics

**Cons**:
- Unnatural for function-based tool calls
- Over-engineered

#### Option 4: Help Parameter (Simplest)

```python
# Namespace discovery
filesystem(list=True)

# Tool execution
filesystem.dir(path="/tmp")

# Tool help
filesystem.dir(help=True)
```

**Pros**:
- Single consistent pattern
- No special `.help()` method

**Cons**:
- `help=True` parameter feels awkward
- Mixes meta-operations with execution

### Recommended Convention

**Use Option 1: Python-like with `.help()`**

```python
# Discovery
namespace.help()        # or namespace.list()

# Execution
namespace.action(params)

# Documentation
namespace.action.help()
```

**Rationale**:
- Most familiar to developers
- Clear separation of concerns
- Pythonic (our codebase is PHP but Python conventions are widely known)
- Extensible (can add `.version()`, `.examples()`, etc.)

### Built-in Tool Namespaces

```python
# File operations
file.help()                              # List file tools
file.read(path="/config.yaml")           # Read file
file.read.help()                         # Help for read
file.write(path="/out.txt", content="")  # Write file
file.search(pattern="*.php", path="src/") # Search files
file.dir(path="/tmp")                    # List directory

# Database operations
db.help()
db.query(sql="SELECT * FROM users")
db.query.help()
db.insert(table="users", data={})

# CRM operations
crm.help()
crm.contact.get(id=123)
crm.contact.get.help()
crm.contact.update(id=123, data={})

# Task/Agent operations
task.help()                              # List available tasks/agents
task.code_review(target="src/")          # Run code review
task.code_review.help()                  # Help for code_review
task.explore(query="find API routes")    # Explore codebase
task.plan(feature="OAuth")               # Create implementation plan

# Batch/Shell operations
batch.help()
batch.run(command="ls -la")
batch.run.help()
batch.script(path="/scripts/migrate.sh")
```

### Nested Namespaces

For complex domains:

```python
# S3 file operations (nested under s3)
s3.file.read(bucket="my-bucket", key="data.json")
s3.file.write(bucket="my-bucket", key="output.json", content="")
s3.file.list(bucket="my-bucket", prefix="logs/")

# Cloud database
cloud.db.query(instance="prod", sql="...")
cloud.db.backup(instance="prod")

# External APIs
api.github.repo.get(owner="org", repo="project")
api.github.issue.create(owner="org", repo="project", title="Bug")
```

**Convention**: Max 3 levels (`namespace.category.action`)

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

- [ ] Update `ToolRegistry`
  - Support namespace-based registration
  - Support namespace-based lookup
  - Add `listByNamespace(string $namespace): array`

- [ ] Create namespace helper methods
  - `namespace.help()` → list tools in namespace
  - `namespace.action.help()` → get tool documentation

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
```

#### 1.2 Two-Level Disclosure

**Goal**: Simplify from 3 levels to 2

**Tasks**:
- [ ] Remove Level 2 (middle "details" level)
- [ ] Update `ToolSpec` to support Level 1 (metadata) and Level 2 (full spec)
- [ ] Update discovery tools to use 2-level pattern

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

**Goal**: Implement `namespace.help()` pattern

**Tasks**:
- [ ] Create `HelpTool` for each namespace
- [ ] Auto-generate help tools from registry
- [ ] Support `action.help()` for specific tools

**Implementation**:
```php
// Auto-generated for each namespace
class FileHelpTool implements Tool
{
    public function __invoke(array $args): array {
        if (empty($args['tool'])) {
            // file.help() - list all file tools
            return $this->registry->listByNamespace('file');
        }

        // file.help(tool="read") - get help for file.read
        $fullName = "file.{$args['tool']}";
        return $this->registry->get($fullName)->fullSpec();
    }
}

// Or: file.read.help() as separate tool
class FileReadHelpTool implements Tool
{
    public function __invoke(array $args): array {
        return $this->registry->get('file.read')->fullSpec();
    }
}
```

**Decision needed**: Which pattern?
- Option A: `file.help()` and `file.help(tool="read")`
- Option B: `file.help()` and `file.read.help()` (separate tools)

**Recommendation**: Option B - cleaner, more intuitive

---

### Phase 2: Claude Compatibility (Week 2) **CRITICAL**

#### 2.1 Claude Skill Format Support

**Goal**: Load Claude skills from standard format

**Tasks**:
- [ ] Create `SkillLoader` to parse SKILL.md files
  - Parse frontmatter (YAML metadata)
  - Parse markdown content
  - Load bundled resources from `resources/` directory

- [ ] Create `SkillSpec` extending `ToolSpec`
  - Additional fields: version, author, tags
  - Resource bundling support
  - Capability declarations

- [ ] Support skill loading from:
  - Local filesystem
  - GitHub repositories
  - Skill package registries

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

**Goal**: Load skill metadata upfront, full content on-demand

**Tasks**:
- [ ] Load only frontmatter initially (~100 tokens)
- [ ] Load full SKILL.md when skill invoked (<5k tokens)
- [ ] Load resources on-demand

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

        // Execute skill with full context
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
- [ ] `task.help()` lists all available skills (metadata only)
- [ ] `task.help(task="code_review")` shows full skill documentation
- [ ] `find_tool(purpose="review code")` suggests relevant skills

**Implementation**:
```php
// task.help() implementation
class TaskHelpTool implements Tool
{
    public function __invoke(array $args): array {
        if (empty($args['task'])) {
            // List all tasks (metadata only)
            return $this->registry->listByNamespace('task');
        }

        // Get full spec for specific task
        $fullName = "task.{$args['task']}";
        return $this->registry->get($fullName)->fullSpec();
    }
}
```

---

### Phase 3: Semantic Search (Week 3)

#### 3.1 `find_tool(purpose)` Implementation

**Goal**: Semantic tool discovery

**Tasks**:
- [ ] Create `FindToolTool` with semantic matching
- [ ] Use embeddings or LLM for matching purpose to tools
- [ ] Return ranked results with relevance scores

**Implementation**:
```php
class FindToolTool implements Tool
{
    public function __construct(
        private ToolRegistry $registry,
        private Instructor $instructor,  // For semantic matching
    ) {}

    public function __invoke(array $args): array {
        $purpose = $args['purpose'];

        // Get all tools
        $allTools = $this->registry->all();

        // Build prompt for semantic matching
        $toolList = array_map(
            fn($spec) => "{$spec->name}: {$spec->shortDescription}",
            $allTools
        );

        $prompt = <<<PROMPT
Given this purpose: "{$purpose}"

Find the most relevant tools from this list:
{$toolList}

Return top 3 matches with relevance scores.
PROMPT;

        $result = $this->instructor->respond(
            messages: Messages::fromString($prompt),
            responseModel: new class {
                /** @var ToolMatch[] */
                public array $matches;
            }
        );

        return ['matches' => $result->matches];
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
- [ ] Help tools work (`file.help()`, `file.read.help()`)
- [ ] Claude skills load from standard SKILL.md format
- [ ] Progressive loading reduces context by 85%+
- [ ] Semantic search works (`find_tool(purpose="...")`)
- [ ] Backward compatibility maintained

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
3. Help tools (`namespace.help()`)
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
