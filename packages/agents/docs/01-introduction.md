---
title: 'Introduction'
description: 'Overview of the Agents SDK for building LLM-powered agents that reason and act through tool use'
---

# Introduction

The Agents package provides a minimal, composable foundation for building LLM-powered agents that reason and act through tool use.

At its core, an agent is a **loop**: send messages to an LLM, receive a response (possibly with tool calls), execute those tools, feed results back, and repeat until done.

## Key Design Principles

- **Immutable state** - `AgentState` is a readonly value object. Every mutation returns a new instance.
- **Pluggable drivers** - Swap between `ToolCallingDriver` (native function calling) and `ReActDriver` (Thought/Action/Observation) without changing your agent code.
- **Lifecycle hooks** - Intercept any phase of execution to add guards, logging, or custom behavior.
- **Testable by default** - `FakeAgentDriver` lets you script deterministic scenarios without LLM calls.

## Package Structure

```
Core/           # AgentLoop, AgentState, tools, stop signals
Context/        # AgentContext, message compilers
Hooks/          # Lifecycle interceptors and guard hooks
Drivers/        # ToolCallingDriver, ReActDriver, FakeAgentDriver
Events/         # Agent event system
Exceptions/     # Domain exceptions
```

## Minimal Example

```php
use Cognesy\Agents\Core\AgentLoop;
use Cognesy\Agents\Core\Data\AgentState;

$loop = AgentLoop::default();
$state = AgentState::empty()->withUserMessage('Hello!');
$result = $loop->execute($state);

echo $result->finalResponse()->toString();
```
