# Implementation Guide: Discovery Tools

**Date**: 2025-01-06
**Purpose**: Step-by-step implementation of discovery tools with progressive disclosure

## Overview

This guide provides concrete implementation steps for creating discovery tools that follow the "Tool Registry IS A TOOL" pattern with three-level progressive disclosure.

---

## Phase 1: Core Infrastructure

### Step 1: Create ToolSpec with Disclosure Levels

```php
// packages/instructor/src/Tools/ToolSpec.php
namespace Cognesy\Instructor\Tools;

final readonly class ToolSpec
{
    public function __construct(
        public string $name,

        // Level 1: Browse
        public string $shortDescription,    // One-line summary (max 10 words)

        // Level 2: Decide
        public string $description,         // 2-3 sentence description
        public string $usage,               // Usage example
        public array $parameterNames,       // Just parameter names
        public string $returns,             // Return type

        // Level 3: Deep dive
        public array $parameters,           // Full parameter specs
        public array $examples,             // Multiple examples
        public array $errors,               // Error conditions
        public array $notes,                // Performance/edge case notes
    ) {}

    // Convenience methods for progressive disclosure

    public function level1(): array {
        return [
            'name' => $this->name,
            'summary' => $this->shortDescription,
        ];
    }

    public function level2(): array {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'parameters' => $this->parameterNames,
            'usage' => $this->usage,
            'returns' => $this->returns,
        ];
    }

    public function level3(): array {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'parameters' => $this->parameters,
            'usage' => $this->usage,
            'returns' => $this->returns,
            'examples' => $this->examples,
            'errors' => $this->errors,
            'notes' => $this->notes,
        ];
    }

    public function parameter(string $name): array {
        if (!isset($this->parameters[$name])) {
            throw new \InvalidArgumentException("Parameter {$name} not found");
        }

        return [
            'tool' => $this->name,
            'parameter' => $name,
            ...$this->parameters[$name],
        ];
    }
}
```

### Step 2: Create ToolRegistry

```php
// packages/instructor/src/Tools/ToolRegistry.php
namespace Cognesy\Instructor\Tools;

class ToolRegistry
{
    /** @var array<string, ToolSpec> */
    private array $tools = [];

    public function register(ToolSpec $spec): void {
        $this->tools[$spec->name] = $spec;
    }

    public function get(string $name): ?ToolSpec {
        return $this->tools[$name] ?? null;
    }

    public function has(string $name): bool {
        return isset($this->tools[$name]);
    }

    public function all(): array {
        return $this->tools;
    }

    public function names(): array {
        return array_keys($this->tools);
    }

    // Level 1: List all with summaries
    public function list(): array {
        return array_map(
            fn(ToolSpec $spec) => $spec->level1(),
            $this->tools
        );
    }
}
```

### Step 3: Create Discovery Tool

```php
// packages/instructor/src/Tools/DiscoverToolsTool.php
namespace Cognesy\Instructor\Tools;

class DiscoverToolsTool implements Tool
{
    public function __construct(
        private string $name,
        private string $category,
        private ToolRegistry $registry,
    ) {}

    public function __invoke(array $args = []): array {
        // Level 1: List all tools (no arguments)
        if (empty($args['tool'])) {
            return $this->level1_browse();
        }

        $toolName = $args['tool'];

        // Validate tool exists
        if (!$this->registry->has($toolName)) {
            throw new \InvalidArgumentException(
                "Tool '{$toolName}' not found in {$this->category} registry. " .
                "Available tools: " . implode(', ', $this->registry->names())
            );
        }

        $spec = $this->registry->get($toolName);

        // Level 3a: Specific parameter
        if (isset($args['parameter'])) {
            return $spec->parameter($args['parameter']);
        }

        // Level 3b: Full specification
        if (isset($args['detail']) && $args['detail'] === 'full') {
            return $spec->level3();
        }

        // Level 2: Tool details
        return $spec->level2();
    }

    private function level1_browse(): array {
        return [
            'tools' => $this->registry->list(),
        ];
    }

    public function getSpec(): ToolSpec {
        return new ToolSpec(
            name: $this->name,
            shortDescription: "Discover {$this->category} tools",
            description: "Discover available {$this->category} tools. Call without arguments to list all tools, with 'tool' parameter to get usage details, with 'tool' and 'parameter' for specific parameter details, or with detail='full' for complete specification.",
            usage: "{$this->name}()",
            parameterNames: ['tool', 'parameter', 'detail'],
            returns: 'array',
            parameters: [
                'tool' => [
                    'type' => 'string',
                    'description' => 'Optional: specific tool name to get details for',
                    'required' => false,
                    'examples' => ['tool="read_file"'],
                ],
                'parameter' => [
                    'type' => 'string',
                    'description' => 'Optional: specific parameter name (requires tool)',
                    'required' => false,
                    'examples' => ['parameter="offset"'],
                ],
                'detail' => [
                    'type' => 'string',
                    'description' => 'Optional: "full" for complete specification',
                    'required' => false,
                    'enum' => ['full'],
                ],
            ],
            examples: [
                [
                    'code' => "{$this->name}()",
                    'description' => 'List all available tools',
                ],
                [
                    'code' => "{$this->name}(tool='read_file')",
                    'description' => 'Get usage details for read_file',
                ],
                [
                    'code' => "{$this->name}(tool='read_file', parameter='offset')",
                    'description' => 'Get details for offset parameter',
                ],
                [
                    'code' => "{$this->name}(tool='read_file', detail='full')",
                    'description' => 'Get complete specification',
                ],
            ],
            errors: [
                [
                    'type' => 'InvalidArgumentException',
                    'when' => 'Tool name not found in registry',
                ],
            ],
            notes: [
                'Level 1 (no args): Browse available tools',
                'Level 2 (tool): Get usage details',
                'Level 3 (parameter or detail=full): Deep dive',
            ],
        );
    }
}
```

