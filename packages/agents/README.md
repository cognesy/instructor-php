# Instructor Agents

SDK for building composable AI agents in PHP.

This package provides:

- `AgentLoop` for step-based agent execution
- tools and tool execution runtime
- hooks/guards for lifecycle control
- `AgentBuilder` capabilities for composition
- templates and session runtime for persisted workflows

This package is a split from the [Instructor PHP monorepo](https://github.com/cognesy/instructor-php).

## Installation

```bash
composer require cognesy/agents
```

## Minimal Example

```php
use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Data\AgentState;

$agent = AgentLoop::default();
$state = AgentState::empty()->withUserMessage('What is 2+2?');
$result = $agent->execute($state);

echo $result->finalResponse()->toString();
```

## Coding Agent

`UseCodingTools` installs the provider-familiar `read`, `bash`, `edit`, and
`write` tool names. `CodingAgentPrompt` renders matching operating guidance
from the prompt shipped in `resources/prompts/coding/coding-agent.md.twig`.

```php
use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Capability\Coding\UseCodingTools;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Prompts\Coding\CodingAgentPrompt;

$workDir = '/path/to/project';
$agent = AgentBuilder::base()
    ->withCapability(new UseCodingTools($workDir))
    ->build();

$state = AgentState::empty()
    ->withSystemPrompt(CodingAgentPrompt::with(
        documentation_path: $workDir . '/AGENTS.md',
    ))
    ->withUserMessage('Inspect the project and make the requested change.');

$result = $agent->execute($state);
```

`documentation_path` is optional. When it is omitted, the rendered prompt does
not invent a package-relative documentation location.

The file contracts are deliberately bounded and conservative:

- `read` defaults to a numbered 200-line/32 KiB window and returns an exact
  continuation hint. Agents can explicitly increase `limit` and `max_bytes`
  when a larger window or complete file is justified.
- `edit` streams through the source and atomically commits only after exact
  match validation succeeds.
- `write` creates a new file atomically, requires an existing parent directory,
  and refuses to replace different content.
- `bash` returns at most a 32 KiB window and explains how to recover from
  larger output.

Legacy `UseFileTools` remains available with the existing `read_file`,
`edit_file`, and `write_file` names.

## Documentation

Use the Agents docs for actual usage and architecture details:

- `packages/agents/docs/01-introduction.md`
- `packages/agents/docs/02-basic-agent.md`
- `packages/agents/docs/05-tools.md`
- `packages/agents/docs/13-agent-builder.md`
- `packages/agents/docs/14-agent-templates.md`
- `packages/agents/docs/16-session-runtime.md`
