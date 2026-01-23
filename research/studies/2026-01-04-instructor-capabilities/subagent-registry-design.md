# Subagent Registry Design

## Overview

Enhance the Agent system to support predefined, configurable subagents loaded from files or code, inspired by Claude Code's subagent system.

## Current Architecture

### Existing Components

1. **SpawnSubagentTool** - Spawns subagents with predefined types
   - `agent_type` parameter: explore/code/plan
   - Uses `AgentCapability` to filter tools
   - Creates isolated subagent with filtered tools

2. **AgentCapability Interface** - Defines tool filtering logic
   - `toolsFor(AgentType, Tools): Tools` - Filter tools by type
   - `systemPromptFor(AgentType): string` - Get system prompt
   - `isToolAllowed(AgentType, string): bool` - Check tool permission

3. **DefaultAgentCapability** - Hardcoded implementation
   - EXPLORE_TOOLS: bash, read_file
   - CODE_TOOLS: bash, read_file, write_file, edit_file, todo_write
   - PLAN_TOOLS: read_file

### Limitations

- Only 3 predefined agent types
- No custom tool combinations
- No custom system prompts per subagent
- No file-based configuration
- No skill loading
- No model selection

## Proposed Architecture

### New Components

```
SubagentSpec (Data)
├── name: string
├── description: string
├── systemPrompt: string
├── tools: ?array<string>           # null = inherit all
├── model: ?string                   # null = use default
├── skills: ?array<string>
└── metadata: array                  # extensible

SubagentRegistry (Service)
├── register(SubagentSpec): void
├── get(string): SubagentSpec
├── has(string): bool
├── all(): array<SubagentSpec>
├── loadFromFile(string): void
├── loadFromDirectory(string): void
└── loadFromJson(array): void

SubagentSpecParser (Utility)
├── parseMarkdownFile(string): SubagentSpec
├── parseYamlFrontmatter(string): array
├── parseJson(array): SubagentSpec
└── validate(SubagentSpec): Result

SpawnSubagentTool (Modified)
├── __construct(Agent, SubagentRegistry)
├── __invoke(subagent: string, prompt: string): string
├── filterTools(SubagentSpec, Tools): Tools
├── loadSkills(array<string>, SkillLibrary): Skills
└── createSubagent(SubagentSpec): Agent
```

## File Format Specification

### Markdown with YAML Frontmatter

```markdown
---
name: code-reviewer
description: Expert code reviewer focusing on quality, security, and best practices
tools: read_file, search_files, list_dir
model: inherit
skills: php-coding-standards, security-checklist
---

You are a senior code reviewer with expertise in PHP, clean code principles,
and security best practices.

## Your Responsibilities

1. Review code for quality issues
2. Identify security vulnerabilities
3. Check adherence to coding standards
4. Suggest improvements

## Guidelines

- Be constructive and specific
- Provide examples when suggesting changes
- Focus on high-impact issues first
```

### Configuration Fields

| Field | Type | Required | Default | Description |
|-------|------|----------|---------|-------------|
| `name` | string | Yes | - | Unique identifier (lowercase, hyphens) |
| `description` | string | Yes | - | When to invoke this subagent |
| `tools` | array\|null | No | null | Specific tools (null = inherit all) |
| `model` | string\|null | No | null | Model preset or 'inherit' |
| `skills` | array\|null | No | null | Skills to auto-load |
| `systemPrompt` | string | Yes | - | Content after frontmatter |

### File Locations

```
Priority 1 (Highest): Project-level
.claude/agents/
├── code-reviewer.md
├── security-auditor.md
└── test-generator.md

Priority 2: Package-level (for reusable subagents)
vendor/cognesy/instructor-php/subagents/
├── php-expert.md
└── api-designer.md

Priority 3 (Lowest): User-level (global)
~/.instructor-php/subagents/
└── my-custom-agent.md
```

## Implementation Details

### 1. SubagentSpec Class

