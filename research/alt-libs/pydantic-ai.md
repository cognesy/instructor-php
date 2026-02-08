# Pydantic AI - Architecture Analysis

## Overview

Pydantic AI is a provider-agnostic GenAI agent framework for Python built by the Pydantic team. The repo is organized as a `uv` workspace with multiple packages:

- **`pydantic-ai-slim`** (`pydantic_ai_slim/`) - Core agent framework
- **`pydantic-graph`** (`pydantic_graph/`) - Type-hint-based graph library that powers the agent loop
- **`pydantic-evals`** (`pydantic_evals/`) - Evaluation framework
- **`clai`** (`clai/`) - CLI/web UI for chatting with agents

Key design principles: lightweight, provider-agnostic, strongly-typed, Pydantic-native validation throughout.

---

## 1. Agent Loop

**Files:** `_agent_graph.py`, `agent/__init__.py`, `run.py`

The agent loop is implemented as a **graph execution** powered by `pydantic_graph`. The graph has 4 node types:

```
UserPromptNode → ModelRequestNode → CallToolsNode → End | ModelRequestNode (loop)
                                                   ↘ SetFinalResult → End
```

### Node Types

| Node | Role | File/Line |
|------|------|-----------|
| `UserPromptNode` | Processes user prompt, system prompts, instructions, deferred tool results | `_agent_graph.py:180` |
| `ModelRequestNode` | Sends request to LLM model, receives response | `_agent_graph.py:437` |
| `CallToolsNode` | Processes model response: handles tool calls, text output, validates results | `_agent_graph.py:570` |
| `SetFinalResult` | Terminal node used after streaming to set the final result | `_agent_graph.py:831` |

### Graph Construction

`build_agent_graph()` (`_agent_graph.py:1338`) uses `GraphBuilder` from `pydantic_graph.beta` to wire these nodes:

```python
g = GraphBuilder(name=name, state_type=GraphAgentState, deps_type=GraphAgentDeps, ...)
g.add(
    g.edge_from(g.start_node).to(UserPromptNode),
    g.node(UserPromptNode),
    g.node(ModelRequestNode),
    g.node(CallToolsNode),
    g.node(SetFinalResult),
)
return g.build(validate_graph_structure=False)
```

### Loop Termination

The loop terminates when `CallToolsNode` returns `End(FinalResult(...))`. This happens when:
1. Text output is validated successfully
2. An output tool call returns valid structured data
3. An image output is received (if output type allows)
4. Deferred tool requests are collected (if `DeferredToolRequests` is in output types)

The loop continues (returns `ModelRequestNode`) when:
- Tool calls were processed but no final result found
- Validation failed and retry is allowed
- Empty response received (retry)

### Loop Guards

- **`max_result_retries`** — max validation/output retries before `UnexpectedModelBehavior` is raised
- **`UsageLimits`** — token/request/tool-call limits checked before each request and after token counting
- **`run_step`** counter — incremented on each `ModelRequestNode._prepare_request()` call

---

## 2. Agent State Data Model

### `GraphAgentState` (`_agent_graph.py:86`)

Mutable state that persists across graph nodes within a single run:

```python
@dataclass(kw_only=True)
class GraphAgentState:
    message_history: list[ModelMessage]   # Full conversation history
    usage: RunUsage                        # Token/request counters
    retries: int = 0                       # Current retry count (for output validation)
    run_step: int = 0                      # Current step number
    run_id: str                            # UUID for the run
    metadata: dict[str, Any] | None        # User-defined metadata
```

### `GraphAgentDeps` (`_agent_graph.py:127`)

Immutable configuration/dependencies for a single graph run:

```python
@dataclass(kw_only=True)
class GraphAgentDeps(Generic[DepsT, OutputDataT]):
    user_deps: DepsT                       # User-injected dependencies
    prompt: str | Sequence[UserContent] | None
    new_message_index: int                 # Index separating old/new messages
    model: Model                           # The LLM model to use
    model_settings: ModelSettings | None
    usage_limits: UsageLimits
    max_result_retries: int
    end_strategy: EndStrategy              # 'early' | 'exhaustive'
    get_instructions: Callable             # Async function returning instructions
    output_schema: OutputSchema[OutputDataT]
    output_validators: list[OutputValidator]
    validation_context: Any
    history_processors: Sequence[HistoryProcessor]
    builtin_tools: list[AbstractBuiltinTool | BuiltinToolFunc]
    tool_manager: ToolManager[DepsT]
    tracer: Tracer                         # OpenTelemetry tracer
    instrumentation_settings: InstrumentationSettings | None
```

