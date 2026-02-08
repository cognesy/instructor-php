# Pi Mono vs Instructor PHP — Architectural Comparison

## Overview

| Dimension | Pi Mono | Instructor PHP |
|-----------|---------|----------------|
| **Language** | TypeScript (Node.js) | PHP 8.3+ |
| **Repo structure** | Monorepo, 7 packages | Monorepo, ~26 packages (3 in scope) |
| **Primary focus** | Coding agent (interactive CLI) | Structured extraction + generic agent framework |
| **Package layering** | `ai` → `agent` → `coding-agent` | `polyglot` → `instructor` → `agents` |

### In-Scope Packages

| Layer | Pi Mono | Instructor PHP |
|-------|---------|----------------|
| LLM provider abstraction | `@mariozechner/pi-ai` | `cognesy/polyglot` |
| Structured output / core loop | `@mariozechner/pi-agent-core` | `cognesy/instructor` |
| Domain agent framework | `@mariozechner/pi-coding-agent` | `cognesy/agents` |

Both use a three-layer architecture where the lowest layer abstracts LLM providers, the middle layer provides a core loop/extraction mechanism, and the top layer provides the domain-specific agent runtime.

---

## 1. Agent Loop

### Pi Mono — Dual-Loop with Streaming

The agent loop is a **dual-loop** pattern:

```
agentLoop()
  └─ runLoop()
       ├─ OUTER LOOP: while(true)      ← follow-up messages
       │    ├─ INNER LOOP: while(hasToolCalls || steeringMessages)
       │    │    ├─ Check steering messages
       │    │    ├─ streamAssistantResponse()    ← LLM call (streaming)
       │    │    ├─ executeToolCalls()            ← sequential
       │    │    └─ Check for new steering
       │    └─ getFollowUpMessages()
       └─ agent_end
```

- **Streaming-first**: LLM responses stream via `EventStream<E,R>` (push-based async iterable)
- **Two injection points**: steering (mid-execution redirection) and follow-up (post-completion chaining)
- Returns `EventStream` of events, consumed via `for await`
- The loop is a free function (`agentLoop()`) wrapped by the `Agent` class

### Instructor PHP — Step-by-Step Loop with Driver Pattern

The agent loop uses a **single-loop** pattern with a pluggable **driver**:

```
AgentLoop.iterate(state)
  └─ while(true)
       ├─ onBeforeStep(state)           ← interceptor hook
       ├─ handleToolUse(state)          ← delegates to driver.useTools()
       │    ├─ Driver.getToolCallResponse()    ← LLM call (sync)
       │    ├─ executor.executeTools()         ← sequential
       │    └─ Driver.buildStepFromResponse()
       ├─ onAfterStep(state)            ← interceptor hook
       ├─ shouldStop(state)             ← evaluates stop signals
       └─ yield state                   ← generator-based iteration
```

- **Synchronous with generators**: Uses PHP generators (`yield`) for step iteration; no streaming
- **Driver pattern**: The `CanUseTools` interface abstracts how tools are invoked:
  - `ToolCallingDriver` — native tool/function calling via API
  - `ReActDriver` — Thought/Action/Observation prompting via structured output
  - `FakeAgentDriver` — scripted scenarios for testing
- Returns `iterable<AgentState>` via PHP generator; callers can iterate steps
- The loop can be consumed all-at-once (`execute()`) or step-by-step (`iterate()`)

### Key Differences

| Aspect | Pi Mono | Instructor PHP |
|--------|---------|----------------|
| Streaming | Native streaming via EventStream | No streaming; sync request/response |
| Loop shape | Dual-loop (inner tools + outer follow-ups) | Single loop with stop signal evaluation |
| Step iteration | No step-level pause; runs to completion | Generator-based; `iterate()` yields each step |
| LLM interaction | Single strategy (streaming tool calls) | Pluggable drivers (ToolCalling, ReAct, Fake) |
| Mid-execution injection | Steering + follow-up message queues | Hooks can modify state; continuation requests |

---

## 2. Agent State Data Model

### Pi Mono — Mutable State in Agent Class