```php
<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Subagents;

use Cognesy\Addons\Agent\Core\Collections\Tools;

final readonly class SubagentSpec
{
    /**
     * @param string $name Unique identifier
     * @param string $description When to use this subagent
     * @param string $systemPrompt Custom system prompt
     * @param array<string>|null $tools Tool names (null = inherit all)
     * @param string|null $model Model preset or 'inherit'
     * @param array<string>|null $skills Skill names to auto-load
     * @param array<string, mixed> $metadata Additional metadata
     */
    public function __construct(
        public string $name,
        public string $description,
        public string $systemPrompt,
        public ?array $tools = null,
        public ?string $model = null,
        public ?array $skills = null,
        public array $metadata = [],
    ) {}

    public function inheritsAllTools(): bool {
        return $this->tools === null;
    }

    public function isToolAllowed(string $toolName): bool {
        if ($this->inheritsAllTools()) {
            return true;
        }
        return in_array($toolName, $this->tools, true);
    }

    public function filterTools(Tools $allTools): Tools {
        if ($this->inheritsAllTools()) {
            return $allTools;
        }

        $filtered = [];
        foreach ($allTools->all() as $tool) {
            if ($this->isToolAllowed($tool->name())) {
                $filtered[] = $tool;
            }
        }

        return new Tools(...$filtered);
    }

    public function hasSkills(): bool {
        return $this->skills !== null && count($this->skills) > 0;
    }

    public function shouldInheritModel(): bool {
        return $this->model === 'inherit';
    }
}
```

### 2. SubagentRegistry Class

```php
<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Subagents;

use Cognesy\Addons\Agent\Exceptions\SubagentNotFoundException;

final class SubagentRegistry
{
    /** @var array<string, AgentSpec> */
    private array $specs = [];

    private AgentSpecParser $parser;

    public function __construct(?AgentSpecParser $parser = null) {
        $this->parser = $parser ?? new AgentSpecParser();
    }

    // Registration

    public function register(AgentSpec $spec): void {
        $this->specs[$spec->name] = $spec;
    }

    public function registerMultiple(AgentSpec ...$specs): void {
        foreach ($specs as $spec) {
            $this->register($spec);
        }
    }

    // Retrieval

    public function get(string $name): AgentSpec {
        if (!$this->has($name)) {
            throw new SubagentNotFoundException("Subagent '{$name}' not found.");
        }
        return $this->specs[$name];
    }

    public function has(string $name): bool {
        return isset($this->specs[$name]);
    }

    /** @return array<string, AgentSpec> */
    public function all(): array {
        return $this->specs;
    }

    /** @return array<string> */
    public function names(): array {
        return array_keys($this->specs);
    }

    // Loading from files

    public function loadFromFile(string $path): void {
        $spec = $this->parser->parseMarkdownFile($path);
        $this->register($spec);
    }

    public function loadFromDirectory(string $path, bool $recursive = false): void {
        if (!is_dir($path)) {
            return;
        }

        $pattern = $recursive ? '**/*.md' : '*.md';
        $files = glob($path . '/' . $pattern, GLOB_BRACE);

        foreach ($files as $file) {
            if (is_file($file)) {
                $this->loadFromFile($file);
            }
        }
    }

    public function loadFromJson(array $data): void {
        $spec = $this->parser->parseJson($data);
        $this->register($spec);
    }

    // Auto-discovery

    public function autoDiscoverSubagents(
        ?string $projectPath = null,
        ?string $packagePath = null,
        ?string $userPath = null,
    ): void {
        // Priority 3: User-level (global)
        if ($userPath !== null && is_dir($userPath)) {
            $this->loadFromDirectory($userPath);
        }

        // Priority 2: Package-level
        if ($packagePath !== null && is_dir($packagePath)) {
            $this->loadFromDirectory($packagePath);
        }

        // Priority 1: Project-level (highest priority, can override)
        if ($projectPath !== null && is_dir($projectPath)) {
            $this->loadFromDirectory($projectPath);
        }
    }
}
```

### 3. SubagentSpecParser Class

