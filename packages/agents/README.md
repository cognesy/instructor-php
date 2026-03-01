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

## Documentation

Use the Agents docs for actual usage and architecture details:

- `packages/agents/docs/01-introduction.md`
- `packages/agents/docs/02-basic-agent.md`
- `packages/agents/docs/13-agent-builder.md`
- `packages/agents/docs/14-agent-templates.md`
- `packages/agents/docs/16-session-runtime.md`
