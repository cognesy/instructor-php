---
title: 'Tool Calling Internals'
description: 'How ToolCallingDriver, ReActDriver, and ToolExecutor implement tool use through different strategies'
---

# Tool Calling Internals

> Most users can skip this page.
> For day-to-day usage, start with [Basic Agent](02-basic-agent.md), [Tools](05-tools.md), and [AgentBuilder & Capabilities](13-agent-builder.md).

The agent's ability to use tools is built on a clean separation of concerns: a **driver** decides which tools to call (by consulting the LLM), and an **executor** runs the actual tools. Two contracts define this boundary, and three driver implementations satisfy the first contract in different ways.

## Architecture Overview

```
AgentLoop
  |-- CanUseTools (driver)              # decides what tools to call
  |   |-- ToolCallingDriver             # native LLM function calling
  |   |-- ReActDriver                   # Thought/Action/Observation via structured output
  |   |-- FakeAgentDriver               # scripted responses for testing
  |
  |-- CanExecuteToolCalls (executor)    # runs the actual tools
      |-- ToolExecutor                  # default implementation
```

The `AgentLoop` owns both the driver and the executor. Before the first step, it binds the tool runtime to the driver via `CanAcceptToolRuntime::withToolRuntime()`, ensuring the driver has access to the same `Tools` collection and `ToolExecutor` that the loop manages. This binding happens once per `execute()` / `iterate()` call.

## The Two Contracts

### CanUseTools (Driver Contract)

The driver receives the current `AgentState`, consults the LLM (or a scripted scenario), and returns an updated state with a new `AgentStep` attached. The step may contain tool calls, a final response, or an error:

```php
interface CanUseTools
{
    public function useTools(AgentState $state): AgentState;
}
```

The driver is responsible for:
- Compiling messages from state via `CanCompileMessages`
- Sending the messages to the LLM with tool schemas
- Parsing the LLM response for tool calls
- Delegating tool execution to the `ToolExecutor`
- Formatting execution results as follow-up messages
- Building and attaching the `AgentStep` to the returned state

### CanExecuteToolCalls (Executor Contract)

The executor receives a set of `ToolCalls` and the current `AgentState`, runs each tool, and returns the results:

```php
interface CanExecuteToolCalls
{
    public function executeTools(ToolCalls $toolCalls, AgentState $state): ToolExecutions;
}
```

The executor is responsible for:
- Resolving tool instances from the `Tools` collection
- Injecting context (agent state, tool call metadata) into tools that request it
- Validating arguments against the tool schema
- Running the tool and capturing the result
- Handling errors, interception hooks, and events

## ToolCallingDriver

`ToolCallingDriver` uses the LLM's **native function calling API**. This is the default driver created by `AgentLoop::default()` and is the recommended choice for models that support function calling (GPT-4o, Claude, Gemini, etc.).

### How It Works

Each invocation of `useTools()` follows this sequence:

1. **Compile messages.** The message compiler (default: `ConversationWithCurrentToolTrace`) produces a `Messages` collection from the agent state. This compiler includes the full conversation history plus trace messages from the current execution only.

2. **Build the inference request.** The driver assembles an `InferenceRequest` with the compiled messages, tool schemas from the `Tools` collection, the model name, tool choice strategy, and any cached context.

3. **Send to the LLM.** The request is dispatched through the `InferenceRuntime`, which handles provider-specific API formatting, retries, and streaming.

4. **Parse tool calls.** The `InferenceResponse` is inspected for `toolCalls`. If present, they are forwarded to the `ToolExecutor`.

5. **Execute tools.** The `ToolExecutor` runs each tool call and returns `ToolExecutions`.

6. **Format results.** The `ToolExecutionFormatter` converts each `ToolExecution` into a pair of messages: an assistant message with `tool_calls` metadata, and a `tool` role message with the execution result (or error).

7. **Build the step.** An `AgentStep` is created with the input messages, output messages, inference response, and tool executions, then attached to the state via `withCurrentStep()`.

### Configuration

