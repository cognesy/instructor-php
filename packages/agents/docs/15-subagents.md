---
title: 'Subagents'
description: 'Delegate work to isolated child agents with their own tools and limits'
---

# Subagents

Subagents let one agent delegate part of the work to another agent definition.
Each child runs with its own state, budget, and tool visibility.

## Quick Start

```php
use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Capability\Core\UseGuards;
use Cognesy\Agents\Capability\Core\UseTools;
use Cognesy\Agents\Capability\File\SearchFilesTool;
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
    tools: new NameList('read_file'),
));

$agent = AgentBuilder::base()
    ->withCapability(new UseFileTools('/my/project'))
    ->withCapability(new UseTools(SearchFilesTool::inDirectory('/my/project')))
    ->withCapability(new UseSubagents(provider: $registry))
    ->withCapability(new UseGuards(maxSteps: 20))
    ->build();

$state = AgentState::empty()->withUserMessage(
    'Review src/AgentLoop.php and summarize key issues.'
);
$result = $agent->execute($state);
```

The parent model decides when to call `spawn_subagent`.

## What Happens

1. parent calls `spawn_subagent(subagent, prompt)`
2. registry resolves the `AgentDefinition`
3. child `AgentLoop` is created from the definition
4. child runs to completion
5. child result is returned as tool output to parent

## Defining Subagents

```php
use Cognesy\Agents\Collections\NameList;
use Cognesy\Agents\Data\ExecutionBudget;
use Cognesy\Agents\Template\Data\AgentDefinition;

$registry->register(new AgentDefinition(
    name: 'researcher',
    description: 'Search and analyze source files',
    systemPrompt: 'Find relevant evidence and summarize it.',
    tools: new NameList('read_file', 'search_files'),
    budget: new ExecutionBudget(maxSteps: 8, maxTokens: 4000),
));
```

You can also load definitions from files with `AgentDefinitionRegistry`.

## Tool Visibility

Child tools are controlled by `AgentDefinition`:

- omit `tools` to inherit all parent tools
- set `tools` to create an allow-list
- use `toolsDeny` to remove tools from the inherited or allowed set

```php
new AgentDefinition(
    name: 'safe_editor',
    description: 'Edit files without shell access',
    systemPrompt: 'Edit files safely.',
    tools: new NameList('read_file', 'write_file', 'edit_file'),
    toolsDeny: new NameList('write_file'),
);
```

## Depth Control

Use `SubagentPolicy` or `UseSubagents::forDepth()` to cap recursion:

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

$agent = AgentBuilder::base()
    ->withCapability(UseSubagents::forDepth(2, provider: $registry))
    ->build();
```

If depth is exceeded, subagent execution fails with `SubagentDepthExceededException`.

## Child Budgets and Models

Each child can declare its own budget and model in the definition:

```php
use Cognesy\Agents\Data\ExecutionBudget;
use Cognesy\Polyglot\Inference\Config\LLMConfig;

new AgentDefinition(
    name: 'quick_reviewer',
    description: 'Short, fast review',
    systemPrompt: 'Be concise.',
    llmConfig: LLMConfig::fromArray([
        'driver' => 'openai',
        'model' => 'gpt-4o-mini',
    ]),
    budget: new ExecutionBudget(maxSteps: 5, maxTokens: 2500, maxSeconds: 20.0),
);
```

Parent limits stay on the parent builder, usually via `UseGuards`.

## Events

Subagent lifecycle emits `SubagentSpawning` and `SubagentCompleted`:

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
