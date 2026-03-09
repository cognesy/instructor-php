---
title: 'Agent Templates'
description: 'Define agents as data using markdown, YAML, or JSON files and instantiate them via registries and factories'
---

# Agent Templates

Agent templates let you define agents as data and build them at runtime.
They are the simplest way to keep agent setup out of PHP code.

## AgentDefinition

`AgentDefinition` is the core template object.
The fields you will use most often are:

- `name`
- `description`
- `systemPrompt`
- `llmConfig`
- `capabilities`
- `tools`
- `toolsDeny`
- `skills`
- `budget`
- `metadata`

Minimal example:

```php
use Cognesy\Agents\Collections\NameList;
use Cognesy\Agents\Data\ExecutionBudget;
use Cognesy\Agents\Template\Data\AgentDefinition;
use Cognesy\Polyglot\Inference\Config\LLMConfig;

$definition = new AgentDefinition(
    name: 'researcher',
    description: 'Searches for information on a topic',
    systemPrompt: 'You are a research assistant. Find and summarize information.',
    label: 'Research Agent',
    llmConfig: LLMConfig::fromArray(['driver' => 'anthropic']),
    budget: new ExecutionBudget(maxSteps: 10, maxTokens: 8000),
    tools: new NameList('bash', 'read_file'),
    toolsDeny: new NameList('write_file'),
    capabilities: new NameList('use_bash'),
);
```

All fields except `name`, `description`, and `systemPrompt` are optional.

## Tool Rules

- `tools: null` means inherit all available tools
- `tools` means allow only those tools
- `toolsDeny` removes tools from the inherited or allowed set

## Definition Files

### Markdown

```markdown
---
name: researcher
description: Searches for information on a topic
label: Research Agent
llmConfig:
  driver: anthropic
budget:
  maxSteps: 10
  maxTokens: 8000
tools:
  - bash
  - read_file
capabilities:
  - use_bash
---

You are a research assistant. Find and summarize information accurately.
```

The body becomes `systemPrompt`.

### YAML

```yaml
name: researcher
description: Searches for information on a topic
systemPrompt: |
  You are a research assistant. Find and summarize information.
llmConfig:
  driver: anthropic
budget:
  maxSteps: 10
```

### JSON

```json
{
  "name": "researcher",
  "description": "Searches for information on a topic",
  "systemPrompt": "You are a research assistant.",
  "llmConfig": { "driver": "anthropic" },
  "budget": { "maxSteps": 10 }
}
```

## Loading Definitions

### AgentDefinitionLoader

```php
use Cognesy\Agents\Template\AgentDefinitionLoader;

$loader = new AgentDefinitionLoader();
$definition = $loader->loadFile('/path/to/researcher.md');
```

Supported extensions: `.md`, `.json`, `.yaml`, `.yml`.

### AgentDefinitionRegistry

```php
use Cognesy\Agents\Template\AgentDefinitionRegistry;

$registry = new AgentDefinitionRegistry();

$registry->register($definition);
$registry->registerMany($def1, $def2, $def3);

$registry->loadFromFile('/agents/researcher.md');
$registry->loadFromDirectory('/agents', recursive: true);

// Load order is: userPath, packagePath, projectPath/.claude/agents.
$registry->autoDiscover('/my/project', '/package/agents', '/user/agents');

$registry->get('researcher');
$registry->has('researcher');
$registry->names();
$registry->count();
```

Loading errors are collected during directory scans.
Check `$registry->errors()` when you want to inspect skipped files.

## Instantiation Factories

### DefinitionStateFactory

```php
use Cognesy\Agents\Template\Factory\DefinitionStateFactory;

$factory = new DefinitionStateFactory();
$state = $factory->instantiateAgentState($definition);
```

### DefinitionLoopFactory

```php
use Cognesy\Agents\Capability\AgentCapabilityRegistry;
use Cognesy\Agents\Capability\Bash\UseBash;
use Cognesy\Agents\Template\Factory\DefinitionLoopFactory;

$capabilities = new AgentCapabilityRegistry();
$capabilities->register('use_bash', new UseBash());

$factory = new DefinitionLoopFactory($capabilities);
$loop = $factory->instantiateAgentLoop($definition);
```

If the definition uses named tools, provide a tool registry:

```php
use Cognesy\Agents\Tool\ToolRegistry;

$tools = new ToolRegistry();
$tools->register($searchTool);

$factory = new DefinitionLoopFactory(
    capabilities: $capabilities,
    tools: $tools,
);
```

If a definition references tools and no registry is provided, `DefinitionLoopFactory` throws.

## Using with Subagents

`AgentDefinitionRegistry` is the standard provider for `UseSubagents`:

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