```php
<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Subagents;

use Symfony\Component\Yaml\Yaml;

final class SubagentSpecParser
{
    public function parseMarkdownFile(string $path): AgentSpec {
        $content = file_get_contents($path);
        if ($content === false) {
            throw new \RuntimeException("Failed to read file: {$path}");
        }

        return $this->parseMarkdown($content);
    }

    public function parseMarkdown(string $content): AgentSpec {
        // Extract YAML frontmatter
        $pattern = '/^---\s*\n(.*?)\n---\s*\n(.*)$/s';
        if (!preg_match($pattern, $content, $matches)) {
            throw new \InvalidArgumentException("Invalid markdown format: missing frontmatter");
        }

        $frontmatter = Yaml::parse($matches[1]);
        $systemPrompt = trim($matches[2]);

        return $this->createSpec($frontmatter, $systemPrompt);
    }

    public function parseJson(array $data): AgentSpec {
        $systemPrompt = $data['systemPrompt'] ?? $data['prompt'] ?? '';
        unset($data['systemPrompt'], $data['prompt']);

        return $this->createSpec($data, $systemPrompt);
    }

    private function createSpec(array $data, string $systemPrompt): AgentSpec {
        $name = $data['name'] ?? throw new \InvalidArgumentException("Missing 'name' field");
        $description = $data['description'] ?? throw new \InvalidArgumentException("Missing 'description' field");

        // Parse tools (comma-separated string or array)
        $tools = null;
        if (isset($data['tools'])) {
            $tools = is_string($data['tools'])
                ? array_map('trim', explode(',', $data['tools']))
                : $data['tools'];
        }

        // Parse skills (comma-separated string or array)
        $skills = null;
        if (isset($data['skills'])) {
            $skills = is_string($data['skills'])
                ? array_map('trim', explode(',', $data['skills']))
                : $data['skills'];
        }

        $metadata = $data['metadata'] ?? [];

        return new AgentSpec(
            name: $name,
            description: $description,
            systemPrompt: $systemPrompt,
            tools: $tools,
            model: $data['model'] ?? null,
            skills: $skills,
            metadata: $metadata,
        );
    }

    public function validate(AgentSpec $spec): bool {
        // Validate name format (lowercase, hyphens only)
        if (!preg_match('/^[a-z][a-z0-9-]*$/', $spec->name)) {
            throw new \InvalidArgumentException(
                "Invalid subagent name '{$spec->name}': must use lowercase letters and hyphens"
            );
        }

        // Validate description is not empty
        if (trim($spec->description) === '') {
            throw new \InvalidArgumentException("Subagent description cannot be empty");
        }

        // Validate system prompt is not empty
        if (trim($spec->systemPrompt) === '') {
            throw new \InvalidArgumentException("Subagent system prompt cannot be empty");
        }

        return true;
    }
}
```

### 4. Modified SpawnSubagentTool