```typescript
interface AgentState {
  systemPrompt: string;
  model: Model<any>;
  thinkingLevel: ThinkingLevel;    // "off"|"minimal"|"low"|"medium"|"high"|"xhigh"
  tools: AgentTool<any>[];
  messages: AgentMessage[];         // Full conversation
  isStreaming: boolean;
  streamMessage: AgentMessage|null; // Currently streaming message
  pendingToolCalls: Set<string>;
  error?: string;
}
```

- **Mutable**: Direct property assignment on the `Agent` class's internal state
- **Flat structure**: Messages, tools, model, and streaming state at the same level
- **No execution tracking**: No built-in concept of "steps" or "executions" at the core level
- **Extensible messages**: `CustomAgentMessages` via declaration merging

### Instructor PHP — Immutable State with Session/Execution Split

```php
final readonly class AgentState {
    // Session data (persists across executions)
    private string $agentId;
    private ?string $parentAgentId;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;
    private AgentContext $context;        // messages, metadata, system prompt, response format

    // Execution data (transient, null between executions)
    private ?ExecutionState $execution;  // steps, stop signals, continuation
}
```

- **Immutable**: All state objects are `readonly`; mutations return new instances (`with()` pattern)
- **Two-tier structure**: Session-level data (identity, messages) vs execution-level data (steps, status, continuation)
- **Rich execution tracking**: `ExecutionState` → `StepExecutions` → `StepExecution` → `AgentStep` → `ToolExecutions`
- **Agent context**: `AgentContext` wraps `MessageStore` (sectioned message storage) + `Metadata` + system prompt + response format

### Key Differences

| Aspect | Pi Mono | Instructor PHP |
|--------|---------|----------------|
| Mutability | Mutable state on Agent class | Fully immutable (`readonly` + `with()` cloning) |
| Execution tracking | None at core level | Rich: ExecutionState → StepExecutions → AgentStep → ToolExecution |
| Session identity | External (SessionManager) | Built-in: agentId, parentAgentId, timestamps |
| Message storage | Flat `AgentMessage[]` array | Sectioned `MessageStore` (messages, buffer, summary, execution_buffer) |
| Extensibility | Declaration merging for message types | Metadata bag + context sections |

---

## 3. Conversation Data Model

### Pi Mono — Append-Only JSONL Tree

Sessions are stored as **append-only JSONL files** with entries forming a **tree** via `id`/`parentId`:

- **Entry types**: message, thinking_level_change, model_change, compaction, branch_summary, custom, label, session_info
- **Leaf pointer**: `leafId` tracks the current position in the tree
- **Forking**: Move `leafId` to earlier entry → next append creates a branch
- **Rewind**: `buildSessionContext()` walks `parentId` chain from leaf → root
- **Context resolution**: Handles compaction entries (summaries of earlier context)
- **Cross-session forking**: `createBranchedSession()` extracts a path into a new file; `forkFrom()` copies between projects
- **Version migration**: v1→v2→v3 migration system

### Instructor PHP — Sectioned MessageStore

Messages are managed through `AgentContext` with a **sectioned `MessageStore`**:

- **Sections**: `messages` (main), `buffer` (overflow holding area), `summary` (compacted context), `execution_buffer` (current execution output)
- **Message routing**: `withStepOutputRouted()` routes tool output to `execution_buffer` during steps, merges to `messages` on final response
- **Inference context assembly**: `messagesForInference()` concatenates sections in order: summary → buffer → messages → execution_buffer
- **No tree structure**: Linear conversation; no built-in forking or branching
- **No persistence built-in**: State is serializable (`toArray()`/`fromArray()`) but no file format is prescribed

### Forking and Rewind

| Feature | Pi Mono | Instructor PHP |
|---------|---------|----------------|
| Conversation branching | Tree structure with `id`/`parentId` | Not built-in |
| Rewind | Move leaf pointer → rebuild context | Not built-in (could reset state) |
| Cross-session forking | `createBranchedSession()`, `forkFrom()` | Not built-in |
| Persistence format | Append-only JSONL | Serializable to/from array (no prescribed format) |
| Context compaction | Built-in via compaction entries | Via `UseSummarization` capability (hook-based) |

---

## 4. Context Isolation

### Pi Mono

- **No process isolation** for tools — all run in the same Node.js process
- **AbortSignal** per tool for cancellation
- **Sequential tool execution** — one at a time
- **`mom` package** provides process-level sandboxing for supervised agents
- **Proxy stream** (`streamProxy()`) for network-level LLM isolation