### `Agent` Class (`agent/__init__.py:104`)

The `Agent` class is a `@dataclass(init=False)` generic in `AgentDepsT` and `OutputDataT`. It stores:
- Model reference (`_model`)
- Output schema and validators
- System prompts (static and dynamic functions)
- Tool configuration (`_function_toolset`, `_user_toolsets`, `_output_toolset`)
- Settings overrides via `ContextVar` (for `agent.override()`)
- Concurrency limiter
- Exit stack for toolset lifecycle management

The Agent is **stateless between runs** — all run state lives in `GraphAgentState` created fresh for each `run()`/`iter()` call.

---

## 3. Conversation Data Model

**File:** `messages.py`

### Message Types

Two top-level message types form the conversation alternation:

| Type | Kind | Description |
|------|------|-------------|
| `ModelRequest` | `'request'` | Messages sent TO the model (user prompts, system prompts, tool returns, retry prompts) |
| `ModelResponse` | `'response'` | Messages FROM the model (text, tool calls, thinking, files) |

```python
ModelMessage = ModelRequest | ModelResponse  # Union type
ModelMessagesTypeAdapter = TypeAdapter(list[ModelMessage])  # For serialization
```

### Request Parts (`ModelRequestPart`)

| Part | Discriminator | Purpose |
|------|---------------|---------|
| `SystemPromptPart` | `'system-prompt'` | System instructions (static or dynamic with `dynamic_ref`) |
| `UserPromptPart` | `'user-prompt'` | User input (text or multimodal `UserContent`) |
| `ToolReturnPart` | `'tool-return'` | Tool execution result |
| `RetryPromptPart` | `'retry-prompt'` | Retry instructions (validation errors or `ModelRetry`) |

### Response Parts (`ModelResponsePart`)

| Part | Discriminator | Purpose |
|------|---------------|---------|
| `TextPart` | `'text'` | Plain text output |
| `ToolCallPart` | `'tool-call'` | Tool invocation with args |
| `BuiltinToolCallPart` | `'builtin-tool-call'` | Built-in tool call (e.g., web search) |
| `BuiltinToolReturnPart` | `'builtin-tool-return'` | Built-in tool result |
| `ThinkingPart` | `'thinking'` | Chain-of-thought content with optional signature |
| `FilePart` | `'file'` | Binary file output (images, etc.) |

### Multimodal Content

Rich content types: `ImageUrl`, `AudioUrl`, `DocumentUrl`, `VideoUrl`, `BinaryContent`, `BinaryImage`, `CachePoint`. All use Pydantic `@pydantic_dataclass` with discriminator fields.

### Conversation Forking / Rewind

**No built-in forking/rewind primitives.** However, the architecture supports these patterns:

1. **Rewind**: Pass `message_history` parameter to `run()`/`iter()` with a truncated list (e.g., `messages[:N]`). The `new_message_index` tracks where old history ends and new messages begin.

2. **Forking**: Copy the message list before a run and pass it as `message_history` to a new run. Since messages are plain dataclasses, `deepcopy()` or list slicing works.

3. **`output_tool_return_content`**: `AgentRunResult.all_messages()` accepts this parameter to modify the output tool's return content, enabling conversation continuation with custom responses.

4. **History Processors**: `HistoryProcessor` callables can transform the message history before each model request (e.g., summarization, truncation, sliding window).

---

## 4. Context Isolation

### `RunContext` (`_run_context.py:30`)

The central context object passed to tools, system prompt functions, and validators:

```python
@dataclass(repr=False, kw_only=True)
class RunContext(Generic[RunContextAgentDepsT]):
    deps: RunContextAgentDepsT     # User dependencies
    model: Model                    # Current model
    usage: RunUsage                 # Current usage stats
    prompt: str | ... | None        # Original user prompt
    messages: list[ModelMessage]    # Conversation so far
    validation_context: Any         # Pydantic validation context
    tracer: Tracer                  # OTel tracer
    retries: dict[str, int]         # Per-tool retry counts
    tool_call_id: str | None        # Current tool call ID
    tool_name: str | None           # Current tool name
    retry: int                      # Current retry number
    max_retries: int
    run_step: int                   # Current step
    run_id: str | None
    metadata: dict[str, Any] | None
    # ... more fields
```

### Context Variable Isolation