```php
<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Tools\Subagent;

use Cognesy\Addons\Agent\Agent;use Cognesy\Addons\Agent\AgentFactory;use Cognesy\Addons\Agent\Core\Data\AgentState;use Cognesy\Addons\Agent\Core\Enums\AgentStatus;use Cognesy\Addons\Agent\Skills\SkillLibrary;use Cognesy\Addons\Agent\Tools\BaseTool;use Cognesy\Addons\AgentTemplate\Registry\AgentRegistry;use Cognesy\Addons\AgentTemplate\Spec\AgentSpec;use Cognesy\Messages\Messages;

class SpawnSubagentTool extends BaseTool
{
    private Agent $parentAgent;
    private AgentRegistry $registry;
    private ?SkillLibrary $skillLibrary;
    private ?string $parentModel;

    public function __construct(
        Agent $parentAgent,
        AgentRegistry $registry,
        ?SkillLibrary $skillLibrary = null,
        ?string $parentModel = null,
    ) {
        parent::__construct(
            name: 'spawn_subagent',
            description: <<<'DESC'
Spawn a predefined subagent for a focused task.

Available subagents are defined in .claude/agents/ directory.
Each subagent has specific expertise, tools, and capabilities.

Use the subagent's description to determine when to invoke it.
DESC,
        );

        $this->parentAgent = $parentAgent;
        $this->registry = $registry;
        $this->skillLibrary = $skillLibrary;
        $this->parentModel = $parentModel;
    }

    #[\Override]
    public function __invoke(mixed ...$args): string {
        $subagentName = $args['subagent'] ?? $args[0] ?? '';
        $prompt = $args['prompt'] ?? $args[1] ?? '';

        $spec = $this->registry->get($subagentName);
        $subagent = $this->createSubagent($spec);
        $initialState = $this->createInitialState($prompt, $spec->systemPrompt);

        $finalState = $this->runSubagent($subagent, $initialState);

        return $this->extractFinalResponse($finalState, $spec->name);
    }

    private function createSubagent(AgentSpec $spec): Agent {
        // Filter tools
        $tools = $spec->filterTools($this->parentAgent->tools());

        // Add skills to tools if specified
        if ($spec->hasSkills() && $this->skillLibrary !== null) {
            $skillTools = [];
            foreach ($spec->skills as $skillName) {
                if ($this->skillLibrary->has($skillName)) {
                    // Could create a LoadSkillTool per skill, or preload skills
                    // For now, we'll add them to the system prompt
                }
            }
        }

        // Determine model
        $llmPreset = null;
        if ($spec->model !== null && !$spec->shouldInheritModel()) {
            $llmPreset = $spec->model;
        } elseif ($spec->shouldInheritModel()) {
            $llmPreset = $this->parentModel;
        }

        return AgentFactory::default(
            tools: $tools,
            llmPreset: $llmPreset,
        );
    }

    private function createInitialState(string $prompt, string $systemPrompt): AgentState {
        $messages = Messages::fromArray([
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $prompt],
        ]);

        return AgentState::empty()->withMessages($messages);
    }

    private function runSubagent(Agent $subagent, AgentState $state): AgentState {
        return $subagent->finalStep($state);
    }

    private function extractFinalResponse(AgentState $state, string $name): string {
        $parts = ["[Subagent: {$name}]"];

        if ($state->status() === AgentStatus::Failed) {
            $errorMsg = $state->currentStep()?->errorsAsString() ?? 'Unknown error';
            $parts[] = "Status: Failed";
            $parts[] = "Error: {$errorMsg}";
            return implode("\n", $parts);
        }

        $parts[] = "Status: Completed";
        $parts[] = "Steps: {$state->stepCount()}";

        $finalStep = $state->currentStep();
        if ($finalStep !== null) {
            $response = $finalStep->outputMessages()->toString();
            if ($response !== '') {
                $parts[] = "";
                $parts[] = $response;
            }
        }

        return implode("\n", $parts);
    }

    #[\Override]
    public function toToolSchema(): array {
        // Build enum of available subagents
        $subagentNames = $this->registry->names();
        $descriptions = [];
        foreach ($this->registry->all() as $spec) {
            $descriptions[] = "{$spec->name}: {$spec->description}";
        }

        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name(),
                'description' => $this->description() . "\n\nAvailable subagents:\n" . implode("\n", $descriptions),
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'subagent' => [
                            'type' => 'string',
                            'enum' => $subagentNames,
                            'description' => 'Which subagent to spawn',
                        ],
                        'prompt' => [
                            'type' => 'string',
                            'description' => 'The task or question for the subagent',
                        ],
                    ],
                    'required' => ['subagent', 'prompt'],
                ],
            ],
        ];
    }
}
```

### 5. Updated AgentFactory

```php
public static function codingAgent(
    ?string $workDir = null,
    ?SkillLibrary $skills = null,
    ?SubagentRegistry $subagentRegistry = null,
    int $maxSteps = 20,
    int $maxTokens = 32768,
    int $timeout = 300,
    ?CanHandleEvents $events = null,
    ?string $llmPreset = null,
): Agent {
    // ... existing code ...

    // Auto-discover subagents if registry not provided
    if ($subagentRegistry === null) {
        $subagentRegistry = new SubagentRegistry();
        $subagentRegistry->autoDiscoverSubagents(
            projectPath: $workDir . '/.claude/agents',
            packagePath: __DIR__ . '/../../subagents',
            userPath: $_SERVER['HOME'] . '/.instructor-php/subagents',
        );
    }

    // Add subagent tool with registry
    $subagentTool = new SpawnSubagentTool(
        $agent,
        $subagentRegistry,
        $skills,
        $llmPreset,
    );

    return $agent->withTools($tools->merge(new Tools($subagentTool)));
}
```

## Example Subagent Definitions

### code-reviewer.md

```markdown
---
name: code-reviewer
description: Expert code reviewer. Use after making code changes to identify issues.
tools: read_file, search_files, list_dir, grep
model: inherit
skills: php-coding-standards, security-best-practices
---

You are a senior code reviewer with expertise in PHP, clean code principles,
and security best practices.

## Your Responsibilities

1. Review code for quality issues
2. Identify security vulnerabilities (SQL injection, XSS, etc.)
3. Check adherence to project coding standards
4. Suggest specific improvements with examples

## Review Checklist

- [ ] Code follows SOLID principles
- [ ] No security vulnerabilities
- [ ] Proper error handling
- [ ] Clear naming and structure
- [ ] Adequate type safety
- [ ] No code duplication

## Output Format

Provide findings in order of severity:
1. **Critical**: Security issues, bugs
2. **High**: Design problems, major code smells
3. **Medium**: Code style, minor improvements
4. **Low**: Suggestions, optimizations
```

