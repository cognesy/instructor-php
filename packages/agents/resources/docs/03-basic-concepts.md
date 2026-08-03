---
title: 'Basic Concepts'
description: 'Core components of the agent system: AgentLoop, AgentState, AgentContext, AgentStep, and ExecutionState'
---

# Basic Concepts

The Agents package is built around a small set of immutable value objects that
represent everything about an agent's lifecycle. Understanding these concepts
is essential before working with any other feature of the system.


## AgentLoop

The `AgentLoop` is the orchestrator at the heart of every agent. It drives a
step-based execution cycle: call the LLM, execute any requested tools, evaluate
stop conditions, and repeat until the agent is finished.

Each iteration of the loop follows a well-defined lifecycle:

```
BeforeExecution -> [ BeforeStep -> UseTools -> AfterStep -> ShouldStop? ] -> AfterExecution
```

The loop begins with a `BeforeExecution` phase where the execution state is
initialized. It then enters a repeating cycle of steps. During each step, the
loop fires a `BeforeStep` hook, hands control to the driver to call the LLM
and execute any tool calls, fires an `AfterStep` hook, and then evaluates
whether the agent should stop. If the model responds without requesting any
tool calls (a "final response"), or if a stop signal has been emitted by a
hook, the loop exits. An `AfterExecution` phase finalizes the state before
it is returned.

You can obtain a default loop with sensible defaults using the static
constructor:

```php
use Cognesy\Agents\AgentLoop;

$loop = AgentLoop::default();
```

This creates a loop wired with the default tool-calling driver, an event
dispatcher, and a pass-through interceptor. For more control, use the
`AgentBuilder` to compose a loop with specific capabilities, tools, and hooks.


## AgentState

`AgentState` is an immutable value object that carries everything about an
agent's session and its current execution. Every method that modifies state
returns a new instance, leaving the original untouched. This immutability
makes the data flow through the loop predictable and safe for inspection at
any point.

The state is divided into two conceptual layers:

**Session data** persists across executions. It represents the agent's
long-lived identity and accumulated conversation history:

| Property | Description |
|---|---|
| `agentId` | A unique identifier for this agent instance |
| `parentAgentId` | The ID of the parent agent, if this is a sub-agent |
| `context` | The `AgentContext` holding messages, system prompt, metadata, and response format |
| `llmConfig` | Optional LLM configuration overrides |
| `executionCount` | How many times this agent has been executed |
| `createdAt` / `updatedAt` | Session timing timestamps |

**Execution data** is transient. It exists only while the loop is running and
is `null` between executions. The `execution` property holds an
`ExecutionState` instance that tracks step results, the current step, timing,
continuation signals, and the execution status.

Creating an initial state is straightforward:

```php
use Cognesy\Agents\Data\AgentState;

$state = AgentState::empty()
    ->withSystemPrompt('You are a helpful assistant.')
    ->withUserMessage('What is the capital of France?');
```

Because every `with*` method returns a new instance, you can chain calls
fluently. The original `$state` is never modified.

`AgentState` is the runtime state for a single agent loop. If you need to
persist state across HTTP requests or manage multi-turn sessions, see the
[Session Runtime](16-session-runtime.md) documentation for `AgentSession`
and `SessionRuntime`.


## AgentContext

`AgentContext` is the container for all conversation-related data that the
agent sends to the LLM. It holds four pieces of information:

- **MessageStore** -- the sectioned storage for conversation messages. Messages
  are organized into named sections (the default section holds the main
  conversation). The driver's message compiler reads from this store to build
  the final prompt.
- **System prompt** -- the instruction text prepended to every LLM call.
- **Metadata** -- arbitrary key-value pairs that hooks and capabilities can use
  to pass information through the pipeline without modifying messages.
- **Response format** -- an optional structured output format that instructs the
  LLM to respond in a particular schema.

You interact with the context indirectly through `AgentState` methods:

```php
$state = AgentState::empty()
    ->withSystemPrompt('You are a research assistant.')
    ->withUserMessage('Summarize this article.')
    ->withMetadata('user_id', 42);
```

When the loop calls the LLM, the driver's message compiler (implementing
`CanCompileMessages`) transforms the current `AgentContext` into the final
`Messages` collection sent to the inference API. This compilation step is
where features like message filtering, trace exclusion, and context
summarization are applied.


## AgentStep

