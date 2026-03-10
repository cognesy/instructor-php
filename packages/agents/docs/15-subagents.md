---
title: 'Subagents'
docname: 'subagents'
order: 15
id: 'subagents'
---

## Introduction

Subagents allow one agent to delegate part of its work to another agent that runs in complete isolation. Each child agent has its own state, system prompt, tool set, resource budget, and LLM configuration. The parent agent decides when to delegate by calling the `spawn_subagent` tool, and the child's final output is returned to the parent as a tool result.

This delegation model is useful when different parts of a task require different expertise, tool access, or resource limits. A code review agent might spawn a "security reviewer" subagent with read-only file access and a tight step budget, while also spawning a "style checker" subagent with different instructions. The parent orchestrates the overall workflow without needing to know the implementation details of each child.

## Quick Start

The following example sets up a parent agent with file tools and a "reviewer" subagent that can only read files:

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

// Define the subagent
$registry = new AgentDefinitionRegistry();
$registry->register(new AgentDefinition(
    name: 'reviewer',
    description: 'Review one file and report important issues.',
    systemPrompt: 'You review code and report only high-signal findings.',
    tools: new NameList('read_file'),
));

// Build the parent agent
$agent = AgentBuilder::base()
    ->withCapability(new UseFileTools('/my/project'))
    ->withCapability(new UseTools(SearchFilesTool::inDirectory('/my/project')))
    ->withCapability(new UseSubagents(provider: $registry))
    ->withCapability(new UseGuards(maxSteps: 20))
    ->build();

// Execute
$state = AgentState::empty()->withUserMessage(
    'Review src/AgentLoop.php and summarize key issues.'
);
$result = $agent->execute($state);
```

The parent model decides on its own when to call `spawn_subagent`. The tool schema includes a description of all available subagents and their purposes, giving the LLM the information it needs to choose the right one.

## How Delegation Works

When the parent agent calls `spawn_subagent(subagent: 'reviewer', prompt: 'Review this file...')`, the following sequence occurs:

1. **Depth check** -- the system verifies that the current nesting depth has not exceeded the configured maximum. If it has, a `SubagentDepthExceededException` is thrown and returned to the parent as a tool error.
2. **Definition lookup** -- the `AgentDefinitionRegistry` resolves the named `AgentDefinition`. If the name is not found, a `SubagentNotFoundException` is thrown.
3. **Tool filtering** -- the child's tool set is determined by applying the definition's `tools` allow-list and `toolsDeny` deny-list against the parent's available tools.
4. **Driver resolution** -- the child inherits the parent's tool-use driver. If the definition specifies an `llmConfig` and the driver supports `CanAcceptLLMConfig`, the child's driver is reconfigured with the specified model/provider.
5. **Budget application** -- if the definition declares an `ExecutionBudget`, `UseGuards` is applied to the child's builder with the budget's limits.
6. **Loop construction** -- an `AgentBuilder` assembles the child `AgentLoop` from the filtered tools, configured driver, and guards.
7. **State initialization** -- a fresh `AgentState` is created with the definition's system prompt and the caller's prompt as the user message. If the definition has skills, they are injected as additional system messages.
8. **Execution** -- the child loop runs to completion. If the child fails (`ExecutionStatus::Failed`), a `SubagentExecutionException` is thrown.
9. **Result return** -- the child's final `AgentState` is returned to the parent as the tool call result. The parent continues its execution with this information.

## Defining Subagents

Subagents are defined using the same `AgentDefinition` class used by agent templates. You can register them programmatically or load them from files.

### Programmatic Registration

```php
use Cognesy\Agents\Collections\NameList;
use Cognesy\Agents\Data\ExecutionBudget;
use Cognesy\Agents\Template\Data\AgentDefinition;
use Cognesy\Agents\Template\AgentDefinitionRegistry;

$registry = new AgentDefinitionRegistry();

$registry->register(new AgentDefinition(
    name: 'researcher',
    description: 'Search and analyze source files to find relevant evidence',
    systemPrompt: 'Find relevant evidence and summarize it clearly.',
    tools: new NameList('read_file', 'search_files'),
    budget: new ExecutionBudget(maxSteps: 8, maxTokens: 4000),
));

