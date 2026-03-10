---
title: 'Introduction'
description: 'Overview of the Agents SDK for building LLM-powered agents that reason and act through tool use'
---

# Introduction

The Agents package provides a minimal, composable foundation for building LLM-powered agents in PHP. An agent, at its core, is a **loop**: send messages to a language model, receive a response that may include tool calls, execute those tools, feed the results back into the conversation, and repeat until the model produces a final answer. This simple pattern is powerful enough to drive everything from single-turn question answering to multi-step autonomous workflows.

The package is designed to stay out of your way. There are no heavyweight frameworks to learn, no mandatory dependency injection containers, and no magic. You construct an execution loop, hand it some state, and get back a result. Everything in between -- tool execution, lifecycle hooks, stop conditions -- is explicit and composable.

## Key Design Principles

### Immutable State

`AgentState` is a readonly value object. Every operation that modifies state -- adding a message, recording a step, signaling a stop -- returns a new instance, leaving the original untouched. This makes agent execution predictable and easy to reason about: you can always inspect any prior state without worrying that a later step has mutated it. It also makes agents inherently safe for persistence and resumption, since state can be serialized at any point without race conditions.

### Pluggable Drivers

The execution engine does not know how to talk to an LLM. That responsibility belongs to a **driver**. The default `ToolCallingDriver` uses native function-calling APIs (OpenAI, Anthropic, etc.), while the `ReActDriver` implements the Thought/Action/Observation reasoning pattern using structured output. You can swap drivers without changing any of your agent code, tools, or hooks -- the `AgentLoop` treats them identically through the `CanUseTools` interface.

### Lifecycle Hooks

Every phase of execution fires a lifecycle hook: before/after execution, before/after each step, before/after each tool call, on stop, and on error. Hooks can inspect and transform the agent state, inject messages, enforce resource limits, or block tool calls entirely. The built-in guard hooks (`StepsLimitHook`, `TokenUsageLimitHook`, `ExecutionTimeLimitHook`) are implemented this way, and you can add your own using the same mechanism.

### Testable by Default

`FakeAgentDriver` lets you script deterministic scenarios without making any LLM calls. You define a sequence of `ScenarioStep` objects -- each specifying a response, optional tool calls, and a step type -- and the driver replays them in order. This means your agent tests are fast, deterministic, and free of API dependencies.

## Architecture Overview

The package is organized into two layers, with two additional systems built on top:

### AgentLoop -- The Execution Engine

`AgentLoop` is the immutable execution engine at the heart of the package. It holds a set of tools, a driver, an interceptor (hook stack), and an event handler -- all set at construction time. You pass it an `AgentState`, and it runs the step loop until a stop condition is met, returning the final state.

The loop's execution cycle works as follows:

1. **Before execution** -- fire lifecycle hooks, ensure a fresh execution context
2. **Before step** -- fire hooks (guards check limits here)
3. **Use tools** -- the driver sends messages to the LLM, receives a response, and executes any requested tool calls
4. **After step** -- fire hooks (state inspection, summarization, etc.)
5. **Evaluate continuation** -- check whether the loop should stop (no tool calls, stop signal received, or continuation explicitly requested)
6. **Repeat** from step 2, or stop and fire after-execution hooks

Use `AgentLoop` directly when you want full control or when your agent is simple enough that manual construction is clearer than composition.

### AgentBuilder -- The Composition Layer

`AgentBuilder` assembles an `AgentLoop` from pluggable **capabilities** -- small, reusable classes that install tools, hooks, guards, drivers, and compilers. Each capability implements `CanProvideAgentCapability` and knows how to configure itself onto an agent. This keeps complex agent setups modular: you can mix and match capabilities like `UseBash`, `UseGuards`, `UseFileTools`, `UseSummarization`, and more, without any of them knowing about each other.

Use `AgentBuilder` when your agent needs multiple capabilities and you want to keep the configuration declarative and composable.

### Agent Templates

When agent configuration should be data-driven rather than code-driven, **Agent Templates** let you define agents as `AgentDefinition` objects and instantiate loops and states from Markdown, YAML, or JSON files. This is useful when non-developers need to configure agents, or when you want to store agent definitions alongside prompts and tool configurations in version-controlled files.

### Session Runtime

When agent state must persist across HTTP requests, CLI invocations, or background processes, the **Session Runtime** provides a persistence layer. It stores `AgentSession` objects in pluggable stores (file-based or in-memory), and lets you apply typed actions (`SendMessage`, `ChangeModel`, `ForkSession`, etc.) to stored sessions. This is the foundation for building chat interfaces, long-running background agents, and multi-turn workflows.

## Quick Comparison

```php
use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Capability\Bash\UseBash;
use Cognesy\Agents\Capability\Core\UseGuards;

// Direct: construct and execute in three lines
$loop = AgentLoop::default()->withTool($myTool);
$state = AgentState::empty()->withUserMessage('Hello!');
$result = $loop->execute($state);

// Composed: declarative capability stacking
$loop = AgentBuilder::base()
    ->withCapability(new UseBash())
    ->withCapability(new UseGuards(maxSteps: 10))
    ->build();
```

## Package Structure

```
AgentLoop.php             # Core execution loop
CanControlAgentLoop       # Loop interface (execute / iterate)

Builder/                  # AgentBuilder, AgentConfigurator, capability contracts
Capability/               # Use* capabilities (Core, Bash, File, Subagent, etc.)
Collections/              # Tools, AgentSteps, StepExecutions, ToolExecutions

Context/                  # AgentContext, message compilers (CanCompileMessages)
Continuation/             # StopSignal, StopReason, ExecutionContinuation
Data/                     # AgentState, ExecutionState, AgentStep, ExecutionBudget

Drivers/                  # ToolCallingDriver, ReActDriver, FakeAgentDriver
Enums/                    # AgentStepType, ExecutionStatus
Events/                   # Agent event system (started, completed, failed, etc.)
Exceptions/               # Domain exceptions (tool blocked, invalid args, etc.)

Hook/                     # HookStack, HookInterface, HookContext, built-in hooks
Interception/             # CanInterceptAgentLifecycle, PassThroughInterceptor
Tool/                     # ToolInterface, BaseTool, FunctionTool, ToolExecutor

Template/                 # Agent definitions, parsers (MD/YAML/JSON), registry
Session/                  # AgentSession, SessionRuntime, actions, stores

Broadcasting/             # AgentEventBroadcaster for SSE/WebSocket streaming
```

## Minimal Example

```php
use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Data\AgentState;

$loop = AgentLoop::default();
$state = AgentState::empty()->withUserMessage('Hello!');
$result = $loop->execute($state);

echo $result->finalResponse()->toString();
```

This sends a single message to the default LLM provider, receives a text response (no tool calls), and prints it. The loop detects there are no tool calls to execute, so it stops after one step. The entire execution is captured in the returned `AgentState`, which you can inspect for usage statistics, step history, errors, and timing information.

## Recommended Learning Path

1. **[Basic Agent](02-basic-agent.md)** -- Build your first agent with `AgentLoop`, add tools, customize the driver, and understand the execution lifecycle.
2. **[AgentBuilder & Capabilities](13-agent-builder.md)** -- Compose agents from reusable capability modules when configuration becomes non-trivial.
3. **[Agent Templates](14-agent-templates.md)** -- Define agents as data when configuration should come from files rather than code.
4. **[Session Runtime](16-session-runtime.md)** -- Persist and resume agent sessions across processes for chat interfaces and long-running workflows.