```php
use Cognesy\Polyglot\Inference\InferenceRuntime;
use Cognesy\Polyglot\Inference\LLMProvider;
use Cognesy\Agents\Drivers\ToolCalling\ToolCallingDriver;
use Cognesy\Events\Dispatchers\EventDispatcher;

$llm = LLMProvider::new();
$events = new EventDispatcher('agent');
$inference = InferenceRuntime::fromProvider($llm, events: $events);

$driver = new ToolCallingDriver(
    inference: $inference,
    llm: $llm,
    model: 'gpt-4o',
    toolChoice: 'auto',           // 'auto', 'required', or a specific tool name
    responseFormat: [],            // optional response format constraints
    options: [],                   // additional provider-specific options
    events: $events,
);
```

### Tool Choice Strategies

The `toolChoice` parameter controls how the LLM selects tools:

| Value | Behavior |
|---|---|
| `'auto'` | The LLM decides whether to call a tool or respond directly (default) |
| `'required'` | The LLM must call at least one tool |
| `'none'` | Tool calling is disabled; the LLM responds with text only |
| `'toolName'` | The LLM must call the specified tool |

### Tool Args Leak Protection

Some LLM providers accidentally echo tool call arguments as the response content. The `ToolCallingDriver` detects this by parsing the content as JSON and comparing it against the tool call arguments. If they match, the content is silently discarded to prevent duplicate data in the conversation.

## ReActDriver

`ReActDriver` implements the **ReAct (Reasoning + Acting)** pattern using structured output extraction. Instead of relying on native function calling, it prompts the LLM to output a JSON decision with explicit `thought`, `type`, `tool`, `args`, and `answer` fields.

### How It Works

1. **Build system prompt.** The `MakeReActPrompt` action generates a system prompt that describes the available tools and the expected ReAct JSON format.

2. **Extract decision.** The `StructuredOutputRuntime` extracts a `ReActDecision` object from the LLM response. This uses the configured `OutputMode` (typically JSON) and includes retry logic for extraction failures.

3. **Validate decision.** The `ReActValidator` checks that the decision has a valid type, references an existing tool, and includes valid arguments.

4. **Route by type.**
   - If the decision type is `call_tool`: convert it to `ToolCalls`, execute via the `ToolExecutor`, and format the results as Thought/Action/Observation messages.
   - If the decision type is `final_answer`: extract the answer text and build a final response step.

5. **Optional final inference.** When `finalViaInference` is `true`, the driver makes a separate LLM call to produce the final answer, using the full conversation as context. This can improve answer quality at the cost of an extra API call.

### Configuration

```php
use Cognesy\Polyglot\Inference\InferenceRuntime;
use Cognesy\Polyglot\Inference\LLMProvider;
use Cognesy\Agents\Drivers\ReAct\ReActDriver;
use Cognesy\Instructor\StructuredOutputRuntime;
use Cognesy\Instructor\Creation\StructuredOutputConfigBuilder;
use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Events\Dispatchers\EventDispatcher;

$llm = LLMProvider::new();
$events = new EventDispatcher('agent');
$inference = InferenceRuntime::fromProvider($llm, events: $events);
$structuredOutput = new StructuredOutputRuntime(
    inference: $inference,
    events: $events,
    config: (new StructuredOutputConfigBuilder())
        ->withOutputMode(OutputMode::Json)
        ->withMaxRetries(2)
        ->create(),
);

$driver = new ReActDriver(
    inference: $inference,
    structuredOutput: $structuredOutput,
    llm: $llm,
    model: 'gpt-4o',
    mode: OutputMode::Json,
    maxRetries: 2,                // retries on decision extraction failure
    finalViaInference: false,     // use a separate LLM call for the final answer
    finalModel: null,             // optional different model for final answer
    finalOptions: [],             // optional different options for final answer
);
```

### Error Handling

The `ReActDriver` handles two categories of extraction failures:

- **Extraction failure.** If the `StructuredOutputRuntime` cannot parse the LLM output into a `ReActDecision`, the driver builds a failure step with a `decision_extraction` pseudo-tool execution and marks the state as failed.

- **Validation failure.** If the decision is extracted but fails validation (invalid type, unknown tool, missing arguments), the driver builds a failure step with a `decision_validation` pseudo-tool execution and marks the state as failed.