### Instructor PHP

- **No process isolation** — PHP is single-threaded; all runs in-process
- **No AbortSignal equivalent** — PHP lacks cooperative cancellation primitives
- **Sequential tool execution** — tools execute one at a time in `ToolExecutor`
- **Parent agent ID tracking** — `AgentState.parentAgentId` links child to parent
- **Subagent policy** — `SubagentPolicy` with `maxDepth` and `summaryMaxChars` limits
- **State injection** — `CanAccessAgentState` trait allows tools to read agent state

### Key Differences

| Aspect | Pi Mono | Instructor PHP |
|--------|---------|----------------|
| Process isolation | Via `mom` package (child_process) | None |
| Cooperative cancellation | AbortController/AbortSignal throughout | No equivalent |
| Subagent depth limiting | No built-in limit | `SubagentPolicy.maxDepth` (default 3) |
| Tool state access | Via closure captures | Via `CanAccessAgentState` interface |

---

## 5. Subagent Support

### Pi Mono

No first-class subagent abstraction. Subagent patterns achieved through:

1. **Multiple Agent instances** — lightweight, each with own state/tools/events
2. **Mom supervisor** — process-level sandboxing via `child_process.spawn()`
3. **SDK custom tools** — extensions spawn sub-conversations via new Agent instances

### Instructor PHP

Subagents are a first-class concept in the `AgentBuilder` layer:

1. **`SubagentPolicy`** — configurable depth limit + summary character limit
2. **`AgentDefinitionProvider`** — interface for discovering available subagent definitions
3. **`SubagentSpawning` / `SubagentCompleted` events** — lifecycle events for subagent execution
4. **`AgentBlueprint` / `AgentBlueprintRegistry`** — template system for defining reusable agent configurations
5. **`AgentCapability` pattern** — capabilities (tools, subagents, summarization) install into `AgentBuilder`

### Key Differences

| Aspect | Pi Mono | Instructor PHP |
|--------|---------|----------------|
| Subagent abstraction | Ad-hoc (multiple instances) | First-class (SubagentPolicy, events, registry) |
| Agent templates | None | AgentBlueprint + AgentBlueprintRegistry |
| Depth limiting | None | Configurable `maxDepth` |
| Subagent events | None at core level | SubagentSpawning, SubagentCompleted |

---

## 6. Tool System

### Pi Mono — Schema-Validated Tools

```typescript
interface AgentTool<TParams extends TSchema, TDetails> extends Tool<TParams> {
  name: string;
  description: string;
  label: string;
  parameters: TSchema;     // TypeBox schema
  execute: (toolCallId, params, signal?, onUpdate?) => Promise<AgentToolResult<TDetails>>;
}
```

- **TypeBox schemas** for parameter validation at the boundary
- **Progress callbacks** (`onUpdate`) for streaming tool progress
- **AbortSignal** propagated to every tool
- **Factory functions** (`createReadTool(cwd)`, etc.) for built-in tools
- **Two-level registry**: all tools vs active tools (subset sent to LLM)
- **Extension wrapping**: `wrapToolsWithExtensions()` intercepts all tool calls/results

### Instructor PHP — Interface-Based Tools

```php
interface ToolInterface {
    public function use(mixed ...$args): Result;
    public function toToolSchema(): array;
    public function metadata(): array;
    public function name(): string;
    public function description(): string;
    public function instructions(): array;
}
```

- **Result type** — tools return `Result` (Success/Failure), not exceptions
- **No schema validation** at the interface level — validation in `ToolExecutor` checks required params against schema
- **No progress callbacks** — tools run synchronously, no streaming updates
- **No AbortSignal** — tools cannot be cancelled mid-execution
- **`CanAccessAgentState`** trait — tools can opt into reading agent state
- **Immutable `Tools` collection** — `withTools()`, `withToolRemoved()` return new instances
- **`ToolPolicy`** — per-capability tool configuration
- **`FunctionTool`** — wraps PHP callables as tools automatically
- **`MockTool`** — scripted tool for testing

### Tool Execution