An `AgentStep` is an immutable snapshot of a single loop iteration. After
the driver calls the LLM and executes any tool calls, the results are
bundled into an `AgentStep` and attached to the state.

Each step captures:

| Property | Description |
|---|---|
| `id` | A unique `AgentStepId` for this step |
| `inputMessages` | The messages that were sent to the LLM |
| `outputMessages` | The assistant's response and any tool result messages |
| `inferenceResponse` | The raw LLM response, including token usage and finish reason |
| `toolExecutions` | A `ToolExecutions` collection with the results of each executed tool call |
| `errors` | An `ErrorList` aggregating any errors from tool execution or the step itself |

The step's type is derived automatically from its contents:

```php
use Cognesy\Agents\Enums\AgentStepType;

$step->stepType(); // AgentStepType::FinalResponse
                   // AgentStepType::ToolExecution
                   // AgentStepType::Error
```

A step is classified as `ToolExecution` when the LLM requested tool calls,
`Error` when any errors occurred during the step, and `FinalResponse` when
the model produced a plain text answer with no tool calls and no errors.

You can inspect a step's tool calls at two levels: `requestedToolCalls()`
returns the tool calls the model asked for, while `executedToolCalls()`
returns only those that were actually run (a tool call can be blocked by a
hook before execution).


## StepExecution

`StepExecution` wraps an `AgentStep` with timing and continuation metadata.
When a step completes, the loop bundles the step together with its start and
end timestamps and the continuation state at that point, then appends this
`StepExecution` to the completed steps list.

This separation keeps the `AgentStep` focused on what happened during the
step (messages, tool calls, errors), while `StepExecution` records when it
happened and whether a stop signal was active:

```php
$stepExecution = $state->lastStepExecution();

$stepExecution->step();          // The underlying AgentStep
$stepExecution->startedAt();     // DateTimeImmutable
$stepExecution->completedAt();   // DateTimeImmutable
$stepExecution->duration();      // float (seconds)
$stepExecution->continuation();  // ExecutionContinuation snapshot
$stepExecution->usage();         // Token usage for this step
```


## ExecutionState

`ExecutionState` tracks the transient state of a single execution run. It is
created fresh when the loop starts and is set to `null` on the `AgentState`
once the execution completes.

The execution state manages:

- **Status** -- one of `Pending`, `InProgress`, `Completed`, `Stopped`, or
  `Failed` (see `ExecutionStatus` enum).
- **Step history** -- a `StepExecutions` collection of all completed steps.
- **Current step** -- the in-progress `AgentStep` before it is finalized.
- **Continuation** -- an `ExecutionContinuation` object that collects stop
  signals and tracks whether a continuation has been requested.
- **Timing** -- execution start and completion timestamps.

You typically access execution data through convenience methods on
`AgentState` rather than working with `ExecutionState` directly:

```php
$state->status();           // ExecutionStatus::InProgress
$state->stepCount();        // 3
$state->usage();            // Accumulated token usage across all steps
$state->executionDuration();// Total wall-clock time in seconds
$state->shouldStop();       // Whether the loop should terminate
```

The `shouldStop()` logic follows a clear priority chain. If a stop signal
has been emitted (by a hook such as `StepsLimitHook` or
`FinishReasonHook`) and no continuation has been explicitly requested, the
execution stops. If no stop signal exists but the current step has tool
calls, the execution continues to process them. If neither condition
applies -- meaning the model produced a final response with no tool calls
-- the execution stops naturally.


## ToolExecution

A `ToolExecution` is an immutable record of a single tool call's outcome.
It captures the tool call that was requested, the result (success or
failure), and precise timing:

```php
$toolExec = $state->lastToolExecution();

$toolExec->name();        // 'search_web'
$toolExec->args();        // ['query' => 'PHP 8.4 features']
$toolExec->hasError();    // false
$toolExec->value();       // The successful return value
$toolExec->error();       // null (or a Throwable on failure)
$toolExec->wasBlocked();  // true if a hook blocked execution
$toolExec->startedAt();   // DateTimeImmutable
$toolExec->completedAt(); // DateTimeImmutable
```

Tool executions are collected within each `AgentStep` via its
`toolExecutions()` method. When a tool call is blocked by a pre-execution
hook, the `ToolExecution` is still recorded, but with a `Failure` result
containing a `ToolExecutionBlockedException`.
