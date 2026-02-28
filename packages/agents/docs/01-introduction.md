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

## Two Layers

**AgentLoop** is the immutable execution engine. It holds tools, a driver, an interceptor, and an event handler — all set at construction time. It takes an `AgentState`, runs the step loop, and returns the final state. Use it directly for simple agents or full manual control.

**AgentBuilder** is the composition layer. It assembles an `AgentLoop` from pluggable capabilities (`Use*` classes) that install tools, hooks, guards, drivers, and compilers. Use it when you want modular, reusable configuration.

**Agent Templates** let you define agents as data (`AgentDefinition`) and instantiate loops/states from markdown, YAML, or JSON.

**Session Runtime** lets you persist agent sessions and "wake up" agents across processes by applying typed actions on stored session state.

```php
// Direct: AgentLoop
$loop = AgentLoop::default()->withTool($myTool);

// Composed: AgentBuilder
$agent = AgentBuilder::base()
    ->withCapability(new UseBash())
    ->withCapability(new UseGuards(maxSteps: 10))
    ->build();
```

## Package Structure

```
AgentLoop.php         # Core execution loop
CanControlAgentLoop   # Loop interface (execute/iterate)
Broadcasting/         # AgentEventBroadcaster, BroadcastConfig, CanBroadcastAgentEvents
Builder/              # AgentBuilder, AgentConfigurator, capability contracts
Capability/           # Use* capabilities (Core, Bash, File, Subagent, etc.)
Collections/          # Tools, AgentSteps, StepExecutions, ErrorList, etc.
Context/              # AgentContext, message compilers (CanCompileMessages)
Continuation/         # StopSignal, StopReason, ExecutionContinuation
Data/                 # AgentState, ExecutionState, AgentStep, ExecutionBudget
Drivers/              # ToolCallingDriver, ReActDriver, FakeAgentDriver
Enums/                # AgentStepType, ExecutionStatus
Events/               # Agent event system
Exceptions/           # Domain exceptions
Hook/                 # HookStack, HookInterface, HookContext, built-in hooks
Interception/         # CanInterceptAgentLifecycle, PassThroughInterceptor
Template/             # Agent definitions, parsers, registry
Session/              # AgentSession, SessionRuntime, actions, repositories, stores
Tool/                 # ToolInterface, BaseTool, FunctionTool, ToolExecutor

```

## Minimal Example

```php
use Cognesy\Agents\AgentLoop;use Cognesy\Agents\Data\AgentState;

$loop = AgentLoop::default();
$state = AgentState::empty()->withUserMessage('Hello!');
$result = $loop->execute($state);

echo $result->finalResponse()->toString();
```

## Recommended Path

1. Start with `AgentLoop` ([Basic Agent](02-basic-agent.md)).
2. Move to `AgentBuilder` when composition becomes non-trivial ([AgentBuilder & Capabilities](13-agent-builder.md)).
3. Move to templates when config should be data-driven ([Agent Templates](14-agent-templates.md)).
4. Move to sessions when runtime state must persist between calls/processes ([Session Runtime](16-session-runtime.md)).