---

## Phase 2: Example Tool Implementations

### Example 1: File Tools Registry

```php
// Create registry
$fileToolRegistry = new ToolRegistry();

// Register file tools with full specs
$fileToolRegistry->register(new ToolSpec(
    name: 'read_file',
    shortDescription: 'Read file contents',
    description: 'Reads a file from the filesystem. Supports reading specific line ranges for large files. Returns file contents as string.',
    usage: 'read_file(file_path="/path/to/file.txt")',
    parameterNames: ['file_path', 'offset', 'limit'],
    returns: 'string',
    parameters: [
        'file_path' => [
            'type' => 'string',
            'description' => 'Absolute path to the file to read',
            'required' => true,
            'validation' => 'Must be absolute path',
        ],
        'offset' => [
            'type' => 'integer',
            'description' => 'Line number to start reading from (1-indexed)',
            'required' => false,
            'default' => 1,
        ],
        'limit' => [
            'type' => 'integer',
            'description' => 'Maximum number of lines to read',
            'required' => false,
            'default' => null,
        ],
    ],
    examples: [
        ['code' => 'read_file(file_path="/config/app.yaml")', 'description' => 'Read entire file'],
        ['code' => 'read_file(file_path="/logs/app.log", offset=100, limit=50)', 'description' => 'Read lines 100-150'],
    ],
    errors: [
        ['type' => 'FileNotFoundException', 'when' => 'File does not exist'],
        ['type' => 'PermissionException', 'when' => 'File not readable'],
    ],
    notes: [
        'For files > 10MB, consider using offset/limit',
        'Line endings preserved as-is',
    ],
));

$fileToolRegistry->register(new ToolSpec(
    name: 'write_file',
    shortDescription: 'Write to file',
    description: 'Writes content to a file on the filesystem. Creates file if it doesn\'t exist, overwrites by default. Supports append mode.',
    usage: 'write_file(file_path="/path/to/file.txt", content="Hello World")',
    parameterNames: ['file_path', 'content', 'mode'],
    returns: 'bool',
    parameters: [
        'file_path' => [
            'type' => 'string',
            'description' => 'Absolute path to the file to write',
            'required' => true,
        ],
        'content' => [
            'type' => 'string',
            'description' => 'Content to write to file',
            'required' => true,
        ],
        'mode' => [
            'type' => 'string',
            'description' => 'Write mode: overwrite or append',
            'required' => false,
            'default' => 'overwrite',
            'enum' => ['overwrite', 'append'],
        ],
    ],
    examples: [
        ['code' => 'write_file(file_path="/tmp/output.txt", content="Hello")', 'description' => 'Create/overwrite file'],
        ['code' => 'write_file(file_path="/tmp/log.txt", content="Entry", mode="append")', 'description' => 'Append to file'],
    ],
    errors: [
        ['type' => 'PermissionException', 'when' => 'Directory not writable'],
    ],
    notes: [
        'Creates parent directories automatically',
        'Atomic write - file updated only on success',
    ],
));

$fileToolRegistry->register(new ToolSpec(
    name: 'search_files',
    shortDescription: 'Find files by pattern',
    description: 'Searches for files matching a glob pattern. Supports recursive search and file type filtering. Returns list of matching file paths.',
    usage: 'search_files(pattern="*.php", path="src/")',
    parameterNames: ['pattern', 'path', 'recursive', 'file_type'],
    returns: 'array',
    parameters: [
        'pattern' => [
            'type' => 'string',
            'description' => 'Glob pattern to match files',
            'required' => true,
            'examples' => ['*.php', '**/*.test.js', 'config.{yaml,yml}'],
        ],
        'path' => [
            'type' => 'string',
            'description' => 'Directory to search in',
            'required' => false,
            'default' => '.',
        ],
        'recursive' => [
            'type' => 'boolean',
            'description' => 'Search subdirectories recursively',
            'required' => false,
            'default' => true,
        ],
        'file_type' => [
            'type' => 'string',
            'description' => 'Filter by file type',
            'required' => false,
            'enum' => ['file', 'directory', 'symlink'],
        ],
    ],
    examples: [
        ['code' => 'search_files(pattern="*.php", path="src/")', 'description' => 'Find all PHP files'],
        ['code' => 'search_files(pattern="test_*.py", recursive=false)', 'description' => 'Non-recursive search'],
    ],
    errors: [
        ['type' => 'PathNotFoundException', 'when' => 'Search path does not exist'],
    ],
    notes: [
        'Results sorted by modification time',
        'Symlinks followed by default',
    ],
));

// Create discovery tool
$discoverFileTool = new DiscoverToolsTool(
    name: 'discover_file_tools',
    category: 'file operations',
    registry: $fileToolRegistry,
);

// Add to agent
$agent = AgentBuilder::base()
    ->withTool($discoverFileTool)
    ->build();
```

