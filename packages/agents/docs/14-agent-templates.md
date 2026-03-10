---
title: 'Agent Templates'
docname: 'agent_templates'
order: 14
id: 'agent-templates'
---

## Introduction

Agent templates let you define agents as data rather than PHP code. Instead of writing a class that constructs an `AgentBuilder` with hardcoded capabilities and tools, you describe the agent's identity, instructions, tool access, and resource budget in a definition file. At runtime, a factory turns that definition into a working `AgentLoop` and `AgentState`.

This separation between definition and instantiation makes it possible to manage agents through configuration files, version them alongside your prompts, and let non-developers create or adjust agents without touching PHP. It is also the foundation of the subagent system -- when a parent agent spawns a child, it looks up the child's `AgentDefinition` in a registry and builds a loop from it on the fly.

## AgentDefinition

`AgentDefinition` is the core data object that describes an agent. It is a `final readonly` class with the following fields:

| Field | Type | Required | Description |
|---|---|---|---|
| `name` | `string` | Yes | Unique identifier used to look up the agent in registries |
| `description` | `string` | Yes | Human-readable summary of what the agent does. Also shown in tool schemas when the agent is available as a subagent. |
| `systemPrompt` | `string` | Yes | The system prompt that instructs the agent's behavior |
| `label` | `string\|null` | No | Display name (defaults to `name` if omitted) |
| `llmConfig` | `LLMConfig\|string\|null` | No | LLM configuration. Pass a string like `'anthropic'` for just the driver name, or a full `LLMConfig` object for model-level control. |
| `capabilities` | `NameList` | No | Named capabilities to activate (looked up in `AgentCapabilityRegistry`) |
| `tools` | `NameList\|null` | No | Allow-list of tool names. `null` means inherit all available tools. |
| `toolsDeny` | `NameList\|null` | No | Deny-list of tool names to exclude from the inherited or allowed set |
| `skills` | `NameList\|null` | No | Named skills to inject into the agent's context |
| `budget` | `ExecutionBudget\|null` | No | Resource limits: max steps, tokens, seconds, cost, and deadline |
| `metadata` | `Metadata\|null` | No | Arbitrary key-value data merged into the agent's state |

### Creating Definitions in PHP

```php
use Cognesy\Agents\Collections\NameList;
use Cognesy\Agents\Data\ExecutionBudget;
use Cognesy\Agents\Template\Data\AgentDefinition;
use Cognesy\Polyglot\Inference\Config\LLMConfig;

$definition = new AgentDefinition(
    name: 'researcher',
    description: 'Searches for information on a topic and summarizes findings',
    systemPrompt: 'You are a research assistant. Find and summarize information accurately.',
    label: 'Research Agent',
    llmConfig: LLMConfig::fromArray([
        'driver' => 'anthropic',
        'model' => 'claude-sonnet-4-20250514',
    ]),
    budget: new ExecutionBudget(maxSteps: 10, maxTokens: 8000),
    tools: new NameList('bash', 'read_file'),
    toolsDeny: new NameList('write_file'),
    capabilities: new NameList('use_bash'),
);
```

### Tool Visibility Rules

The `tools` and `toolsDeny` fields work together to control which tools the agent can access:

- **`tools: null`** (the default) -- the agent inherits all tools available in its context. For subagents, this means all tools the parent has.
- **`tools: new NameList('read_file', 'bash')`** -- only these named tools are allowed. Any other tools are excluded.
- **`toolsDeny: new NameList('write_file')`** -- these tools are removed from whatever set the agent would otherwise have, whether inherited or explicitly allowed.

The deny list is applied after the allow list. If you set `tools` to allow `read_file` and `write_file`, and `toolsDeny` to deny `write_file`, the agent will only have access to `read_file`.

### ExecutionBudget

The `ExecutionBudget` class defines resource limits for a single agent execution. All fields are optional -- `null` means unlimited.

```php
use Cognesy\Agents\Data\ExecutionBudget;

$budget = new ExecutionBudget(
    maxSteps: 10,         // maximum number of agent loop iterations
    maxTokens: 8000,      // total token usage across all steps
    maxSeconds: 60.0,     // wall-clock time limit
    maxCost: 0.50,        // maximum cost in dollars
    deadline: new DateTimeImmutable('2025-12-31'),
);
```

When an `AgentDefinition` declares a budget, it is translated into `UseGuards` during loop instantiation.

## Definition Files

Agent definitions can be stored in markdown, YAML, or JSON files. Each format maps directly to the `AgentDefinition` fields.

### Markdown Format

Markdown definitions use YAML front matter for structured fields and the document body for the system prompt. This is the most readable format for agents with long or complex system prompts.