| Aspect | Pi Mono | Instructor PHP |
|--------|---------|----------------|
| Execution order | Sequential, with steering check after each | Sequential, with hook intercept before/after each |
| Result type | `AgentToolResult<TDetails>` (content + details) | `Result` (Success/Failure monadic type) |
| Error handling | Try/catch → error result to LLM | Try/catch → `ToolExecutionException` → `Failure` result |
| Tool blocking | Extension wrapping can skip tools | `HookContext.withToolExecutionBlocked()` |
| Argument validation | TypeBox schema validation | Required-field check against tool schema |
| Progress streaming | `onUpdate` callback per tool | None |

---

## 7. Extension Points / Hooks

### Pi Mono — Event-Based Extension System

Extensions are JavaScript modules discovered from filesystem:

- **`ExtensionFactory`**: `(api: ExtensionAPI) => Extension`
- **Rich API**: registerCommand, registerTool, on(event), sendMessage, setModel, setActiveTools, registerWidget, registerShortcut, registerFlag, compact, exec
- **20+ event types**: agent lifecycle, tool calls, session events, input transformation, context transformation, resource discovery
- **Tool wrapping**: `wrapToolsWithExtensions()` wraps every tool for interception
- **Skills**: Markdown files with YAML frontmatter, loaded as prompt snippets

### Instructor PHP — Hook Interceptor Pattern

Hooks are objects implementing `HookInterface`, registered with trigger types and priorities:

- **`HookInterface`**: `handle(HookContext): HookContext`
- **8 trigger types**: BeforeExecution, BeforeStep, BeforeToolUse, AfterToolUse, AfterStep, OnStop, AfterExecution, OnError
- **`HookStack`** — chain of hooks with priority ordering
- **`HookContext`** — rich context object carrying state, tool call, execution, errors; hooks can modify state, block tools, add errors
- **Capability pattern**: `AgentCapability.install(AgentBuilder)` — composable features that install hooks and tools
- **Built-in hooks**: `FinishReasonHook`, `ApplyContextConfigHook`, `CallableHook`
- **Guard hooks**: `StepsLimitHook`, `TokenUsageLimitHook`, `ExecutionTimeLimitHook`
- **Separate event system**: `AgentEventEmitter` with `wiretap()` and typed event classes

### Key Differences

| Aspect | Pi Mono | Instructor PHP |
|--------|---------|----------------|
| Extension model | JavaScript modules from filesystem | PHP classes implementing HookInterface |
| Discovery | Filesystem scanning (~/.pi/agent/extensions/) | Programmatic registration via AgentBuilder |
| Composition | Flat event handlers | Capability pattern (install into builder) |
| Hook priority | Event order (per event type) | Numeric priority on HookStack |
| Tool interception | Wrap-based (before/after every tool) | HookContext with BeforeToolUse/AfterToolUse triggers |
| State mutation | Extensions call API methods imperatively | Hooks return modified HookContext immutably |
| Built-in guards | None (manual in extensions) | StepsLimit, TokenUsageLimit, ExecutionTimeLimit |
| UI extension | Widgets, shortcuts, keyboard bindings | None |

---

## 8. Observability

### Pi Mono

- **Agent events**: `AgentEvent` union type with ~10 event types (agent_start/end, turn_start/end, message_start/update/end, tool_execution_start/update/end)
- **AgentSession events**: Extended with auto_compaction, auto_retry events
- **EventBus**: Simple EventEmitter pub/sub
- **EventStream**: Push-based async iterable for streaming
- **Usage tracking**: Per-message usage data (input/output/cache tokens + cost)
- **No OpenTelemetry** integration
- **Diagnostics**: Resource loading warnings

### Instructor PHP

- **24+ typed event classes**: `AgentExecutionStarted`, `AgentStepCompleted`, `ToolCallStarted`, `ToolCallCompleted`, `ToolCallBlocked`, `StopSignalReceived`, `InferenceRequestStarted`, `InferenceResponseReceived`, `SubagentSpawning`, `SubagentCompleted`, `ContinuationEvaluated`, `ValidationFailed`, etc.
- **`AgentEventEmitter`**: Typed emitter with `wiretap()` for all events and `onEvent()` for specific classes
- **Broadcasting**: `AgentEventBroadcaster` for multi-destination event distribution
- **Usage tracking**: Per-step usage via `InferenceResponse.usage()`, aggregated via `StepExecutions.totalUsage()`
- **No OpenTelemetry** integration
- **Event hierarchy**: Extends from `cognesy/events` package (shared infrastructure)