### Example 2: Agent Discovery

```php
// packages/addons/src/Agent/Tools/DiscoverAgentsTool.php
namespace Cognesy\Addons\Agent\Tools;

use Cognesy\Addons\Agent\Registry\AgentRegistry;
use Cognesy\Instructor\Tools\Tool;
use Cognesy\Instructor\Tools\ToolSpec;

class DiscoverAgentsTool implements Tool
{
    public function __construct(
        private AgentRegistry $registry,
    ) {}

    public function __invoke(array $args = []): array {
        // Level 1: List agents
        if (empty($args['agent'])) {
            return $this->level1_list($args['category'] ?? null);
        }

        $agentName = $args['agent'];

        if (!$this->registry->has($agentName)) {
            throw new \InvalidArgumentException(
                "Agent '{$agentName}' not found. Available: " . implode(', ', $this->registry->names())
            );
        }

        $spec = $this->registry->get($agentName);

        // Level 3: Full specification
        if (isset($args['detail']) && $args['detail'] === 'full') {
            return $this->level3_full($spec);
        }

        // Level 2: Agent details
        return $this->level2_details($spec);
    }

    private function level1_list(?string $category): array {
        $agents = [];
        foreach ($this->registry->all() as $spec) {
            // Filter by category if provided
            if ($category && !in_array($category, $spec->capabilities)) {
                continue;
            }

            $agents[] = [
                'name' => $spec->name,
                'summary' => $spec->description,
            ];
        }
        return ['agents' => $agents];
    }

    private function level2_details($spec): array {
        return [
            'name' => $spec->name,
            'description' => $spec->description,
            'whenToUse' => $spec->whenToUse,
            'capabilities' => $spec->capabilities,
            'constraints' => $spec->constraints,
        ];
    }

    private function level3_full($spec): array {
        return [
            'name' => $spec->name,
            'description' => $spec->description,
            'whenToUse' => $spec->whenToUse,
            'systemPrompt' => $spec->systemPrompt,
            'tools' => $spec->tools,
            'prohibitedTools' => $spec->prohibitedTools,
            'capabilities' => $spec->capabilities,
            'constraints' => $spec->constraints,
        ];
    }

    public function getSpec(): ToolSpec {
        return new ToolSpec(
            name: 'discover_agents',
            shortDescription: 'Discover specialized agents',
            description: 'Discover available specialized agents that can help with tasks. Call without arguments to list all agents, with agent name to get details, or with detail="full" for complete specification.',
            usage: 'discover_agents()',
            parameterNames: ['agent', 'category', 'detail'],
            returns: 'array',
            parameters: [
                'agent' => [
                    'type' => 'string',
                    'description' => 'Optional: agent name to get details for',
                    'required' => false,
                ],
                'category' => [
                    'type' => 'string',
                    'description' => 'Optional: filter by capability category',
                    'required' => false,
                ],
                'detail' => [
                    'type' => 'string',
                    'description' => 'Optional: "full" for complete specification',
                    'required' => false,
                    'enum' => ['full'],
                ],
            ],
            examples: [
                ['code' => 'discover_agents()', 'description' => 'List all agents'],
                ['code' => 'discover_agents(category="code-analysis")', 'description' => 'List code analysis agents'],
                ['code' => 'discover_agents(agent="explorer")', 'description' => 'Get explorer details'],
                ['code' => 'discover_agents(agent="explorer", detail="full")', 'description' => 'Get complete spec'],
            ],
            errors: [],
            notes: [
                'Use category to filter by capability',
                'Level 2 shows when to use and constraints',
                'Level 3 includes system prompt and tools',
            ],
        );
    }
}
```