```markdown
---
name: researcher
description: Searches for information on a topic
label: Research Agent
llmConfig:
  driver: anthropic
  model: claude-sonnet-4-20250514
budget:
  maxSteps: 10
  maxTokens: 8000
tools:
  - bash
  - read_file
toolsDeny:
  - write_file
capabilities:
  - use_bash
metadata:
  domain: research
  version: "2.0"
---

You are a research assistant. Your job is to find and summarize information accurately.

When given a topic, use the available tools to gather evidence, then synthesize your findings
into a clear, well-structured summary. Always cite the sources you used.
```

The document body (everything after the front matter) becomes the `systemPrompt` field.

### YAML Format

```yaml
name: researcher
description: Searches for information on a topic
systemPrompt: |
  You are a research assistant. Find and summarize information accurately.
  Always cite the sources you used.
llmConfig:
  driver: anthropic
budget:
  maxSteps: 10
  maxTokens: 8000
tools:
  - bash
  - read_file
```

### JSON Format

```json
{
  "name": "researcher",
  "description": "Searches for information on a topic",
  "systemPrompt": "You are a research assistant. Find and summarize information accurately.",
  "llmConfig": {
    "driver": "anthropic"
  },
  "budget": {
    "maxSteps": 10,
    "maxTokens": 8000
  },
  "tools": ["bash", "read_file"]
}
```

All three formats produce identical `AgentDefinition` objects when loaded.

## Loading Definitions

### AgentDefinitionLoader

The `AgentDefinitionLoader` class parses a single file into an `AgentDefinition`. It selects the appropriate parser based on the file extension.

```php
use Cognesy\Agents\Template\AgentDefinitionLoader;

$loader = new AgentDefinitionLoader();
$definition = $loader->loadFile('/path/to/researcher.md');
```

Supported extensions: `.md`, `.json`, `.yaml`, `.yml`. The loader throws a `RuntimeException` if the file cannot be read and an `InvalidArgumentException` for unsupported extensions.

You can also supply custom parsers by passing an array to the constructor:

```php
use Cognesy\Agents\Template\Parsers\MarkdownDefinitionParser;
use Cognesy\Agents\Template\Parsers\JsonDefinitionParser;
use Cognesy\Agents\Template\Parsers\YamlDefinitionParser;

$loader = new AgentDefinitionLoader([
    'md' => new MarkdownDefinitionParser(),
    'json' => new JsonDefinitionParser(),
    'yaml' => new YamlDefinitionParser(),
    'yml' => new YamlDefinitionParser(),
]);
```

### AgentDefinitionRegistry

The `AgentDefinitionRegistry` is a named collection of agent definitions. It supports programmatic registration, file loading, directory scanning, and auto-discovery.

```php
use Cognesy\Agents\Template\AgentDefinitionRegistry;

$registry = new AgentDefinitionRegistry();
```

#### Programmatic Registration

```php
$registry->register($definition);
$registry->registerMany($def1, $def2, $def3);
```

#### Loading from Files

```php
// Load a single file
$registry->loadFromFile('/agents/researcher.md');

// Load all definition files from a directory
$registry->loadFromDirectory('/agents');

// Load recursively, scanning subdirectories
$registry->loadFromDirectory('/agents', recursive: true);
```

During directory scans, files that fail to parse are skipped rather than causing exceptions. The errors are collected and can be inspected afterward:

```php
$errors = $registry->errors();
// Returns: ['path/to/broken.md' => 'Error message', ...]
```

#### Auto-Discovery

The `autoDiscover()` method scans up to three standard locations for agent definition files:

```php
$registry->autoDiscover(
    projectPath: '/my/project',      // scans /my/project/.claude/agents
    packagePath: '/package/agents',   // scans this directory directly
    userPath: '/user/agents',         // scans this directory directly
);
```

Paths are scanned in order: `userPath`, `packagePath`, then `projectPath/.claude/agents`. Later registrations overwrite earlier ones with the same name, so user-level definitions take precedence over package defaults.

#### Querying the Registry

```php
$definition = $registry->get('researcher');    // throws AgentNotFoundException if missing
$exists = $registry->has('researcher');        // returns bool
$names = $registry->names();                   // returns ['researcher', 'reviewer', ...]
$count = $registry->count();                   // returns int
$all = $registry->all();                       // returns ['name' => AgentDefinition, ...]
```

## Instantiation Factories

Once you have an `AgentDefinition`, two factory classes turn it into runnable components: one for the initial `AgentState`, and one for the `AgentLoop` that executes it.

### DefinitionStateFactory

Creates an `AgentState` pre-configured with the definition's system prompt, metadata, and LLM config. It implements the `CanInstantiateAgentState` contract.

```php
use Cognesy\Agents\Template\Factory\DefinitionStateFactory;

$factory = new DefinitionStateFactory();
$state = $factory->instantiateAgentState($definition);
```