### Key Differences

| Aspect | Pi Mono | Instructor PHP |
|--------|---------|----------------|
| Event types | Union type (~10 variants) | 24+ typed classes with typed payloads |
| Streaming events | Native (message_update, tool_execution_update) | None (no streaming) |
| Event filtering | By type field in union | By class (`onEvent(ClassName, handler)`) |
| Broadcasting | Single listener set | `AgentEventBroadcaster` for multi-target |
| Usage aggregation | Per-message | Per-step + execution-level totals |

---

## 9. Interrupt Mechanism

### Pi Mono — AbortController + Steering

Multi-level cooperative cancellation:

1. **`agent.abort()`** → signals `AbortController`
2. **Agent loop** checks signal after streaming
3. **Tools** receive `AbortSignal`, can cancel mid-execution
4. **Steering messages** — soft interrupt: skip remaining tools, inject message
5. **AgentSession** — dedicated abort controllers for compaction, retry, bash

### Instructor PHP — StopSignal + Hooks

Exception-based stopping with hook overrides:

1. **`AgentStopException`** → caught in loop, converted to `StopSignal`
2. **`StopSignal`** with typed `StopReason` (Completed, StepsLimit, TokenLimit, TimeLimit, ErrorForbade, UserRequested, etc.)
3. **`ExecutionContinuation`** — balances stop signals against continuation requests
4. **Guard hooks** throw `AgentStopException` when limits exceeded
5. **Continuation request** — hooks can override stop by setting `isContinuationRequested`
6. **`shouldStop()` evaluation**: stops if StopSignals present AND no continuation requested AND no pending tool calls

### Key Differences

| Aspect | Pi Mono | Instructor PHP |
|--------|---------|----------------|
| Cancellation model | Cooperative (AbortController/AbortSignal) | Exception-based (AgentStopException) |
| Mid-tool cancel | Yes (AbortSignal propagated to tools) | No (PHP lacks cooperative cancellation) |
| Soft interrupt | Steering messages (skip remaining tools) | Continuation requests override stop signals |
| Stop reasons | Implicit (abort vs error vs normal end) | Typed enum: 10 StopReason variants with priority |
| Stop signal priority | N/A | Prioritized (Error > StopRequested > StepsLimit > ... > Completed) |

---

## 10. Step-by-Step Execution

### Pi Mono

- **No step-level pause** at the agent loop level
- Loop runs to completion (or abort) per prompt
- External control via:
  - Steering messages (redirect mid-execution)
  - Follow-up messages (chain after completion)
  - RPC mode (JSON-RPC for programmatic control)
  - Print mode (single prompt, non-interactive)

### Instructor PHP

- **Generator-based step iteration** via `iterate()`:
  ```php
  foreach ($loop->iterate($state) as $stepState) {
      // Process each step, inspect state, decide to break
  }
  ```
- **`execute()` method** for run-to-completion
- **Hook-based control**: `OnStop` hook can set continuation to override stopping
- **AgentStep snapshot**: Each step captures input messages, inference response, tool executions, errors

### Key Differences

| Aspect | Pi Mono | Instructor PHP |
|--------|---------|----------------|
| Step iteration | Not built-in | `iterate()` → PHP generator yields each step |
| Pause/resume | Via abort + re-prompt | Natural with generator: caller controls loop |
| Step inspection | Via events only | Full `AgentStep` snapshot (input, output, tools, errors) |
| External control | RPC mode, steering, follow-ups | Direct programmatic control via iteration |

---

## 11. Serialization / Deserialization

### Pi Mono

- **JSONL session files**: Append-only, line-per-entry, tree structure
- **Version migration**: v1→v2→v3 on load
- **JSON settings**: Global + project-local settings files
- **Auth storage**: Encrypted API key storage
- **Model registry**: Built-in + user-defined model configs

### Instructor PHP

- **Array-based serialization**: Every value object has `toArray()` / `fromArray()`
- **No prescribed file format**: Framework doesn't dictate storage
- **Complete state round-trip**: AgentState → AgentContext → ExecutionState → StepExecutions → AgentStep → ToolExecution all serializable
- **Config presets**: `ConfigPresets` system for named configurations