---

## Phase 3: Usage Examples

### Complete Example: Multi-Domain Discovery

```php
<?php
use Cognesy\Addons\Agent\AgentBuilder;
use Cognesy\Instructor\Tools\ToolRegistry;
use Cognesy\Instructor\Tools\DiscoverToolsTool;
use Cognesy\Addons\Agent\Tools\DiscoverAgentsTool;
use Cognesy\Addons\Agent\Registry\AgentRegistry;
use Cognesy\Addons\Agent\Capabilities\Subagent\UseSubagents;

// Setup file tools registry
$fileToolRegistry = new ToolRegistry();
$fileToolRegistry->register(/* ... file tool specs ... */);

// Setup batch tools registry
$batchToolRegistry = new ToolRegistry();
$batchToolRegistry->register(/* ... batch tool specs ... */);

// Setup agent registry
$agentRegistry = new AgentRegistry();
$agentRegistry->loadFromDirectory(__DIR__ . '/agents');

// Build agent with multiple discovery tools
$agent = AgentBuilder::base()
    ->withSystemPrompt('You are a development assistant.')

    // File tools discovery
    ->withTool(new DiscoverToolsTool(
        name: 'discover_file_tools',
        category: 'file operations',
        registry: $fileToolRegistry,
    ))

    // Batch tools discovery
    ->withTool(new DiscoverToolsTool(
        name: 'discover_batch_tools',
        category: 'batch/shell commands',
        registry: $batchToolRegistry,
    ))

    // Agent discovery
    ->withTool(new DiscoverAgentsTool($agentRegistry))

    // Subagent spawning
    ->withCapability(new UseSubagents($agentRegistry))

    ->build();

// Example interaction
$question = "Read the database config file and list all migration files";
$state = AgentState::empty()->withMessages(Messages::fromString($question));

while ($agent->hasNextStep($state)) {
    $state = $agent->nextStep($state);

    $step = $state->currentStep();
    echo "Step {$state->stepCount()}: [{$step->stepType()->value}]\n";

    if ($step->hasToolCalls()) {
        foreach ($step->toolCalls()->all() as $toolCall) {
            echo "  → {$toolCall->name()}()\n";
        }
    }
}

// Expected workflow:
// Step 1: [tool_use]
//   → discover_file_tools()
// Step 2: [tool_use]
//   → discover_file_tools(tool="read_file")
// Step 3: [tool_use]
//   → read_file(file_path="config/database.yaml")
// Step 4: [tool_use]
//   → discover_file_tools(tool="search_files")
// Step 5: [tool_use]
//   → search_files(pattern="*_migration.sql", path="migrations/")
// Step 6: [final_response]
```

---

## Phase 4: Testing

### Unit Tests for Discovery Tool

