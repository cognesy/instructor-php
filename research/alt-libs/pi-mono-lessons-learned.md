# pi-mono: High-Impact Design Improvement Opportunities

Lessons learned from comparing pi-mono's architecture against Pydantic AI's mature agentic framework design. Focused on core agentic loop, data models, tool handling, extensibility, context isolation, and error handling.

---

## 1. Agent Loop: From Flat While-Loop to Graph-Based Execution

### Current State (pi-mono)

The agent loop (`agent-loop.ts`) is a nested while-loop:

```
outer loop (follow-up messages)
  inner loop (tool calls + steering)
    streamAssistantResponse()
    executeToolCalls()
    check steering queue
  check follow-up queue
```

This is functional but tightly couples iteration logic with tool execution, steering, and follow-up handling into one monolithic `runLoop()` function (~200 lines).

### Opportunity

**Decompose the loop into discrete, named execution phases (nodes).**

Pydantic AI models its loop as a graph of typed nodes: `UserPromptNode → ModelRequestNode → CallToolsNode → End`. Each node:
- Has a single responsibility
- Declares its possible next nodes via return types
- Can be individually intercepted, inspected, or replaced

**Why this matters:**
- **Step-by-step execution** — `agent.iter()` yields individual nodes, letting consumers inspect state between phases, add conditional logic, or abort. pi-mono can only observe via events after the fact.
- **Streaming within steps** — Each node can independently offer streaming (e.g., `ModelRequestNode.stream()`).
- **Testability** — Individual nodes can be unit-tested in isolation.
- **Extension points** — Nodes can be wrapped or replaced without modifying the loop.

**Recommendation:** Define a `Phase` or `Step` enum/union (`PrepareContext | ModelRequest | ProcessToolCalls | Finalize`) and refactor the loop to iterate over phases. Expose an `iter()` API that yields phases, allowing consumers to drive execution step-by-step rather than only observing events.

---

## 2. Agent State: Separate Mutable State from Immutable Configuration

### Current State

`AgentState` in `types.ts` mixes configuration and runtime state:

```typescript
interface AgentState {
  systemPrompt: string;      // Config
  model: Model<any>;         // Config
  thinkingLevel: ThinkingLevel; // Config
  tools: AgentTool<any>[];   // Config
  messages: AgentMessage[];  // Mutable state
  isStreaming: boolean;       // Runtime state
  streamMessage: AgentMessage | null; // Runtime state
  pendingToolCalls: Set<string>; // Runtime state
  error?: string;            // Runtime state
}
```

The `Agent` class also stores runtime state as class fields (`abortController`, `steeringQueue`, `followUpQueue`, `runningPrompt`, etc.) without a clear boundary.

### Opportunity

**Split into `AgentConfig` (immutable per-run) and `RunState` (mutable per-run).**

Pydantic AI separates this cleanly:
- `GraphAgentDeps` — immutable configuration passed to the run (model, tools, limits, validators, tracer)
- `GraphAgentState` — mutable state that changes during execution (messages, usage, retries, run_step, run_id)
- The `Agent` class itself is stateless between runs

**Why this matters:**
- **Concurrent runs** — Separating per-run state from agent config would allow the same `Agent` to run multiple concurrent conversations without shared mutable state.
- **Snapshot/restore** — A clean `RunState` object is trivially serializable for pause/resume.
- **Reasoning** — Developers can reason about what changes during a run vs. what's fixed.

**Recommendation:** Create a `RunState` type holding `messages`, `isStreaming`, `streamMessage`, `pendingToolCalls`, `error`, `usage`, `runId`. Pass it through the loop rather than mutating `Agent._state` directly.

---

## 3. Conversation Data Model: Add Discriminated Part Types

### Current State

Messages use a flat content array:

```typescript
interface AssistantMessage {
  role: "assistant";
  content: (TextContent | ThinkingContent | ToolCall)[];
  // ...
}
```

This works well but lacks:
- A discriminator field on the union type for type-safe serialization
- Metadata per-part (provider details, signatures are scattered inconsistently)
- A unified way to handle multimodal content in tool results

### Opportunity

**Add a `part_kind` discriminator and unify content part metadata.**