`_CURRENT_RUN_CONTEXT` (`ContextVar`) stores the current `RunContext` during model requests (`set_current_run_context` context manager). This enables:
- Tools to access the context via `get_current_run_context()`
- Isolation between concurrent agent runs (ContextVar is per-async-task)

### Main Thread vs Tool Execution

- **Model requests** (`ModelRequestNode._make_request`): Runs in the main async task with `set_current_run_context` active
- **Tool execution** (`_call_tools`): Tools run either sequentially or in parallel (`asyncio.create_task`). Each tool receives a `RunContext` built from the graph context via `build_run_context()`. Sync tool functions are dispatched via `run_in_executor()`.
- **Parallel execution modes** (`ParallelExecutionMode`): `'sequential'`, `'parallel_ordered_events'`, `'parallel_unordered_events'` — controlled per-toolset.

### Dependency Injection

Dependencies are typed via `AgentDepsT` generic parameter. The user passes `deps=...` at run time, and tools receive them through `RunContext.deps`. No DI container — plain generic typing.

---

## 5. Multi-Execution Session Handling

### Run Modes

| Method | Description | Return Type |
|--------|-------------|-------------|
| `agent.run()` | Full async run | `AgentRunResult[OutputDataT]` |
| `agent.run_sync()` | Sync wrapper | `AgentRunResult[OutputDataT]` |
| `agent.run_stream()` | Streaming run | `StreamedRunResult[AgentDepsT, OutputDataT]` |
| `agent.iter()` | Step-by-step async context manager | `AgentRun[AgentDepsT, OutputDataT]` |

### Session Continuity

There is **no persistent session object**. Multi-turn conversations work by:

1. Getting `result.all_messages()` from a completed run
2. Passing that list as `message_history` to the next run
3. The `new_message_index` tracks where prior history ends

```python
result1 = await agent.run("Hello")
result2 = await agent.run("Follow up", message_history=result1.all_messages())
```

### `AgentRun` (`run.py:28`)

A stateful, async-iterable run object obtained from `agent.iter()`:

```python
async with agent.iter("prompt") as agent_run:
    async for node in agent_run:
        # UserPromptNode, ModelRequestNode, CallToolsNode, End
        ...
    result = agent_run.result  # AgentRunResult once End is reached
```

Provides: `all_messages()`, `new_messages()`, `usage()`, `next_node`, `next(node)` for manual driving.

### `AgentRunResult` (`run.py:304`)

Final result after run completion:
- `output: OutputDataT` — validated output
- `all_messages()` / `new_messages()` — conversation history
- `usage()` — token/request statistics
- `response` — last `ModelResponse`
- `run_id`, `metadata`, `timestamp()`

---

## 6. Subagents Support

**No first-class subagent/delegation primitive.** However, agents can be composed:

1. **Tool-based delegation**: An agent's tool function can call another agent's `run()`:
   ```python
   @agent.tool
   async def delegate(ctx: RunContext[Deps], query: str) -> str:
       result = await sub_agent.run(query, deps=ctx.deps)
       return result.output
   ```

2. **`WrapperAgent`** (`agent/wrapper.py`): A wrapper that can modify behavior around an inner agent.

3. **`AbstractAgent`** (`agent/abstract.py`): Abstract base class defining the agent interface (`run`, `run_sync`, `run_stream`, `iter`), enabling custom agent implementations.

4. **A2A support** (`_a2a.py`): Agent-to-Agent protocol support for inter-agent communication via the `fasta2a` library.

---

## 7. Tool Discovery

### Tool Registration

Tools can be registered in multiple ways:

1. **Decorator-based** on Agent instance:
   - `@agent.tool` — function tool with `RunContext`
   - `@agent.tool_plain` — function tool without context

2. **Constructor parameter**: `tools=[Tool(...), func, ...]`

3. **Toolsets** (`toolsets/`): `AbstractToolset` implementations:
   - `FunctionToolset` — wraps plain functions
   - `CombinedToolset` — merges multiple toolsets
   - `FilteredToolset` — dynamic filtering per-step
   - `PrefixedToolset` — adds name prefixes
   - `RenamedToolset` — renames tools
   - `PreparedToolset` — runs a prepare function per-step
   - `ApprovalRequiredToolset` — human-in-the-loop approval
   - `DeferredToolset` / `ExternalToolset` — external/deferred execution
   - `DynamicToolset` — toolset from a function

4. **MCP Servers** (`mcp.py`): `MCPServerStdio`, `MCPServerHTTP`, `MCPServerSSE`, `MCPServerStreamableHTTP` — MCP protocol toolsets