### Key Differences

| Aspect | Pi Mono | Instructor PHP |
|--------|---------|----------------|
| Format | JSONL (append-only, tree) | `toArray()`/`fromArray()` (storage-agnostic) |
| Persistence | Built into SessionManager | Bring-your-own storage |
| Migration | Version-based migration system | None (no format to migrate) |
| Scope | Session + settings + auth + models | Agent state only |

---

## 12. Error Handling

### Pi Mono — Layered with Auto-Recovery

```
LLM error → Agent loop (catches, emits agent_end)
  → AgentSession (auto-retry for transient, auto-compact for overflow)
    → UI (displays to user)
```

- **Auto-retry**: Exponential backoff for 429, 5xx, rate limits, connection errors
- **Auto-compaction**: On context overflow, compact then retry
- **Tool errors**: Caught and returned to LLM as error results (self-healing)
- **Extension error isolation**: Extension failures don't crash the agent
- **Dynamic API key resolution**: Per-request key resolution for OAuth token refresh

### Instructor PHP — Result Type + Hook-Based Guards

```
Tool error → Result.Failure → returned to loop
  → ToolExecution with error → included in AgentStep
    → Hooks can intercept via OnError trigger
```

- **Result monadic type**: `Result` wraps success/failure; tools return Result, not throw
- **Guard hooks**: `StepsLimitHook`, `TokenUsageLimitHook`, `ExecutionTimeLimitHook` throw `AgentStopException`
- **Tool errors**: Wrapped in `ToolExecutionException` → `Failure` result; optionally thrown if `throwOnToolFailure` enabled
- **Tool blocking**: `beforeToolUse` hook can block execution → `ToolExecution.blocked()` result
- **No auto-retry** at agent level (retry policy exists at inference/polyglot level via `InferenceRetryPolicy`)
- **No auto-compaction** — summarization is a capability (`UseSummarization`) that runs as AfterStep hooks

### Key Differences

| Aspect | Pi Mono | Instructor PHP |
|--------|---------|----------------|
| Error model | Exceptions + error messages | Result monadic type (Success/Failure) |
| Auto-retry | Built-in (exponential backoff, transient detection) | At polyglot/inference level only |
| Context overflow | Auto-compaction + retry | Summarization capability (preventive, not reactive) |
| Tool error recovery | Error result sent to LLM | Error result in step; LLM can react |
| Resource guards | None built-in | Steps, token, and time limit hooks |
| Error visibility | Error messages in conversation + events | ErrorList in state + events |

---

## 13. LLM Provider Abstraction

### Pi Mono (`packages/ai`)

- **`Model<Provider>` type**: Typed model definitions with provider info
- **Provider drivers**: Stream-based, returning `AssistantMessageEventStream`
- **Cost tracking**: Per-model pricing for input/output/cache tokens
- **`streamFn` abstraction**: The agent receives a function `(model, context, options) → EventStream`
- **Proxy support**: `streamProxy()` for remote LLM access

### Instructor PHP (`packages/polyglot`)

- **`LLMProvider` class**: Factory for inference drivers
- **Driver architecture**: Per-provider drivers (Anthropic, OpenAI, Gemini, Azure, Cohere, XAI, Meta, etc.)
  - Each driver has: `BodyFormat`, `MessageFormat`, `RequestAdapter`, `UsageFormat`
- **`Inference` facade**: Fluent builder → `PendingInference` → `InferenceResponse`
- **`DriverCapabilities`**: Capability querying (streaming, tool calling, JSON schema, response format with tools)
- **Output modes**: Tools, Json, JsonSchema, MdJson, Text, Unrestricted
- **`InferenceRetryPolicy`**: Configurable retry at the inference level
- **Cached context**: `CachedInferenceContext` for prompt caching (system prompt + tools)
- **Cost tracking**: `Pricing` with per-model token costs
- **Embeddings**: Full embeddings API (not in Pi Mono scope)

### Key Differences

