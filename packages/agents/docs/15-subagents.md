---
title: 'Subagents'
description: 'Delegate work to isolated child agents with their own tools and limits'
---

# Subagents

Subagents let a parent agent delegate focused tasks to child agents.
Each child runs with isolated state, tool visibility, and execution budget.

## Quick Start

```php
use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Capability\Core\UseGuards;
use Cognesy\Agents\Capability\File\UseFileTools;
use Cognesy\Agents\Capability\Subagent\UseSubagents;
use Cognesy\Agents\Collections\NameList;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Template\AgentDefinitionRegistry;
use Cognesy\Agents\Template\Data\AgentDefinition;

$registry = new AgentDefinitionRegistry();
$registry->register(new AgentDefinition(
    name: 'reviewer',
    description: 'Review one file and report important issues.',
    systemPrompt: 'You review code and report only high-signal findings.',
    tools: new NameList(['read_file']),
));

$agent = AgentBuilder::base()
    ->withCapability(new UseFileTools('/my/project'))
    ->withCapability(new UseSubagents(provider: $registry))
    ->withCapability(new UseGuards(maxSteps: 20))
    ->build();

$state = AgentState::empty()->withUserMessage(
    'Review src/AgentLoop.php and summarize key issues.'
);
$result = $agent->execute($state);
```

The parent LLM decides when to call `spawn_subagent`.

## What Happens

1. parent calls `spawn_subagent(subagent, prompt)`
2. registry resolves `AgentDefinition`
3. child `AgentLoop` is created from the definition
4. child runs to completion
5. child result is returned as tool output to parent

The parent does not receive the child internal trace by default. It receives the child outcome.

## Defining Subagents

### Programmatic definitions

```php
use Cognesy\Agents\Collections\NameList;
use Cognesy\Agents\Data\ExecutionBudget;
use Cognesy\Agents\Template\Data\AgentDefinition;

$registry->register(new AgentDefinition(
    name: 'researcher',
    description: 'Search and analyze source files',
    systemPrompt: 'Find relevant evidence and summarize it.',
    tools: new NameList(['read_file', 'search_files']),
    budget: new ExecutionBudget(maxSteps: 8, maxTokens: 4000),
));
```

### File-based definitions

```php
$registry->loadFromDirectory('/agents', recursive: true);
```

See [Agent Templates](14-agent-templates.md) for markdown/yaml/json formats.

## Tool Visibility

Child tools are controlled by `AgentDefinition`.

- Programmatic definitions: omitting `tools` (constructor default `null`) inherits all parent tools
- `tools` means allow-list
- `toolsDeny` means remove tools from inherited/allowed set

```php
new AgentDefinition(
    name: 'safe_editor',
    description: 'Edit files without shell access',
    systemPrompt: 'Edit files safely.',
    tools: new NameList(['read_file', 'write_file', 'edit_file']),
    toolsDeny: new NameList(['write_file']),
);
```

## Depth Control

Use `SubagentPolicy` or `UseSubagents::withDepth()` to cap recursion.

```php
use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Capability\Subagent\SubagentPolicy;
use Cognesy\Agents\Capability\Subagent\UseSubagents;

$agent = AgentBuilder::base()
    ->withCapability(new UseSubagents(
        provider: $registry,
        policy: new SubagentPolicy(maxDepth: 2),
    ))
    ->build();

// Equivalent shortcut:
$agent = AgentBuilder::base()
    ->withCapability(UseSubagents::withDepth(2, provider: $registry))
    ->build();
```

If depth is exceeded, subagent tool execution fails with `SubagentDepthExceededException`.

## Child Budgets and Models

Each child can set its own budget and model via definition fields.

```php
use Cognesy\Agents\Data\ExecutionBudget;

new AgentDefinition(
    name: 'quick_reviewer',
    description: 'Short, fast review',
    systemPrompt: 'Be concise.',
    llmConfig: 'openai:gpt-4o-mini',
    budget: new ExecutionBudget(maxSteps: 5, maxTokens: 2500, maxSeconds: 20.0),
);
```

Parent limits are separate and configured on the parent builder (for example `UseGuards`).

## Events

Subagent lifecycle emits:

- `SubagentSpawning`
- `SubagentCompleted`

```php
use Cognesy\Agents\Events\SubagentCompleted;
use Cognesy\Agents\Events\SubagentSpawning;

$agent->onEvent(SubagentSpawning::class, function (SubagentSpawning $e) {
    echo "Spawning {$e->subagentName} at depth {$e->depth}\n";
});

$agent->onEvent(SubagentCompleted::class, function (SubagentCompleted $e) {
    echo "Completed {$e->subagentName} in {$e->steps} steps\n";
});
```

## Testing

Use `FakeAgentDriver` and child steps.

```php
use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Capability\Core\UseDriver;
use Cognesy\Agents\Capability\Subagent\UseSubagents;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Agents\Drivers\Testing\ScenarioStep;

$driver = (new FakeAgentDriver([
    ScenarioStep::toolCall('spawn_subagent', [
        'subagent' => 'reviewer',
        'prompt' => 'Review this file',
    ], executeTools: true),
    ScenarioStep::final('Review complete.'),
]))->withChildSteps([
    ScenarioStep::final('Found one issue.'),
]);

$agent = AgentBuilder::base()
    ->withCapability(new UseDriver($driver))
    ->withCapability(new UseSubagents(provider: $registry))
    ->build();

$result = $agent->execute(AgentState::empty());
```

## ResearchSubagentTool

`ResearchSubagentTool` is a ready-made tool for simple research use cases.

```php
use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Capability\Core\UseTools;
use Cognesy\Agents\Capability\Subagent\ResearchSubagentTool;

$tool = ResearchSubagentTool::inDirectory('/my/project');

$agent = AgentBuilder::base()
    ->withCapability(new UseTools($tool))
    ->build();
```

## Troubleshooting

- `SubagentNotFoundException`: subagent name missing in registry
- `SubagentDepthExceededException`: recursion limit reached
- `SubagentExecutionException`: child execution failed

If a child fails, the parent sees it as a tool failure and can decide how to continue.

## Related

- [Agent Templates](14-agent-templates.md)
- [Tool Calling Internals](12-tool-calling-internals.md)
- [Session Runtime](16-session-runtime.md)