```php
use Cognesy\Instructor\Tools\ToolRegistry;
use Cognesy\Instructor\Tools\DiscoverToolsTool;
use Cognesy\Instructor\Tools\ToolSpec;

test('discovery tool supports level 1 (browse)', function() {
    $registry = new ToolRegistry();
    $registry->register(new ToolSpec(
        name: 'test_tool',
        shortDescription: 'Test tool',
        description: 'Detailed description',
        usage: 'test_tool()',
        parameterNames: ['param1'],
        returns: 'string',
        parameters: [],
        examples: [],
        errors: [],
        notes: [],
    ));

    $discovery = new DiscoverToolsTool('discover_test', 'test', $registry);

    $result = $discovery([]);

    expect($result)->toHaveKey('tools');
    expect($result['tools'])->toHaveCount(1);
    expect($result['tools'][0])->toMatchArray([
        'name' => 'test_tool',
        'summary' => 'Test tool',
    ]);
});

test('discovery tool supports level 2 (details)', function() {
    $registry = new ToolRegistry();
    $registry->register(new ToolSpec(
        name: 'test_tool',
        shortDescription: 'Test tool',
        description: 'Detailed description',
        usage: 'test_tool(param1="value")',
        parameterNames: ['param1', 'param2'],
        returns: 'string',
        parameters: [
            'param1' => ['type' => 'string', 'required' => true],
            'param2' => ['type' => 'integer', 'required' => false],
        ],
        examples: [],
        errors: [],
        notes: [],
    ));

    $discovery = new DiscoverToolsTool('discover_test', 'test', $registry);

    $result = $discovery(['tool' => 'test_tool']);

    expect($result)->toMatchArray([
        'name' => 'test_tool',
        'description' => 'Detailed description',
        'parameters' => ['param1', 'param2'],
        'usage' => 'test_tool(param1="value")',
        'returns' => 'string',
    ]);
});

test('discovery tool supports level 3 (full spec)', function() {
    $registry = new ToolRegistry();
    $registry->register(new ToolSpec(
        name: 'test_tool',
        shortDescription: 'Test tool',
        description: 'Detailed description',
        usage: 'test_tool()',
        parameterNames: ['param1'],
        returns: 'string',
        parameters: [
            'param1' => ['type' => 'string', 'required' => true, 'description' => 'First parameter'],
        ],
        examples: [['code' => 'test_tool(param1="x")', 'description' => 'Example']],
        errors: [['type' => 'TestException', 'when' => 'When error occurs']],
        notes: ['Note 1', 'Note 2'],
    ));

    $discovery = new DiscoverToolsTool('discover_test', 'test', $registry);

    $result = $discovery(['tool' => 'test_tool', 'detail' => 'full']);

    expect($result)->toHaveKey('parameters');
    expect($result['parameters']['param1'])->toMatchArray([
        'type' => 'string',
        'required' => true,
        'description' => 'First parameter',
    ]);
    expect($result)->toHaveKey('examples');
    expect($result)->toHaveKey('errors');
    expect($result)->toHaveKey('notes');
});

test('discovery tool supports level 3 (parameter detail)', function() {
    $registry = new ToolRegistry();
    $registry->register(new ToolSpec(
        name: 'test_tool',
        shortDescription: 'Test tool',
        description: 'Detailed description',
        usage: 'test_tool()',
        parameterNames: ['param1'],
        returns: 'string',
        parameters: [
            'param1' => [
                'type' => 'string',
                'required' => true,
                'description' => 'First parameter',
                'validation' => 'Must not be empty',
            ],
        ],
        examples: [],
        errors: [],
        notes: [],
    ));

    $discovery = new DiscoverToolsTool('discover_test', 'test', $registry);

    $result = $discovery(['tool' => 'test_tool', 'parameter' => 'param1']);

    expect($result)->toMatchArray([
        'tool' => 'test_tool',
        'parameter' => 'param1',
        'type' => 'string',
        'required' => true,
        'description' => 'First parameter',
        'validation' => 'Must not be empty',
    ]);
});

test('discovery tool throws on unknown tool', function() {
    $registry = new ToolRegistry();
    $discovery = new DiscoverToolsTool('discover_test', 'test', $registry);

    $discovery(['tool' => 'nonexistent']);
})->throws(\InvalidArgumentException::class, 'Tool \'nonexistent\' not found');
```

---

## Conclusion

This implementation guide provides:
- ✅ Complete ToolSpec with three disclosure levels
- ✅ ToolRegistry for managing tool specifications
- ✅ DiscoverToolsTool with progressive disclosure
- ✅ DiscoverAgentsTool for agent discovery
- ✅ Complete examples and test coverage

**Next**: See `04-examples.md` for real-world usage scenarios.