Both failure types emit dedicated events (`DecisionExtractionFailed`, `ValidationFailed`) for observability.

## ToolExecutor

`ToolExecutor` is the default `CanExecuteToolCalls` implementation. It is created automatically by `AgentLoop::default()` and handles the complete lifecycle of executing a tool call, including interception hooks, event emission, and error handling.

### Execution Pipeline

For each tool call in the `ToolCalls` collection, the executor runs this pipeline:

```
1. beforeToolUse intercept
   |-- Interceptor can modify the tool call
   |-- Interceptor can modify the agent state
   |-- Interceptor can block execution (returns ToolExecution::blocked())
   |
2. Emit ToolCallStarted event
   |
3. Prepare tool
   |-- Resolve tool instance from Tools collection
   |-- Inject AgentState if tool implements CanAccessAgentState
   |-- Inject ToolCall if tool implements CanAccessToolCall
   |
4. Validate arguments
   |-- Check required parameters from the tool schema
   |-- Return Failure result if parameters are missing
   |
5. Execute
   |-- Call $tool->use(...$args)
   |-- Wrap exceptions in ToolExecutionException
   |-- AgentStopException is re-thrown (not caught)
   |
6. Emit ToolCallCompleted event
   |
7. afterToolUse intercept
   |-- Interceptor can modify the execution result
   |-- Interceptor can modify the agent state
```

### Tool Context Injection

Tools can opt into receiving execution context by implementing one or both of these interfaces:

**`CanAccessAgentState`** -- The tool receives a read-only copy of the current `AgentState` before invocation. This is useful for tools that need to inspect the conversation history, metadata, or execution status:

```php
use Cognesy\Agents\Tool\Contracts\CanAccessAgentState;
use Cognesy\Agents\Data\AgentState;

class ContextAwareTool implements ToolInterface, CanAccessAgentState
{
    private ?AgentState $state = null;

    public function withAgentState(AgentState $state): static
    {
        $clone = clone $this;
        $clone->state = $state;
        return $clone;
    }

    public function use(mixed ...$args): Result
    {
        // Access conversation history, metadata, etc.
        $history = $this->state->messages();
        // ...
    }
}
```

**`CanAccessToolCall`** -- The tool receives the `ToolCall` object that triggered it. Useful for correlation and tracing, especially in subagent tools that emit their own events:

```php
use Cognesy\Agents\Tool\Contracts\CanAccessToolCall;
use Cognesy\Messages\ToolCall;

class TracedTool implements ToolInterface, CanAccessToolCall
{
    private ?ToolCall $toolCall = null;

    public function withToolCall(ToolCall $toolCall): static
    {
        $clone = clone $this;
        $clone->toolCall = $toolCall;
        return $clone;
    }
}
```

### Configuration

```php
use Cognesy\Agents\Tool\ToolExecutor;
use Cognesy\Agents\Collections\Tools;
use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Agents\Interception\PassThroughInterceptor;

$executor = new ToolExecutor(
    tools: $tools,
    events: new EventDispatcher('agent'),
    interceptor: new PassThroughInterceptor(),
    throwOnToolFailure: false,   // true = throw on the first tool error
    stopOnToolBlock: false,      // true = stop executing remaining tools if one is blocked
);

$loop = AgentLoop::default()
    ->withTools($tools)
    ->withToolExecutor($executor);
```

### Error Handling Modes

The `throwOnToolFailure` and `stopOnToolBlock` flags control how the executor responds to problems:

| Flag | Default | When `true` |
|---|---|---|
| `throwOnToolFailure` | `false` | Throws a `ToolExecutionException` immediately when a tool returns a `Failure` result. The exception propagates to the `AgentLoop`, which catches it and marks the step as failed. |
| `stopOnToolBlock` | `false` | When a `beforeToolUse` interceptor blocks a tool call, the executor stops processing remaining tool calls in the batch and returns what it has so far. |

When both flags are `false` (the default), the executor collects all results -- successes, failures, and blocked executions -- and returns them as a `ToolExecutions` collection. The driver then formats them as messages and includes them in the step output, allowing the LLM to see and react to the errors on the next iteration.