### Tool Schema Generation

`_function_schema.py` handles introspection:
1. Inspects function signature and type hints
2. Detects if first param is `RunContext` (via `_takes_ctx()`)
3. Builds Pydantic `TypedDict` schema from parameters
4. Generates JSON Schema for the model
5. Creates `SchemaValidator` for argument validation
6. Extracts descriptions from docstrings (`_griffe.py` for Google/Sphinx/Numpy formats)

### Tool Definition

```python
@dataclass
class ToolDefinition:
    name: str
    description: str
    parameters_json_schema: JsonSchema
    kind: ToolKind  # 'function' | 'output' | 'external' | 'unapproved'
    # ...
```

### `ToolManager` (`_tool_manager.py`)

Manages all tools for a run. Called `for_run_step()` before each model request to re-evaluate dynamic tools. Handles:
- Tool call validation and dispatch
- Retry counting per tool
- Name conflict detection
- Parallel execution mode resolution

---

## 8. Serialization / Deserialization

### Message Serialization

All message types are Pydantic `@dataclass` with discriminator fields (`part_kind`, `kind`), enabling clean JSON serialization:

```python
ModelMessagesTypeAdapter = TypeAdapter(list[ModelMessage])
# Serialize
json_bytes = ModelMessagesTypeAdapter.dump_json(messages)
# Deserialize
messages = ModelMessagesTypeAdapter.validate_json(json_bytes)
```

Available on all result types:
- `result.all_messages_json()` → `bytes`
- `result.new_messages_json()` → `bytes`
- `agent_run.all_messages_json()` → `bytes`

### Binary Content

`BinaryContent` uses `ser_json_bytes='base64'` / `val_json_bytes='base64'` for automatic base64 encoding in JSON.

### Exception Serialization

`ModelRetry` has a `__get_pydantic_core_schema__` classmethod for Pydantic (de)serialization: `{"message": "...", "kind": "model-retry"}`.

### No Full Agent State Serialization

There is no built-in mechanism to serialize/deserialize the full agent state (graph position + state). The message history is the primary persistence point — you resume by passing `message_history` to a new run.

### Durable Execution

For full durability, the `durable_exec/` package provides integrations:
- **Temporal** (`durable_exec/temporal/`)
- **DBOS** (`durable_exec/dbos/`)
- **Prefect** (`durable_exec/prefect/`)

These wrap models, toolsets, and the agent itself to make execution resumable and fault-tolerant.

---

## 9. Extension Points

### Hooks / Callbacks

1. **`EventStreamHandler`** — async callable receiving `RunContext` and an async iterable of `AgentStreamEvent`. Set on agent or per-run. Receives all streaming events.

2. **`HistoryProcessor`** — transforms message history before each model request. Can be sync/async, with/without `RunContext`.

3. **System Prompt Functions** — `@agent.system_prompt` / `@agent.instructions` decorators for dynamic prompts. Can be marked `dynamic=True` for re-evaluation on each step.

4. **`ToolsPrepareFunc`** — `prepare_tools` / `prepare_output_tools` callbacks to modify tool definitions per-step.

5. **Output Validators** — `@agent.output_validator` decorators to post-validate outputs. Can raise `ModelRetry` to request corrections.

### Model Extension

`Model` is an ABC (`models/__init__.py`) with abstract methods:
- `request()` — sync model call
- `request_stream()` — streaming model call

Providers implement this: `OpenAIChatModel`, `AnthropicModel`, `GoogleModel`, `BedrockModel`, `MistralModel`, etc. Users can implement custom `Model` subclasses.

Special models:
- `FunctionModel` / `TestModel` (`models/function.py`, `models/test.py`) — for testing
- `FallbackModel` (`models/fallback.py`) — tries multiple models in sequence
- `InstrumentedModel` (`models/instrumented.py`) — wraps any model with OTel instrumentation

### Toolset Extension

`AbstractToolset` is the extension point for custom tool providers:
- Implement `get_tools()` and `call_tool()`
- Composable via `.filtered()`, `.prefixed()`, `.prepared()`, `.renamed()`, `.approval_required()`

### Agent Overrides

`agent.override()` context manager temporarily replaces name, deps, model, toolsets, tools, instructions, metadata — using `ContextVar` for async safety.

---

## 10. Observability

### OpenTelemetry Integration

**File:** `_instrumentation.py`, `models/instrumented.py`, `_otel_messages.py`

Instrumentation is opt-in via `instrument=True` or `InstrumentationSettings(...)`:

```python
agent = Agent('openai:gpt-4', instrument=True)
# or globally:
Agent.instrument_all(True)
```

### Spans Created

1. **Agent run span** — wraps the entire `iter()` execution
   - Name: `'agent run'` (v2) or `'invoke_agent {name}'` (v3)
   - Attributes: `model_name`, `agent_name`, usage stats, message history

2. **Tool execution span** — wraps tool calls via `tracer.start_as_current_span('running tools')`
   - Attributes: tool names, tool arguments, tool results

3. **Model request spans** — created inside `InstrumentedModel` for each LLM API call
   - Logs messages as OTel events (`gen_ai.system.message`, `gen_ai.user.message`, `gen_ai.tool.message`, etc.)

### `InstrumentationSettings`

```python
@dataclass
class InstrumentationSettings:
    tracer: Tracer
    include_content: bool = True       # Include message content in spans
    include_binary_content: bool = False
    version: int = 2                    # Instrumentation schema version
```

### Versioned Naming

`InstrumentationNames.for_version(v)` provides version-aware span names and attribute keys:
- **v2**: `'agent run'`, `'running tool'`, `'tool_arguments'`
- **v3+**: `'invoke_agent'`, `'execute_tool {name}'`, `'gen_ai.tool.call.arguments'`

### Logfire Integration