### test-generator.md

```markdown
---
name: test-generator
description: Generate comprehensive Pest tests for PHP code
tools: read_file, write_file, search_files
model: sonnet
skills: pest-testing, php-best-practices
---

You are a testing expert specializing in Pest PHP testing framework.

## Your Task

Generate comprehensive test suites that cover:
- Happy path scenarios
- Edge cases
- Error conditions
- Boundary values

## Test Structure

- Use descriptive test names
- Follow Arrange-Act-Assert pattern
- Use dataset providers for multiple cases
- Include both unit and integration tests

## Quality Standards

- Aim for 90%+ code coverage
- Test one thing per test
- Use meaningful assertions
- Mock external dependencies
```

### api-designer.md

```markdown
---
name: api-designer
description: Design RESTful API endpoints and data structures
tools: read_file, write_file
model: opus
---

You are an API design expert following REST principles and best practices.

## Design Principles

1. Use appropriate HTTP methods (GET, POST, PUT, PATCH, DELETE)
2. Consistent URL structure and naming
3. Proper status codes
4. Versioning strategy
5. Clear error responses

## Deliverables

- Endpoint specifications
- Request/response schemas
- Authentication requirements
- Rate limiting considerations
- Documentation examples
```

## Usage Examples

### In Code

```php
use Cognesy\Addons\Agent\AgentFactory;use Cognesy\Addons\AgentTemplate\Registry\AgentRegistry;use Cognesy\Addons\AgentTemplate\Spec\AgentSpec;

// Create registry
$registry = new AgentRegistry();

// Register from file
$registry->loadFromFile('.claude/agents/code-reviewer.md');

// Register from code
$registry->register(new AgentSpec(
    name: 'custom-analyzer',
    description: 'Analyze specific patterns',
    systemPrompt: 'You are an expert analyzer...',
    tools: ['read_file', 'grep'],
    model: 'sonnet',
));

// Create agent with registry
$agent = AgentFactory::codingAgent(
    workDir: '/project',
    subagentRegistry: $registry,
);

// Run agent
$state = AgentState::empty()->withMessages(
    Messages::fromString('Review the authentication code')
);

$result = $agent->finalStep($state);
// Agent will automatically use spawn_subagent with code-reviewer subagent
```

### CLI-Based Configuration

```php
// Parse from JSON (for CLI integration)
$jsonConfig = json_decode('{
  "code-reviewer": {
    "description": "Expert code reviewer",
    "systemPrompt": "You are a senior code reviewer...",
    "tools": ["read_file", "grep", "search_files"],
    "model": "sonnet"
  }
}', true);

foreach ($jsonConfig as $name => $config) {
    $config['name'] = $name;
    $registry->loadFromJson($config);
}
```

## Benefits

1. **Flexibility**: Define subagents via code or files
2. **Reusability**: Share subagents across projects
3. **Specialization**: Each subagent has focused expertise
4. **Tool Control**: Granular tool access per subagent
5. **Model Selection**: Optimize costs by using appropriate models
6. **Skill Integration**: Auto-load relevant skills
7. **Discoverability**: Auto-discover from standard locations

## Migration Path

### Phase 1: Backward Compatibility
- Keep existing `DefaultAgentCapability` and `AgentType`
- Add new `SubagentRegistry` system alongside
- SpawnSubagentTool accepts both old and new approaches

### Phase 2: Deprecation
- Mark `AgentType` enum as deprecated
- Encourage migration to SubagentSpec
- Provide migration guide

### Phase 3: Removal
- Remove `AgentType` and `DefaultAgentCapability`
- SpawnSubagentTool exclusively uses SubagentRegistry

## Open Questions

1. **Skill Loading**: How to preload skills into subagent context?
   - Option A: Add skills as system messages
   - Option B: Create custom state processor
   - Option C: Modify driver to inject skills

2. **Permission Mode**: Should subagents inherit parent's permission settings?
   - Probably yes, but allow override in spec

3. **Nested Subagents**: Should subagents be able to spawn other subagents?
   - Probably not initially, prevent infinite recursion

4. **Model Inheritance**: How to handle 'inherit' across multiple levels?
   - Track parent model in SpawnSubagentTool constructor

5. **Tool Validation**: Should we validate that specified tools exist?
   - Yes, during spec parsing or registration