$registry->register(new AgentDefinition(
    name: 'editor',
    description: 'Make precise edits to source files',
    systemPrompt: 'Apply the requested code changes carefully and verify correctness.',
    tools: new NameList('read_file', 'edit_file'),
    budget: new ExecutionBudget(maxSteps: 12, maxTokens: 8000),
));
```

### File-Based Registration

Definitions can be loaded from `.md`, `.yaml`, or `.json` files:

```php
$registry->loadFromDirectory('/agents', recursive: true);
```

See [Agent Templates](14-agent-templates.md) for the full file format specification.

## Tool Visibility

Tool visibility is one of the most important aspects of subagent design. It determines what a child agent can do, and more importantly, what it cannot.

### Inheriting All Parent Tools

By default (when `tools` is `null`), the child inherits every tool the parent has, including `spawn_subagent` itself:

```php
new AgentDefinition(
    name: 'assistant',
    description: 'General-purpose helper',
    systemPrompt: 'Help with any task.',
    // tools: null -- inherits all parent tools
);
```

### Allow-List

Setting `tools` to a `NameList` creates a strict allow-list. Only the named tools are available to the child:

```php
new AgentDefinition(
    name: 'reader',
    description: 'Read-only file analysis',
    systemPrompt: 'Analyze files but never modify them.',
    tools: new NameList('read_file', 'search_files'),
);
```

### Deny-List

The `toolsDeny` field removes specific tools from whatever set the child would otherwise have. This is useful when you want to inherit most tools but block a few dangerous ones:

```php
new AgentDefinition(
    name: 'safe_editor',
    description: 'Edit files without shell access',
    systemPrompt: 'Edit files safely. Never run shell commands.',
    tools: new NameList('read_file', 'write_file', 'edit_file'),
    toolsDeny: new NameList('write_file'),
);
// Result: child only has 'read_file' and 'edit_file'
```

### spawn_subagent in Children

If the child inherits `spawn_subagent`, the tool is automatically replaced with a nested version that tracks depth. This means children can spawn their own subagents, subject to the depth policy. If you want to prevent this, add `spawn_subagent` to the deny list:

```php
new AgentDefinition(
    name: 'leaf_worker',
    description: 'Performs a task without delegating',
    systemPrompt: 'Complete the task directly.',
    toolsDeny: new NameList('spawn_subagent'),
);
```

## Depth Control

Subagents can spawn their own subagents, creating a recursive hierarchy. The `SubagentPolicy` controls the maximum nesting depth to prevent unbounded recursion.

### Using SubagentPolicy

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
```

The default `maxDepth` is `3`. A depth of `0` means the parent itself; a depth of `2` means the parent can spawn children, and those children can spawn grandchildren, but no further.

### Convenience Factory

For simple depth configuration, use the static `forDepth()` factory:

```php
$agent = AgentBuilder::base()
    ->withCapability(UseSubagents::forDepth(2, provider: $registry))
    ->build();
```

### Depth Exceeded Behavior

When a subagent attempts to spawn at a depth that exceeds the policy, a `SubagentDepthExceededException` is thrown. This exception is returned to the calling agent as a tool error, allowing it to handle the situation gracefully (typically by performing the work itself).

## Child Budgets and Models

Each child agent can declare its own resource budget and LLM configuration independently from the parent.

### Custom Budget

```php
use Cognesy\Agents\Data\ExecutionBudget;

new AgentDefinition(
    name: 'quick_reviewer',
    description: 'Short, fast review with tight limits',
    systemPrompt: 'Be concise. Focus on critical issues only.',
    budget: new ExecutionBudget(
        maxSteps: 5,
        maxTokens: 2500,
        maxSeconds: 20.0,
    ),
);
```