## ToolExecution Result

Each tool execution produces a `ToolExecution` value object containing:

```php
final readonly class ToolExecution
{
    private ToolExecutionId $id;         // Unique execution identifier
    private ToolCall $toolCall;           // The tool call that was executed
    private Result $result;               // Success(value) or Failure(exception)
    private DateTimeImmutable $startedAt;
    private DateTimeImmutable $completedAt;
}
```

You can inspect the result using:

```php
$execution->name();           // Tool name
$execution->args();           // Arguments passed to the tool
$execution->result();         // Result object (Success or Failure)
$execution->value();          // Unwrapped value (null if failed)
$execution->hasError();       // bool
$execution->errorMessage();   // string
$execution->wasBlocked();     // bool -- true if blocked by interceptor
```

## Message Formatting

After tool execution, the results must be formatted as messages that the LLM can understand on the next iteration. Each driver handles this differently:

### ToolCallingDriver: Native Format

The `ToolExecutionFormatter` produces two messages per tool execution:

1. **Assistant message** with `tool_calls` metadata -- represents the LLM's decision to call the tool.
2. **Tool message** with the execution result -- either the successful return value or an error description.

Both messages carry a `tool_execution_id` metadata tag for correlation.

### ReActDriver: Observation Format

The `ReActFormatter` produces messages in the Thought/Action/Observation pattern:

1. **Assistant message** containing the thought and action text from the `ReActDecision`.
2. **User message** (observation) containing the tool execution result, formatted as `Observation: <result>`.

## Events

Both drivers and the executor emit events at key lifecycle points. These can be observed via `AgentLoop::wiretap()` or `AgentLoop::onEvent()`:

| Event | Emitted By | When |
|---|---|---|
| `InferenceRequestStarted` | Driver | Before sending the request to the LLM |
| `InferenceResponseReceived` | Driver | After receiving the LLM response |
| `ToolCallStarted` | ToolExecutor | Before executing a tool |
| `ToolCallCompleted` | ToolExecutor | After a tool execution completes |
| `DecisionExtractionFailed` | ReActDriver | When structured output extraction fails |
| `ValidationFailed` | ReActDriver | When a ReAct decision fails validation |

## When to Use Which Driver

| | ToolCallingDriver | ReActDriver |
|---|---|---|
| **Requires** | LLM with native function calling support | Any LLM capable of JSON output |
| **Tool selection** | Native API -- reliable, low latency | Structured output extraction -- extra parsing step |
| **Reasoning** | Implicit in the LLM's response | Explicit `thought` field in the decision |
| **Reliability** | Higher (native API contract) | Lower (depends on extraction quality) |
| **Flexibility** | Standard tool schemas only | Custom decision schemas possible |
| **Retry support** | Handled by provider retry policy | Built-in `maxRetries` for extraction failures |
| **Best for** | Production agents with capable models | Models without function calling, or when explicit reasoning traces are needed |

## Custom Drivers

You can implement `CanUseTools` to create a custom driver. If your driver uses tools, also implement `CanAcceptToolRuntime` so the `AgentLoop` can inject the tool collection and executor:

```php
use Cognesy\Agents\Drivers\CanUseTools;
use Cognesy\Agents\Drivers\CanAcceptToolRuntime;
use Cognesy\Agents\Collections\Tools;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Tool\Contracts\CanExecuteToolCalls;

class MyCustomDriver implements CanUseTools, CanAcceptToolRuntime
{
    private Tools $tools;
    private CanExecuteToolCalls $executor;

    public function withToolRuntime(Tools $tools, CanExecuteToolCalls $executor): static
    {
        $clone = clone $this;
        $clone->tools = $tools;
        $clone->executor = $executor;
        return $clone;
    }

    public function useTools(AgentState $state): AgentState
    {
        // Your custom tool-calling logic here
        // Must return $state->withCurrentStep($step)
    }
}
```

The `AgentLoop` will call `withToolRuntime()` before the first step, passing the same `Tools` and `ToolExecutor` it manages internally.