You can also pass a seed state to merge the definition's settings onto an existing state:

```php
$existingState = AgentState::empty()->withUserMessage('Start here');
$state = $factory->instantiateAgentState($definition, seed: $existingState);
```

The factory applies settings in this order: system prompt, metadata merge, then LLM config. Each step is skipped if the corresponding field in the definition is empty or null.

### DefinitionLoopFactory

Creates a fully configured `AgentLoop` from a definition. This factory implements `CanInstantiateAgentLoop` and is used internally by `SendMessage` and other session actions.

```php
use Cognesy\Agents\Capability\AgentCapabilityRegistry;
use Cognesy\Agents\Capability\Bash\UseBash;
use Cognesy\Agents\Template\Factory\DefinitionLoopFactory;

$capabilities = new AgentCapabilityRegistry();
$capabilities->register('use_bash', new UseBash());

$factory = new DefinitionLoopFactory($capabilities);
$loop = $factory->instantiateAgentLoop($definition);
```

The factory builds the loop by applying the definition's fields in order:

1. **LLM config** -- if the definition specifies an `llmConfig`, a `ToolCallingDriver` is created with that config.
2. **Guards** -- if the definition declares a non-empty budget, `UseGuards` is applied with the budget's limits.
3. **Capabilities** -- each named capability in the definition is resolved from the `AgentCapabilityRegistry` and applied to the builder.
4. **Tools** -- if the definition references named tools, they are resolved from the tool registry and added via `UseTools`.

#### Providing a Tool Registry

When the definition references tools by name (via `tools` or `toolsDeny`), you must provide a tool registry that implements `CanManageTools`:

```php
use Cognesy\Agents\Tool\ToolRegistry;

$tools = new ToolRegistry();
$tools->register($searchTool);
$tools->register($readFileTool);

$factory = new DefinitionLoopFactory(
    capabilities: $capabilities,
    tools: $tools,
);
```

If a definition references tools and no registry is provided, `DefinitionLoopFactory` throws an `InvalidArgumentException`. Unknown tool names also cause an exception, listing which tools could not be found.

#### Event Propagation

Pass an event handler to propagate events from instantiated loops to a parent dispatcher:

```php
use Cognesy\Events\Dispatchers\EventDispatcher;

$events = new EventDispatcher('session');
$factory = new DefinitionLoopFactory($capabilities, $tools, $events);
```

## AgentCapabilityRegistry

The `AgentCapabilityRegistry` maps string names to capability instances. It is the bridge between definition files (which reference capabilities by name) and the PHP capability classes that implement them.

```php
use Cognesy\Agents\Capability\AgentCapabilityRegistry;
use Cognesy\Agents\Capability\Bash\UseBash;
use Cognesy\Agents\Capability\File\UseFileTools;

$capabilities = new AgentCapabilityRegistry();

// Register a pre-built instance
$capabilities->register('use_bash', new UseBash());

// Register a factory for lazy instantiation
$capabilities->registerFactory('use_file_tools', fn() => new UseFileTools('/my/project'));

// Query the registry
$capabilities->has('use_bash');     // true
$capabilities->get('use_bash');     // returns the UseBash instance
$capabilities->names();             // ['use_bash', 'use_file_tools']
$capabilities->count();             // 2
```

Factory-registered capabilities are instantiated on first access and cached for subsequent lookups. If the factory does not return a `CanProvideAgentCapability`, an `InvalidArgumentException` is thrown.

## Using with Subagents

The `AgentDefinitionRegistry` implements `CanManageAgentDefinitions`, making it the standard provider for the `UseSubagents` capability. When a parent agent calls `spawn_subagent`, the subagent system looks up the named definition in this registry and builds a child loop from it.

```php
use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Capability\Subagent\UseSubagents;
use Cognesy\Agents\Template\AgentDefinitionRegistry;

$registry = new AgentDefinitionRegistry();
$registry->loadFromDirectory('/agents');

$agent = AgentBuilder::base()
    ->withCapability(new UseSubagents(provider: $registry))
    ->build();
```

The subagent tool's schema automatically includes the list of available agents and their descriptions, so the LLM knows which subagents it can delegate to. See [Subagents](15-subagents.md) for the full delegation model.

## Serialization

`AgentDefinition` supports round-trip serialization via `toArray()` and `fromArray()`:

```php
$array = $definition->toArray();
$restored = AgentDefinition::fromArray($array);
```

This is used internally by the session persistence layer to store agent definitions alongside session state. The `fromArray()` method also accepts `title` as an alias for `label` to support legacy formats.

## Related

- [AgentBuilder & Capabilities](13-agent-builder.md)
- [Subagents](15-subagents.md)
- [Session Runtime](16-session-runtime.md)