| Aspect | Pi Mono | Instructor PHP |
|--------|---------|----------------|
| Provider count | ~5 (via `pi-ai`) | 15+ (Anthropic, OpenAI, Azure, Gemini, Cohere, XAI, Meta, Deepseek, Groq, Fireworks, etc.) |
| Streaming | Native streaming throughout | Streaming at inference level but not propagated to agent loop |
| Output modes | Streaming tool calls only | 6 modes (Tools, Json, JsonSchema, MdJson, Text, Unrestricted) |
| Structured output | Not at this layer (agents handle tools) | `StructuredOutput` facade for schema-validated extraction |
| Embeddings | Not included | Full embeddings API with driver support |
| Retry | At AgentSession level | At Inference level (`InferenceRetryPolicy`) |

---

## 14. Structured Output

### Pi Mono

No built-in structured output extraction. The system uses native tool/function calling exclusively — the LLM returns tool calls, and the agent loop dispatches them.

### Instructor PHP (`packages/instructor`)

A comprehensive **structured output extraction** system:

- **`StructuredOutput` facade**: Fluent builder for extraction requests
- **Schema generation**: PHP class → JSON Schema (via reflection + attributes)
- **Output modes**: Tool calling, JSON mode, JSON Schema mode, Markdown JSON, Text
- **Validation + retry**: `CanValidateSelf`, `CanValidateObject`, configurable max retries with retry prompts
- **Deserialization**: Class hydration via Symfony Serializer (`CanDeserializeClass`, `CanDeserializeSelf`)
- **Transformation**: `CanTransformSelf`, `CanTransformData` for post-extraction transforms
- **Streaming partials**: `ModularUpdateGenerator` for streaming partial object updates
- **Sequences**: `Sequence` type for streaming arrays of objects
- **Config presets**: `StructuredOutputConfig` + builder pattern with presets

This is unique to Instructor PHP and has no equivalent in Pi Mono.

---

## 15. Architectural Philosophy

### Pi Mono — Pragmatic, Streaming-First, Application-Specific

- **Designed for a specific product** (coding agent CLI)
- **Streaming is a first-class citizen** — events flow through the entire stack
- **Mutable, practical state** — direct manipulation over immutability
- **Extension-first customization** — filesystem-based plugin system
- **Session-centric persistence** — append-only JSONL with tree structure for branching/rewind
- **Process-level isolation** via supervisor pattern (`mom`)
- **TypeScript idioms**: declaration merging, async iterables, AbortController

### Instructor PHP — Framework, Immutable, Composable

- **Designed as a generic framework** for building diverse agents
- **Immutability throughout** — every state object is `readonly` with `with()` cloning
- **Driver pattern** for LLM interaction strategy (ToolCalling vs ReAct vs custom)
- **Capability pattern** for composable features (summarization, tasks, structured output, subagents)
- **Hook/interceptor pipeline** for cross-cutting concerns with priority ordering
- **Rich type system** — typed events, Result monadic type, value objects for everything
- **Storage-agnostic** — complete serialization but no prescribed persistence
- **PHP idioms**: interfaces, readonly classes, enums with priorities, constructor promotion

---

## Summary Matrix

| Concern | Pi Mono | Instructor PHP |
|---------|---------|----------------|
| **Loop type** | Dual-loop (tools + follow-ups) | Single-loop with stop signals |
| **Streaming** | Native throughout | None at agent level |
| **State mutability** | Mutable | Immutable (readonly + with()) |
| **Step iteration** | Events only | Generator-based yield |
| **Driver pluggability** | Single strategy | ToolCalling / ReAct / Fake |
| **Tool validation** | TypeBox schema | Required-field check |
| **Tool progress** | onUpdate callback | None |
| **Tool cancellation** | AbortSignal | None |
| **Extension model** | Filesystem JS modules | HookInterface + AgentCapability |
| **Stop mechanism** | AbortController | StopSignal + continuation |
| **Auto-retry** | Built-in (exponential backoff) | At inference level only |
| **Auto-compaction** | Built-in (reactive to overflow) | Capability (preventive summarization) |
| **Conversation branching** | Tree structure with fork/rewind | Not built-in |
| **Session persistence** | Append-only JSONL | Serializable (BYO storage) |
| **Structured output** | N/A (tool calls only) | Comprehensive (schema, validation, retry, deserialization) |
| **Subagent support** | Ad-hoc (multiple instances) | First-class (policy, events, registry) |
| **LLM providers** | ~5 | 15+ |
| **Error model** | Exceptions | Result monadic type |
| **Guard rails** | None built-in | Steps/token/time limit hooks |