Pydantic AI uses `part_kind` discriminators on every part type, enabling:
- Type-safe JSON deserialization without custom logic
- Clean `switch` statements
- Extensibility (new part types don't break existing code)

Also notable: Pydantic AI separates request parts (`SystemPromptPart`, `UserPromptPart`, `ToolReturnPart`, `RetryPromptPart`) from response parts, making the data model self-documenting.

**Recommendation:** This is a lower-priority refinement. The current union types with `type` field already serve as discriminators. Consider adding explicit `partKind` if the number of content types grows.

---

## 4. Session Tree Structure: Already Ahead — Strengthen the API

### Current State

pi-mono's `SessionManager` is more sophisticated than Pydantic AI's approach:
- **Tree-structured sessions** with `id`/`parentId` on every entry
- **Branching** via `branch(entryId)` — moves the leaf pointer
- **Branch summaries** — LLM-generated summaries of abandoned paths
- **Compaction** — LLM summarization of old context with `firstKeptEntryId`
- **Labels** — User-defined bookmarks on entries
- **Custom entries** — Extension-specific data persistence

Pydantic AI has none of this — it uses a flat `list[ModelMessage]` and says "copy the list to fork."

### Opportunity

**Expose tree operations at the `Agent` level, not just `SessionManager`.**

Currently, branching and tree navigation are only accessible through `AgentSession` (the coding-agent layer), not through the core `Agent` class. The core `Agent` only sees a flat `messages[]` array.

**Recommendation:** Consider surfacing a `Branch` or `Checkpoint` concept in the core `agent` package so downstream consumers (not just coding-agent) can benefit from forking and rewind.

---

## 5. Context Isolation: Add Per-Run Isolation for Concurrent Agents

### Current State

pi-mono has **no per-run context isolation**. The `Agent` class is a stateful singleton per conversation:
- `Agent._state.messages` is a shared mutable array
- `Agent.prompt()` throws if already streaming (`"Agent is already processing a prompt"`)
- Tool functions receive `(toolCallId, params, signal, onUpdate)` — no run context object
- No equivalent of `ContextVar` for async-safe isolation

### Opportunity

**Introduce a `RunContext` object passed to tools and lifecycle hooks.**

Pydantic AI's `RunContext` gives tools access to:
- `deps` — user-injected dependencies (database connections, API clients, etc.)
- `messages` — conversation history so far
- `usage` — token/cost tracking
- `retry` / `max_retries` — current retry state for this tool
- `tool_name`, `tool_call_id` — identity
- `run_step` — current loop iteration
- `model` — which model is being used

In pi-mono, tools receive only `(toolCallId, params, signal, onUpdate)`. If a tool needs agent context (current messages, model info, usage stats), it must close over external state.

**Why this matters:**
- **Dependency injection** — Tools can receive typed dependencies without global state.
- **Tool-aware retry** — Tools know their retry count and can adjust behavior on the last attempt.
- **Multi-tenant** — Different runs can inject different deps without sharing state.

**Recommendation:** Add a `ToolContext` (or `RunContext`) parameter to the `AgentTool.execute()` signature:

```typescript
interface ToolContext<TDeps = unknown> {
  deps: TDeps;
  messages: readonly AgentMessage[];
  model: Model<any>;
  runId: string;
  retry: number;
  maxRetries: number;
  signal?: AbortSignal;
}
```

This is a **high-impact change** — it enables dependency injection and makes tools self-contained.

---

## 6. Tool Execution: Add Composable Toolset Abstraction

### Current State

Tools are a flat array on `AgentState`:

```typescript
tools: AgentTool<any>[];
```

Tool management (filtering, naming, per-step re-evaluation) is handled ad-hoc by `AgentSession._buildRuntime()` which manually maps tools from a registry. There's no composable abstraction for tool collections.

### Opportunity

**Introduce a `Toolset` abstraction with composable decorators.**

Pydantic AI's `AbstractToolset` hierarchy is powerful:

```
AbstractToolset
├── FunctionToolset      — wraps functions
├── CombinedToolset      — merges multiple toolsets
├── FilteredToolset      — dynamic per-step filtering
├── PrefixedToolset      — adds name prefixes
├── RenamedToolset       — renames tools
├── PreparedToolset      — runs prepare function per-step
├── ApprovalRequiredToolset — human-in-the-loop
├── DeferredToolset      — external execution
├── DynamicToolset       — toolset from a function
└── MCP toolsets         — MCPServerStdio, MCPServerHTTP, etc.
```

Composable: `toolset.filtered(...).prefixed("sub_").approval_required()`.

**Why this matters:**
- **Dynamic tools** — Tools can be added/removed/modified per-step based on context (e.g., different tools for different conversation phases).
- **Namespacing** — Multiple subagents or tool providers can coexist without name collisions.
- **MCP integration** — External tool servers become first-class.
- **Approval workflows** — Human-in-the-loop becomes a toolset decorator, not custom logic.

**Recommendation:** Define a `Toolset` interface:

```typescript
interface Toolset {
  getTools(ctx: ToolContext): Promise<AgentTool[]>;
  callTool(name: string, args: any, ctx: ToolContext): Promise<AgentToolResult>;
}
```

Migrate the current flat `tools[]` array to use a `CombinedToolset` internally. This enables dynamic tool composition without breaking existing code.

---

## 7. Tool Result Incorporation: Add Validation and Retry Loop

### Current State

Tool execution in `agent-loop.ts:executeToolCalls()`:
1. Validates tool arguments via `validateToolArguments()` (TypeBox schema)
2. Calls `tool.execute()`
3. On error, creates a `ToolResultMessage` with `isError: true`
4. Sends result back as next context message

There is **no per-tool retry mechanism**. If a tool call fails, the error goes back to the LLM as a tool result, and the LLM decides what to do. There's also no `ModelRetry` equivalent — tools can't explicitly request the LLM to try again with different arguments.

### Opportunity

**Add per-tool retry counting and a `ModelRetry` exception pattern.**

Pydantic AI allows tools to raise `ModelRetry("try with different args")`, which:
1. Creates a `RetryPromptPart` sent back to the model
2. Tracks per-tool retry count
3. Respects `max_retries` per tool
4. On exceeding retries, raises `ToolRetryError`

**Why this matters:**
- **Self-correcting validation** — When argument validation fails, the error details are sent back to the model with structured error information, enabling the model to fix its own mistakes.
- **Tool-level retry budgets** — Expensive tools can have low retry limits while cheap tools can have higher limits.
- **Graceful degradation** — Retry exhaustion becomes a typed error, not an infinite loop.

**Recommendation:** Add `maxRetries` to `AgentTool`, track retries per-tool in the loop, and add a `ToolRetry` error type that tools can throw to request re-invocation with different args.

---

## 8. Subagent Support: Formalize the Pattern

### Current State

There is no subagent concept in the core `agent` or `ai` packages. The `mom` package uses `Agent` from `pi-agent-core` and wraps it with `AgentSession` from `pi-coding-agent`, but this is at the application level.

Neither Pydantic AI nor pi-mono have first-class subagent primitives. Both handle it via tool-based delegation (a tool calls another agent).

### Opportunity

**Formalize subagent delegation as a tool pattern with context isolation.**

Key requirements for proper subagent support:
1. **Context isolation** — Subagent messages shouldn't leak into parent context
2. **Result summarization** — Subagent output is returned to parent as a tool result
3. **Dependency forwarding** — Subagent inherits parent's deps/credentials
4. **Usage aggregation** — Token usage from subagent rolls up to parent
5. **Cancellation propagation** — Aborting parent aborts subagents

**Recommendation:** Create a `SubagentTool` utility that wraps an `Agent` as a tool:

```typescript
function createSubagentTool(agent: Agent, options: {
  name: string;
  description: string;
  summarize?: (messages: AgentMessage[]) => string;
}): AgentTool;
```

This keeps subagents as tools (composable) while standardizing the isolation pattern.

---

## 9. Error Handling: Add Typed Exception Hierarchy

### Current State

Errors in pi-mono are string-based. `Agent._runLoop()` catches all errors and creates an error `AgentMessage`:

```typescript
} catch (err: any) {
  const errorMsg: AgentMessage = {
    role: "assistant",
    stopReason: this.abortController?.signal.aborted ? "aborted" : "error",
    errorMessage: err?.message || String(err),
    // ...
  };
}
```

`AgentSession` adds auto-retry logic by checking `_isRetryableError()` which inspects `errorMessage` strings:

```typescript
private _isRetryableError(msg: AssistantMessage): boolean {
  // Checks for "overloaded", "rate_limit", 529, 503 in errorMessage
}
```

### Opportunity

**Define a typed error hierarchy.**

Pydantic AI's exception structure:

```
AgentRunError
├── UsageLimitExceeded     — token/request/tool-call limit hit
├── ConcurrencyLimitExceeded
├── UnexpectedModelBehavior — model misbehavior
│   ├── ContentFilterError  — content filter triggered
│   └── IncompleteToolCall  — token limit during tool call
└── ModelAPIError           — provider API failure
    └── ModelHTTPError      — HTTP 4xx/5xx

ModelRetry      — tool requests LLM retry
CallDeferred    — tool defers for external handling
ApprovalRequired — tool needs human approval
```

**Why this matters:**
- **Retry logic becomes pattern-matching**, not string inspection
- **Consumers can catch specific errors** (e.g., `catch (e) { if (e instanceof RateLimitError) ... }`)
- **Error context is preserved** (HTTP status code, retry-after header, provider details)

**Recommendation:** Create error classes:

```typescript
class AgentError extends Error { }
class ModelAPIError extends AgentError { status: number; retryAfterMs?: number; }
class RateLimitError extends ModelAPIError { }
class OverloadedError extends ModelAPIError { }
class UsageLimitExceeded extends AgentError { }
class ToolRetryError extends AgentError { toolName: string; retry: number; }
```

---

## 10. Extensibility: Events vs. Extension System — Different Design Points

### Current State

pi-mono has two extension mechanisms:
1. **`AgentEvent` stream** (core `agent` package) — Simple event emitter with typed events (`agent_start`, `turn_start`, `message_start/update/end`, `tool_execution_start/update/end`, `turn_end`, `agent_end`)
2. **Extension system** (coding-agent package) — Full plugin architecture with `ExtensionRunner`, lifecycle hooks (`before_agent_start`, `turn_start`, `turn_end`, `input`, etc.), UI context, commands, keybindings, tool registration, and session control

The extension system is powerful but lives in `coding-agent`, not in the core `agent` package. This means other consumers (like `mom`) can't use extensions.

### Opportunity

**Move key extension hooks into the core `agent` package.**

The most reusable hooks:
- `before_agent_start` — Modify system prompt or inject context before each run
- `turn_start` / `turn_end` — Per-turn lifecycle
- `input` — Transform or intercept user input
- Tool wrapping — Wrap tool execution with pre/post hooks

These are agent-level concerns, not coding-agent-specific. Moving them down would let `mom` and other consumers benefit.

**Pydantic AI's approach for comparison:**
- `EventStreamHandler` — async callback receiving all streaming events
- `HistoryProcessor` — transforms message history before each model request
- `ToolsPrepareFunc` — modifies tool definitions per-step
- `OutputValidator` — post-validates outputs, can trigger retry
- `agent.override()` — context manager for test/config overrides

**Recommendation:** Add optional lifecycle hooks to `AgentLoopConfig`:

```typescript
interface AgentLoopConfig {
  // existing fields...
  onTurnStart?: (ctx: LoopContext) => Promise<void>;
  onTurnEnd?: (ctx: LoopContext, message: AssistantMessage) => Promise<void>;
  onBeforeModelRequest?: (ctx: LoopContext, messages: Message[]) => Promise<Message[]>;
  onToolResult?: (ctx: LoopContext, result: ToolResultMessage) => Promise<ToolResultMessage>;
}
```

---

## 11. Observability: Add Structured Telemetry

### Current State

pi-mono has no built-in observability beyond the event stream and manual logging. Token usage is tracked in `AssistantMessage.usage` but there are no spans, metrics, or structured telemetry.

### Opportunity

**Add OpenTelemetry span creation at key execution points.**

Pydantic AI creates spans for:
- Agent run (wraps entire execution)
- Model requests (wraps each LLM API call)
- Tool execution (wraps each tool call with arguments and results)

Each span carries structured attributes: model name, tool name, token counts, etc.

**Why this matters:**
- **Performance debugging** — See which tools are slow, which model calls are expensive
- **Cost tracking** — Aggregate token usage across runs with standard tooling
- **Production monitoring** — Integrate with Datadog, Honeycomb, Grafana, etc.

**Recommendation:** Add optional `tracer` to `AgentLoopConfig`. Wrap `streamAssistantResponse()` and `executeToolCalls()` with spans. This is low-effort and opt-in.

---

## 12. Deferred Tool Execution / Human-in-the-Loop

### Current State

pi-mono has **steering** (interrupt mid-run with a new message) and **follow-up** (queue message for after run), which are unique and well-designed mechanisms for human interaction during agent execution. Steering can skip remaining tool calls, which is a practical human-in-the-loop pattern.

However, there's no mechanism for a **tool** to pause and request human approval before proceeding.

### Opportunity

**Add a `DeferredToolResult` / `ApprovalRequired` pattern.**

Pydantic AI lets tools raise `ApprovalRequired()`:
1. Tool execution pauses
2. Run completes with `DeferredToolRequests` as output
3. Human reviews the pending tool calls
4. New run with `deferred_tool_results` approves/denies each call
5. Approved calls are re-executed, denied calls get error results

**Why this matters for coding agents:**
- File writes and bash commands could require approval
- Dangerous operations (rm, git push) could be gated
- Currently, tool approval is implemented at the TUI level with extension hooks, not as a core loop mechanism

**Recommendation:** This is partially handled by the extension system's tool wrapping. Consider whether the approval pattern should also be available in the core `agent` package for non-coding-agent consumers.

---

## 13. Parallel Tool Execution

### Current State

pi-mono executes tool calls **sequentially** in `executeToolCalls()`:

```typescript
for (let index = 0; index < toolCalls.length; index++) {
  // ... execute one at a time
}
```

### Opportunity

**Support parallel tool execution with configurable modes.**

Pydantic AI offers three modes:
- `sequential` — one at a time (current pi-mono behavior)
- `parallel` — all at once via `asyncio.create_task`
- `parallel_ordered_events` — parallel execution, events emitted in order

**Why this matters:**
- Multiple read operations, web fetches, or independent computations can run concurrently
- Significant latency reduction when the LLM requests multiple tool calls in one response

**Recommendation:** Add a `parallelToolExecution` option to `AgentLoopConfig`. Use `Promise.all()` for parallel mode. Allow individual tools to opt into sequential execution (e.g., bash commands that modify state).

---

## 14. Message History Processing: Add Pre-Request Transforms

### Current State

pi-mono has `transformContext` and `convertToLlm` — two-stage message transformation:

```
AgentMessage[] → transformContext() → AgentMessage[] → convertToLlm() → Message[] → LLM
```

This is clean and well-designed. `transformContext` handles context window management, and `convertToLlm` handles type conversion.

### Opportunity

**Make transforms composable and add built-in strategies.**

Pydantic AI's `HistoryProcessor` is a simple callable, but the framework could benefit from built-in processors (and so could pi-mono):
- Sliding window (keep last N messages)
- Token budget (estimate and truncate)
- Summarize-and-replace (LLM summarization of old context — pi-mono already has this via compaction)
- Tool result truncation (collapse large tool outputs)

**Recommendation:** Add a `ContextStrategy` type with built-in implementations. The compaction logic in `AgentSession` could be generalized into a reusable `CompactionStrategy` that works at the `agent` package level.

---

## Summary: Priority Matrix

| # | Improvement | Impact | Effort | Priority |
|---|------------|--------|--------|----------|
| 5 | **RunContext / ToolContext** — dependency injection for tools | Very High | Medium | **P0** |
| 9 | **Typed error hierarchy** | High | Low | **P0** |
| 7 | **Per-tool retry + ModelRetry** | High | Medium | **P1** |
| 6 | **Toolset abstraction** | High | Medium | **P1** |
| 2 | **Separate config from run state** | High | Medium | **P1** |
| 13 | **Parallel tool execution** | High | Low | **P1** |
| 1 | **Graph/phase-based loop + iter()** | High | High | **P2** |
| 10 | **Move extension hooks to core** | Medium | Medium | **P2** |
| 11 | **OpenTelemetry integration** | Medium | Low | **P2** |
| 8 | **SubagentTool utility** | Medium | Low | **P2** |
| 12 | **Deferred/approval in core** | Medium | Medium | **P3** |
| 14 | **Composable context strategies** | Low | Medium | **P3** |
| 4 | **Expose tree ops in core** | Low | Medium | **P3** |
| 3 | **Discriminated part types** | Low | Low | **P3** |

---

## What pi-mono Does Better Than Pydantic AI

For balance, areas where pi-mono's design is already superior:

1. **Session tree with branching** — Pydantic AI has no session concept at all; pi-mono has full tree-structured sessions with branching, summaries, compaction, and labels
2. **Steering and follow-up queues** — Unique mechanism for human interaction during agent runs; Pydantic AI has no equivalent
3. **Event stream design** — The `EventStream<T, R>` generic with backpressure is clean; Pydantic AI's event model is more scattered
4. **Custom message types via declaration merging** — Elegant TypeScript pattern for extending message types without modifying library code
5. **Auto-compaction** — Automatic context window management with LLM summarization; Pydantic AI delegates this entirely to the user
6. **Auto-retry with backoff** — Built into `AgentSession`; Pydantic AI only has HTTP-level retries, not agent-level
7. **Extension system** — Full plugin architecture with UI, commands, keybindings, tool wrapping; Pydantic AI has nothing comparable
8. **Cross-provider message normalization** — The `convertToLlm` boundary cleanly separates app messages from LLM messages; Pydantic AI's message model is LLM-only
