---
title: 'Basic Concepts'
description: 'Core components of the agent system: AgentLoop, AgentState, drivers, tools, and hooks'
---

# Basic Concepts

## AgentLoop

The orchestrator. Runs a step-based loop: call LLM, execute tools, check stop conditions, repeat.

```
BeforeExecution -> [ BeforeStep -> UseTools -> AfterStep -> ShouldStop? ] -> AfterExecution
```

## AgentState

Immutable value object holding everything about an agent's session and execution.

**Session data** (persists across executions):
- `agentId` - unique identifier
- `context` - messages, system prompt, metadata, response format
- `budget` - resource limits (steps, tokens, time)

**Execution data** (transient, null between executions):
- `execution` - current `ExecutionState` with steps, status, continuation signals

`AgentState` is runtime state for a single agent loop. Persisted multi-request session concerns live in `AgentSession` / `SessionRuntime` (see [Session Runtime](16-session-runtime.md)).

```php
$state = AgentState::empty()
    ->withSystemPrompt('You are helpful.')
    ->withUserMessage('Hello');
```

## AgentContext

Holds the conversation data: messages, system prompt, metadata, and response format. The driver's `CanCompileMessages` compiler transforms the agent state into the final `Messages` sent to the LLM.

```php
$state = AgentState::empty()
    ->withSystemPrompt('You are helpful.')
    ->withMetadata('user_id', 123);
```

## AgentStep

An immutable snapshot of a single loop iteration:

- `inputMessages` - what was sent to the LLM
- `outputMessages` - tool results + assistant response
- `inferenceResponse` - raw LLM response
- `toolExecutions` - results of executed tools
- `stepType()` - `FinalResponse`, `ToolExecution`, or `Error`

## StepExecution

Wraps an `AgentStep` with timing and continuation data. Stored in the completed steps list after each iteration.

## ExecutionState

Tracks the current execution's transient state: status, completed steps, current step, and continuation signals.

```php
$state->execution()->status();        // ExecutionStatus::InProgress
$state->execution()->stepCount();     // 3
$state->execution()->shouldStop();    // false
```