If no budget is declared, the child runs without guards (unless the parent's guards indirectly limit it through total token accounting).

### Custom Model

Children can use a different model or provider than the parent. This is useful for cost optimization -- simple tasks can use a cheaper model while complex analysis uses a more capable one.

```php
use Cognesy\Polyglot\Inference\Config\LLMConfig;

new AgentDefinition(
    name: 'quick_classifier',
    description: 'Fast classification with a small model',
    systemPrompt: 'Classify the input into one of the provided categories.',
    llmConfig: LLMConfig::fromArray([
        'driver' => 'openai',
        'model' => 'gpt-4o-mini',
    ]),
    budget: new ExecutionBudget(maxSteps: 3),
);
```

If no `llmConfig` is specified, the child inherits the parent's LLM configuration. You can also pass just a driver name as a string:

```php
new AgentDefinition(
    name: 'anthropic_worker',
    description: 'Worker using Anthropic provider',
    systemPrompt: 'Complete the task.',
    llmConfig: 'anthropic',
);
```

## Skill Injection

Subagents can reference named skills from a `SkillLibrary`. When skills are specified in the definition, their rendered content is injected as additional system messages before the user prompt.

```php
use Cognesy\Agents\Capability\Skills\SkillLibrary;
use Cognesy\Agents\Capability\Subagent\UseSubagents;
use Cognesy\Agents\Collections\NameList;

$skillLibrary = new SkillLibrary();
// ... register skills ...

$registry->register(new AgentDefinition(
    name: 'code_reviewer',
    description: 'Reviews code using project-specific guidelines',
    systemPrompt: 'Review the code following the project guidelines.',
    skills: new NameList('code_style', 'security_checklist'),
));

$agent = AgentBuilder::base()
    ->withCapability(new UseSubagents(
        provider: $registry,
        skillLibrary: $skillLibrary,
    ))
    ->build();
```

## Events

The subagent lifecycle emits two events through the parent's event dispatcher, providing visibility into delegation activity.

### SubagentSpawning

Dispatched when a parent agent is about to spawn a child. Contains context for tracing the delegation hierarchy.

```php
use Cognesy\Agents\Events\SubagentSpawning;

$agent->onEvent(SubagentSpawning::class, function (SubagentSpawning $e) {
    echo "Spawning '{$e->subagentName}' at depth {$e->depth}/{$e->maxDepth}\n";
    echo "Parent agent: {$e->parentAgentId}\n";
    echo "Prompt: {$e->prompt}\n";
});
```

The event includes:
- `parentAgentId` -- the parent's agent ID
- `subagentName` -- the name of the subagent being spawned
- `prompt` -- the task/question sent to the child
- `depth` / `maxDepth` -- current and maximum nesting depth
- `parentExecutionId`, `parentStepNumber`, `toolCallId` -- correlation IDs for tracing

### SubagentCompleted

Dispatched when a child agent finishes execution, regardless of success or failure.

```php
use Cognesy\Agents\Events\SubagentCompleted;

$agent->onEvent(SubagentCompleted::class, function (SubagentCompleted $e) {
    $tokens = $e->usage?->total() ?? 0;
    echo "'{$e->subagentName}' completed: status={$e->status->value}, "
       . "steps={$e->steps}, tokens={$tokens}\n";
});
```

The event includes:
- `subagentName` -- the name of the completed subagent
- `subagentId` -- the child's unique agent ID
- `status` -- the `ExecutionStatus` (completed, failed, etc.)
- `steps` -- total steps the child took
- `usage` -- token usage data (nullable)
- `startedAt` / `completedAt` -- timestamps for duration calculation

## Error Handling

The subagent system defines three specific exception types:

| Exception | When |
|---|---|
| `SubagentNotFoundException` | The named subagent does not exist in the registry |
| `SubagentDepthExceededException` | The spawn would exceed the configured `maxDepth` |
| `SubagentExecutionException` | The child agent finished with `ExecutionStatus::Failed` |

All three are returned to the parent agent as tool errors, so the parent can decide how to proceed -- retry with different instructions, try a different subagent, or handle the task itself.

## The Tool Schema

The `spawn_subagent` tool automatically generates its schema from the registry. The schema includes:

- A `subagent` parameter as an enum of all available agent names
- A `prompt` parameter for the task or question
- A description that lists all available subagents with their descriptions and tool access

This means the LLM can see which subagents are available, what each one does, and what tools each has access to, all from the tool schema alone.

## Related

- [AgentBuilder & Capabilities](13-agent-builder.md)
- [Agent Templates](14-agent-templates.md)
- [Session Runtime](16-session-runtime.md)