Designed for [Pydantic Logfire](https://docs.pydantic.dev/logfire/) which consumes OTel data. All message parts implement `otel_event()` and `otel_message_parts()` methods for structured event export.

### No Custom Event Bus

There is no custom pub/sub event system. Observability is purely through OpenTelemetry spans/events and the `EventStreamHandler` callback.

---

## 11. Interrupt Mechanism

### No Explicit Interrupt API

There is no `cancel()` or `interrupt()` method on agent runs. Interruption works through:

1. **`asyncio.CancelledError` propagation**: Both `abstract.py` (event streaming) and `_agent_graph.py` (tool execution) catch `CancelledError`, cancel pending tasks, and re-raise:
   ```python
   except asyncio.CancelledError as e:
       task.cancel(msg=e.args[0] if len(e.args) != 0 else None)
       raise
   ```

2. **`UsageLimits`**: Exceeding token/request limits raises `UsageLimitExceeded` which stops the loop.

3. **Context manager exit**: Since `iter()` returns an async context manager, exiting the `async with` block will clean up the run.

4. **Deferred tool calls**: Tools can raise `CallDeferred()` or `ApprovalRequired()` to pause execution and return `DeferredToolRequests` as the output. The run can be resumed later by passing `deferred_tool_results` to a new run.

### Human-in-the-Loop

The `ApprovalRequired` exception and `DeferredToolResults` mechanism provide a structured way to pause for human approval:
1. Tool raises `ApprovalRequired(metadata={...})`
2. Run completes with `DeferredToolRequests` output
3. User provides approvals/denials
4. New run with `deferred_tool_results=DeferredToolResults(approvals={...})`

---

## 12. Step-by-Step Execution

### `agent.iter()` — Primary Step API

```python
async with agent.iter("prompt") as agent_run:
    async for node in agent_run:
        if isinstance(node, ModelRequestNode):
            # Inspect/modify before model call
            ...
        elif isinstance(node, CallToolsNode):
            # Inspect model response before tool execution
            ...
        elif isinstance(node, End):
            # Run complete
            ...
```

### Manual Node Driving

`AgentRun.next(node)` allows manually driving execution:

```python
async with agent.iter("prompt") as agent_run:
    node = agent_run.next_node
    while not isinstance(node, End):
        # Optionally mutate or replace node
        node = await agent_run.next(node)
```

### Streaming Within Steps

`ModelRequestNode.stream()` provides streaming within a step:

```python
async with agent.iter("prompt") as agent_run:
    async for node in agent_run:
        if isinstance(node, ModelRequestNode):
            async with node.stream(agent_run.ctx) as stream:
                async for event in stream.stream_text(delta=True):
                    print(event, end="")
```

`CallToolsNode.stream()` emits `HandleResponseEvent` events for tool call start/end.

---

## 13. Tool Handling

### Tool Execution Flow

1. `CallToolsNode._run_stream()` classifies tool calls by kind: `output`, `function`, `external`, `unapproved`, `unknown`
2. **Output tools** processed first (validation via `ToolManager.handle_call()`)
3. **Function tools** processed via `_call_tools()` (parallel or sequential)
4. **Deferred/unapproved tools** collected for `DeferredToolRequests`
5. **Unknown tools** trigger retry

### `_call_tools()` (`_agent_graph.py:1064`)

- Checks `usage_limits.tool_calls_limit` before execution
- Emits `FunctionToolCallEvent` for each call
- Creates OTel span: `tracer.start_as_current_span('running tools')`
- Handles `CallDeferred` and `ApprovalRequired` exceptions
- Supports three parallel execution modes:
  - `sequential` — one at a time
  - `parallel_ordered_events` — all at once, events in call order
  - `parallel_unordered_events` — all at once, events as completed

### `ToolManager.handle_call()` (`_tool_manager.py`)

For each tool call:
1. Look up `ToolsetTool` by name
2. Validate arguments via `args_validator.validate_json()`
3. Set up `RunContext` with tool-specific fields (retry count, tool_call_id, etc.)
4. Call `toolset.call_tool()` within OTel span
5. Handle `ModelRetry` → `RetryPromptPart` (with per-tool retry counting)
6. Handle timeout via `asyncio.timeout()`
7. Return `ToolReturnPart` or `RetryPromptPart`

### End Strategy

`EndStrategy = Literal['early', 'exhaustive']`:
- **`'early'`** (default): Once a valid output tool result is found, remaining function tools are skipped
- **`'exhaustive'`**: All function tools execute even after a final result is found

---

## 14. Error Handling

### Exception Hierarchy (`exceptions.py`)

```
Exception
├── ModelRetry              — Tool requests LLM retry (becomes RetryPromptPart)
├── CallDeferred            — Tool defers execution (external/async)
├── ApprovalRequired        — Tool needs human approval
├── UserError (RuntimeError) — Developer usage mistake
├── AgentRunError (RuntimeError)
│   ├── UsageLimitExceeded  — Token/request/tool-call limit hit
│   ├── ConcurrencyLimitExceeded — Max concurrent runs exceeded
│   ├── UnexpectedModelBehavior — Model misbehavior
│   │   ├── ContentFilterError  — Content filter triggered
│   │   └── IncompleteToolCall  — Token limit during tool call generation
│   └── ModelAPIError       — Provider API failure
│       └── ModelHTTPError  — HTTP 4xx/5xx from provider
├── ToolRetryError          — Internal: wraps RetryPromptPart for tool validation failures
└── FallbackExceptionGroup  — All fallback models failed
```

### Retry Mechanisms

1. **Output validation retries**: `GraphAgentState.increment_retries()` tracks retries. On failure, `RetryPromptPart` is sent back to the model. Exceeding `max_result_retries` raises `UnexpectedModelBehavior`.

2. **Tool retries**: Per-tool retry counting in `ToolManager`. Each tool has its own `max_retries`. `ModelRetry` exception in tool → `RetryPromptPart` sent to model.

3. **HTTP retries**: `retries.py` provides `TenacityTransport`/`AsyncTenacityTransport` wrappers for `httpx` with configurable retry strategies, exponential backoff, and `Retry-After` header support.

4. **Empty response handling**: If model returns empty parts, the framework retries by re-sending the last request.

5. **Fallback models**: `FallbackModel` (`models/fallback.py`) tries multiple models in sequence, collecting errors into `FallbackExceptionGroup`.

### Validation Errors

Tool argument validation errors (from Pydantic) are automatically converted to `RetryPromptPart` with detailed error information, sent back to the model for self-correction.

---

## Summary of Key Design Decisions

| Aspect | Decision |
|--------|----------|
| **Loop** | Graph-based via `pydantic_graph`, not a simple while loop |
| **State** | Ephemeral per-run (`GraphAgentState`), no persistent sessions |
| **Messages** | Plain Pydantic dataclasses with discriminator-based unions |
| **Forking/Rewind** | Not built-in; supported via message list manipulation |
| **DI** | Generic typing + `RunContext.deps`, no DI container |
| **Tools** | Rich `AbstractToolset` hierarchy with composable decorators |
| **Subagents** | No first-class support; composable via tool delegation |
| **Serialization** | Pydantic `TypeAdapter` for messages; no full state serialization |
| **Observability** | Pure OpenTelemetry (spans + events), Logfire-ready |
| **Interrupts** | asyncio cancellation + deferred tools for human-in-the-loop |
| **Stepping** | `agent.iter()` with async iteration over graph nodes |
| **Errors** | Typed exception hierarchy + automatic model retry via `RetryPromptPart` |
| **Durability** | Optional via Temporal/DBOS/Prefect integrations |
