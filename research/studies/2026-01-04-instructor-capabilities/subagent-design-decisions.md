# Subagent Design Decisions

## Research Summary

### Claude Code Approach

Based on research from [Claude Code Skills documentation](https://code.claude.com/docs/en/skills) and [Mikhail Shilkov's deep dive](https://mikhail.io/2025/10/claude-code-skills/):

**Key Findings:**
1. **No explicit CLI commands** - No `skill_list`, `skill_help`, `skill_use` commands
2. **Tool-based invocation** - Skills are accessed via the `Skill` tool
3. **Auto-loading context** - All skill metadata (name + description) is loaded into context at conversation start
4. **On-demand content** - Full skill content is loaded only when explicitly invoked
5. **Natural language selection** - Claude determines which skills to use based on descriptions

**Skill Tool Behavior:**
```xml
<available_skills>
  <skill>
    <name>skill-name</name>
    <description>When to use this skill</description>
  </skill>
  <!-- ... more skills ... -->
</available_skills>
```

**Invocation:**
- User or Claude says "use the code-review skill"
- Claude calls `Skill` tool with just the skill name
- Tool returns full skill content
- Content is added to conversation context

### Current instructor-php Implementation

**SkillLibrary.php:**
- Scans directory for `.md` files with YAML frontmatter
- Lazy loads: metadata scanned once, content loaded on demand
- Methods:
  - `listSkills()` - Returns `[{name, description}]` array
  - `hasSkill(string $name)` - Check if skill exists
  - `getSkill(string $name)` - Load full skill content
  - `renderSkillList()` - Human-readable list

**LoadSkillTool.php:**
- Unified tool for listing and loading
- Parameters:
  - `skill_name` (string) - Which skill to load
  - `list_skills` (boolean) - If true, list all skills
- Returns skill content wrapped in `<skill name="...">...</skill>` tags

**Skill.php:**
- Simple data structure: name, description, body, path, resources
- `render()` - Returns XML-wrapped content
- `renderMetadata()` - Returns `[name]: description`

**Current Integration:**
- `AgentFactory::withSkills()` - Creates agent with LoadSkillTool
- `AgentFactory::codingAgent()` - Optionally includes LoadSkillTool

## Design Decisions for Subagents

### 1. Skill Loading Strategy

**Decision: Option A - Preload skills into system prompt**

**Rationale:**
- Matches Claude Code's behavior of making skills immediately available
- No extra tool calls needed during subagent execution
- Cleaner execution flow
- Skills are typically small (1-5KB), acceptable token overhead

**Implementation:**
```php
// In SpawnSubagentTool
private function createInitialState(string $prompt, SubagentSpec $spec): AgentState {
    $systemParts = [$spec->systemPrompt];

    // Preload specified skills
    if ($spec->hasSkills() && $this->skillLibrary !== null) {
        $systemParts[] = "\n## Available Skills\n";
        foreach ($spec->skills as $skillName) {
            $skill = $this->skillLibrary->getSkill($skillName);
            if ($skill !== null) {
                $systemParts[] = $skill->render();
            }
        }
    }

    $systemMessage = implode("\n", $systemParts);

    $messages = Messages::fromArray([
        ['role' => 'system', 'content' => $systemMessage],
        ['role' => 'user', 'content' => $prompt],
    ]);

    return AgentState::empty()->withMessages($messages);
}
```

**Alternative Considered (Rejected):**
- Option B: Provide LoadSkillTool to subagent
  - Requires subagent to make extra tool call
  - Adds complexity
  - Wastes tokens on tool calling overhead
- Option C: Hybrid (metadata in prompt, LoadSkillTool for content)
  - Over-engineered for current needs
  - Can revisit if skill sizes become problematic

### 2. Permission Mode

**Decision: SKIP - Not supported in current implementation**

**Rationale:**
- No permission system currently implemented in Agent
- Can add later when/if permissions are needed
- Not blocking for MVP

### 3. Nested Subagents

**Decision: Allow with max_depth limit**

**Rationale:**
- Some workflows benefit from delegation chains (e.g., orchestrator → researcher → analyzer)
- max_depth prevents infinite recursion
- Depth tracking enables debugging and cost monitoring

**Implementation:**
```php
class SpawnSubagentTool {
    private int $currentDepth;
    private int $maxDepth;

    public function __construct(
        Agent $parentAgent,
        SubagentRegistry $registry,
        ?SkillLibrary $skillLibrary = null,
        ?string $parentModel = null,
        int $currentDepth = 0,
        int $maxDepth = 3, // Default: allow 3 levels of nesting
    ) {
        $this->currentDepth = $currentDepth;
        $this->maxDepth = $maxDepth;
    }

    #[\Override]
    public function __invoke(mixed ...$args): string {
        if ($this->currentDepth >= $this->maxDepth) {
            return "[Error: Maximum subagent nesting depth ({$this->maxDepth}) reached]";
        }

        $spec = $this->registry->get($subagentName);
        $subagent = $this->createSubagent($spec);
        // ... rest of invocation
    }

    private function createSubagent(SubagentSpec $spec): Agent {
        $tools = $spec->filterTools($this->parentAgent->tools());

        // If spawn_subagent is in filtered tools, create with incremented depth
        if ($tools->has('spawn_subagent')) {
            $nestedSpawnTool = new SpawnSubagentTool(
                $this->parentAgent,
                $this->registry,
                $this->skillLibrary,
                $this->parentModel,
                currentDepth: $this->currentDepth + 1,
                maxDepth: $this->maxDepth,
            );
            $tools = $tools->withToolRemoved('spawn_subagent')
                           ->withTool($nestedSpawnTool);
        }

        return AgentFactory::default(tools: $tools, ...);
    }
}
```

**Configuration:**
```php
// In AgentFactory::codingAgent()
public static function codingAgent(
    // ... existing params
    int $maxSubagentDepth = 3,
): Agent {
    $subagentTool = new SpawnSubagentTool(
        $agent,
        $registry,
        $skills,
        $llmPreset,
        currentDepth: 0,
        maxDepth: $maxSubagentDepth,
    );
}
```

**Usage:**
```php
// Allow deep nesting for complex workflows
$agent = AgentFactory::codingAgent(
    maxSubagentDepth: 5,
);

// Disable nesting entirely
$agent = AgentFactory::codingAgent(
    maxSubagentDepth: 1, // Only parent can spawn, subagents cannot
);
```

### 4. Model Inheritance

**Decision: Track parent model, support 'inherit' keyword**

**Rationale:**
- Cost optimization: use cheaper models for simple subagents
- Consistency: some workflows benefit from same model throughout
- Flexibility: allow override for specialized needs

**Implementation:**
```php
class SpawnSubagentTool {
    private ?string $parentModel;

    public function __construct(
        Agent $parentAgent,
        SubagentRegistry $registry,
        ?SkillLibrary $skillLibrary = null,
        ?string $parentModel = null, // Track parent's model
    ) {
        $this->parentModel = $parentModel;
    }
}

class SubagentSpec {
    public function shouldInheritModel(): bool {
        return $this->model === 'inherit';
    }

    public function resolveModel(?string $parentModel): ?string {
        if ($this->model === null) {
            return null; // Use default
        }
        if ($this->model === 'inherit') {
            return $parentModel;
        }
        return $this->model; // Use specified model
    }
}

// In createSubagent()
$llmPreset = $spec->resolveModel($this->parentModel);
```

### 5. Tool Validation

**Decision: Validate at registration time, warn on unknown tools**

**Rationale:**
- Fail fast: catch typos early
- Better DX: clear error messages
- Runtime safety: prevent silent failures

**Implementation:**
```php
class SubagentSpec {
    public function validate(Tools $availableTools): array {
        $errors = [];

        if ($this->tools === null) {
            return $errors; // Inherits all, nothing to validate
        }

        foreach ($this->tools as $toolName) {
            if (!$availableTools->has($toolName)) {
                $errors[] = "Unknown tool '{$toolName}' in subagent '{$this->name}'";
            }
        }

        return $errors;
    }
}

class SubagentRegistry {
    public function register(SubagentSpec $spec, ?Tools $availableTools = null): void {
        if ($availableTools !== null) {
            $errors = $spec->validate($availableTools);
            if (!empty($errors)) {
                throw new InvalidSubagentException(implode("\n", $errors));
            }
        }

        $this->specs[$spec->name] = $spec;
    }
}
```

**Warning mode (softer):**
```php
// Alternative: Log warnings instead of throwing
foreach ($errors as $error) {
    trigger_error($error, E_USER_WARNING);
}
```

## Comparison with Current Implementation

### What We Keep

✅ **SkillLibrary** - No changes needed
- Already has perfect API for our needs
- `getSkill()` returns full content
- Lazy loading works well

✅ **Skill rendering** - Keep XML format
- `<skill name="...">content</skill>`
- Resources list
- Clean, parseable

✅ **YAML frontmatter parsing** - Already implemented
- Can reuse for SubagentSpec parsing
- Same format as skills

### What We Add

➕ **SubagentSpec** - New data structure
- Extends skill concept to full agents
- Includes tools, model, skills, systemPrompt

➕ **SubagentRegistry** - New service
- Similar to SkillLibrary but for subagents
- Supports code-first and file-based registration

➕ **Enhanced SpawnSubagentTool** - Modified
- Uses SubagentRegistry instead of AgentCapability
- Preloads skills into system prompt
- Handles model inheritance

### What We Deprecate (Eventually)

⚠️ **AgentType enum** - Keep for now, deprecate later
- Still useful for backward compatibility
- Can coexist with SubagentRegistry
- Migration path: `AgentType::Code` → predefined `code` subagent spec

⚠️ **DefaultAgentCapability** - Keep as fallback
- Provide default subagent specs that match current behavior
- Allow projects to override with custom specs

## Migration Strategy

### Phase 1: Add alongside existing (Current)
```php
// Old way still works
$subagent = new SpawnSubagentTool($agent, new DefaultAgentCapability());

// New way
$registry = new SubagentRegistry();
$registry->register(new SubagentSpec(...));
$subagent = new SpawnSubagentTool($agent, $registry);
```

### Phase 2: Provide defaults (Next release)
```php
// Auto-register equivalents of AgentType
$registry = SubagentRegistry::withDefaults(); // Creates explore, code, plan
```

### Phase 3: Deprecate old (Future)
```php
// Mark AgentType as @deprecated
// Update docs to show only SubagentSpec approach
```

## File Format Specification

### Subagent File Format (Reuses Skill Format)

**Location:** `.claude/agents/code-reviewer.md`

```markdown
---
name: code-reviewer
description: Expert code reviewer focusing on security and quality
tools: read_file, search_files, list_dir
model: inherit
skills: php-coding-standards, security-checklist
---

You are a senior code reviewer with expertise in PHP development.

## Your Responsibilities

1. Review code for quality issues
2. Identify security vulnerabilities
3. Check coding standards adherence

## Guidelines

- Be specific and constructive
- Provide examples
- Focus on high-impact issues
```

**Fields (extends Skill format):**
- `name` - Unique identifier (from Skill)
- `description` - When to use (from Skill)
- `tools` - NEW: Tool access list
- `model` - NEW: Model selection
- `skills` - NEW: Skills to preload
- `resources` - Could extend for subagent-specific resources
- Content - System prompt (was skill body)

### Backward Compatibility with Skills

**A skill can become a subagent spec by adding fields:**

```markdown
---
name: api-design
description: REST API design expert
# Original skill fields:
resources:
  - api-checklist.md
  - example-openapi.yaml

# NEW subagent fields (optional):
tools: read_file, write_file
model: opus
---

[Original skill content becomes system prompt]
```

**This means:**
1. Existing skills can be loaded as subagents (backward compatible)
2. Subagents are "skills that can use tools"
3. One format, two purposes (DRY principle)

## Open Questions Answered

### 1. Skill Loading ✅

**Answer:** Preload into system prompt (Option A)

**Code:**
```php
if ($spec->hasSkills() && $this->skillLibrary !== null) {
    foreach ($spec->skills as $skillName) {
        $skill = $this->skillLibrary->getSkill($skillName);
        if ($skill !== null) {
            $systemParts[] = $skill->render();
        }
    }
}
```

### 2. Permission Mode ⏭️

**Answer:** SKIP - Not in current implementation

**Rationale:**
- No permission system exists yet
- Can add when needed

### 3. Nested Subagents ✅

**Answer:** Allow with max_depth parameter (default: 3)

**Code:**
```php
class SpawnSubagentTool {
    public function __construct(
        int $currentDepth = 0,
        int $maxDepth = 3,
    ) {}

    public function __invoke(...) {
        if ($this->currentDepth >= $this->maxDepth) {
            return "[Error: Max depth reached]";
        }
        // Create nested tool with incremented depth
    }
}
```

### 4. Model Inheritance ✅

**Answer:** Support 'inherit' keyword + parent tracking

**Code:**
```php
public function resolveModel(?string $parentModel): ?string {
    if ($this->model === 'inherit') return $parentModel;
    return $this->model;
}
```

### 5. Tool Validation ✅

**Answer:** Validate at registration, throw exception

**Reasoning:**
- Fail fast is better than silent failure
- Clear error messages help developers
- Can add warning mode later if needed

## Next Steps

1. ✅ Research complete
2. ✅ Design decisions documented
3. ⏭️ Implement SubagentSpec class
4. ⏭️ Implement SubagentRegistry class
5. ⏭️ Update SpawnSubagentTool
6. ⏭️ Add factory methods for common patterns
7. ⏭️ Write tests
8. ⏭️ Create examples
9. ⏭️ Update documentation

## Sources

- [Claude Code Skills Documentation](https://code.claude.com/docs/en/skills)
- [Inside Claude Code Skills: Structure, prompts, invocation](https://mikhail.io/2025/10/claude-code-skills/)
- [Agent Skills - Claude Platform Docs](https://platform.claude.com/docs/en/agents-and-tools/agent-skills/overview)
- [GitHub - anthropics/skills](https://github.com/anthropics/skills)
- [Claude Agent Skills: A First Principles Deep Dive](https://leehanchung.github.io/blogs/2025/10/26/claude-skills-deep-dive/)
