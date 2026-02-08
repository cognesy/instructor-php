# Pydantic AI vs Instructor PHP — Architectural Comparison

## Overview

| | **Pydantic AI** | **Instructor PHP** |
|---|---|---|
| **Language** | Python 3.10+ | PHP 8.3+ |
| **Scope** | Agent framework with structured output | Structured output library + agent framework + LLM abstraction layer |
| **Package structure** | Monorepo: `pydantic-ai-slim`, `pydantic-graph`, `pydantic-evals`, `clai` | Monorepo: `polyglot` (LLM layer), `instructor` (structured output), `agents` (agent framework) + addons |
| **Design philosophy** | Lightweight, provider-agnostic, strongly-typed, Pydantic-native | Modular, event-driven, builder-pattern, interface-first |
| **Core typing strategy** | Generic type parameters (`AgentDepsT`, `OutputDataT`) + Pydantic dataclasses | PHP interfaces (`Can*` naming convention) + readonly classes |

The two frameworks address the same problem domain — building AI agents that interact with LLMs — but from different angles. Pydantic AI is a single cohesive framework where the agent is the central concept. Instructor PHP is a layered stack where each package can be used independently: `polyglot` handles LLM connectivity, `instructor` handles structured extraction, and `agents` provides the agent loop.

---

## 1. Layering & Separation of Concerns

### Pydantic AI — Vertically Integrated

The agent, model, tools, and output validation are tightly coupled within a single package. The `Agent` class owns the entire lifecycle:

```
Agent
 ├── Model (LLM abstraction)
 ├── Toolsets (tool discovery + execution)
 ├── OutputSchema (structured output validation)
 └── Graph (execution loop via pydantic_graph)
```

There is no separate "LLM client" package — `models/` is part of the agent framework. Structured output extraction is built into the agent loop's `CallToolsNode`, not a standalone concern.

### Instructor PHP — Horizontally Layered

Each layer is a separate Composer package with its own interfaces:

```
agents (cognesy/instructor-agents)
  ↓ uses
instructor (cognesy/instructor-struct)
  ↓ uses
polyglot (cognesy/instructor-polyglot)
  ↓ uses
cognesy/http, cognesy/events, cognesy/config
```

- **Polyglot** can be used standalone as an LLM client (no agent or structured output needed)
- **Instructor** can be used standalone for structured extraction (no agent loop needed)
- **Agents** composes both for agentic workflows

**Implication**: Instructor PHP allows incremental adoption — use just the LLM layer, add structured output later, then agent capabilities. Pydantic AI requires buying into the full framework even for simple LLM calls (though `FunctionModel`/`TestModel` help with testing).

---

## 2. Agent Loop

### Pydantic AI — Graph-Based State Machine

The loop is implemented as a typed graph via `pydantic_graph`:

```
UserPromptNode → ModelRequestNode → CallToolsNode → End | ModelRequestNode (retry)
```

Four node types with typed edges. The graph is constructed by `build_agent_graph()` using a `GraphBuilder`. Each node's `run()` method returns the next node, making the flow explicit and inspectable.

**Step-by-step execution** is first-class via `agent.iter()`:
```python
async with agent.iter("prompt") as run:
    async for node in run:
        # Inspect/modify nodes between steps
```

### Instructor PHP — Imperative Loop with Hook Interception

The loop in `AgentLoop.iterate()` is a `while`-based generator:

```
onBeforeExecution → [loop: onBeforeStep → handleToolUse → onAfterStep → shouldStop?] → onAfterExecution
```

The driver (`ToolCallingDriver` or `ReActDriver`) handles the LLM call + tool parsing. Hook triggers fire at each lifecycle point. There are no typed graph nodes — the loop is procedural with hook interception points.

**Step-by-step execution** uses PHP generators (`yield`):
```php
foreach ($loop->iterate($state) as $intermediateState) {
    // Inspect state between steps
}
```

### Comparison

| Aspect | Pydantic AI | Instructor PHP |
|--------|-------------|----------------|
| **Loop structure** | Typed graph (4 node types) | Imperative while-loop with hooks |
| **Step granularity** | Per-node (UserPrompt, ModelRequest, CallTools) | Per-iteration (inference + tool execution combined) |
| **Driving mechanism** | Async iterator over graph nodes | PHP generator yielding states |
| **Streaming within steps** | `node.stream()` context manager | Delegated to polyglot's `InferenceStream` |
| **Extensibility** | Replace/wrap graph nodes | Insert hooks at trigger points |
| **Testability** | `FunctionModel`/`TestModel` + node inspection | `FakeAgentDriver` + event assertions |

Pydantic AI's graph model provides finer-grained control (you can intercept between model request and tool execution), while Instructor PHP's hook system provides more interception points within each phase (before/after tool use, on error, on stop).

---

## 3. State & Context Model

### Pydantic AI — Ephemeral State, Generic Dependencies

State is split into two graph-level dataclasses:

- **`GraphAgentState`** (mutable): `message_history`, `usage`, `retries`, `run_step`, `run_id`, `metadata`
- **`GraphAgentDeps`** (immutable): `user_deps`, `model`, `model_settings`, `usage_limits`, `tool_manager`, `tracer`, etc.

The `Agent` itself is **stateless between runs**. All run state lives in `GraphAgentState`, created fresh per `run()`/`iter()` call.

Dependencies are typed via `AgentDepsT` generic parameter and accessed through `RunContext.deps`:
```python
Agent[MyDeps, str]  # Typed deps + typed output
```

Context isolation uses Python's `contextvars.ContextVar` for async-safe per-task isolation.

### Instructor PHP — Rich Hierarchical State

State has explicit session/execution/step hierarchy:

- **`AgentState`** (session-level): `agentId`, `context` (AgentContext), `execution` (ExecutionState)
  - **`ExecutionState`** (per-run): `executionId`, `status`, `stepExecutions`, `currentStep`, `continuation`
    - **`AgentStep`** (per-iteration): `inputMessages`, `inferenceResponse`, `toolExecutions`, `errors`
- **`AgentContext`** (conversation): Multi-section `MessageStore` (messages, buffer, summary, execution_buffer)

Tools access state via `CanAccessAgentState` interface — the `ToolExecutor` injects `AgentState` into tools that implement it:
```php
class MyTool extends BaseTool implements CanAccessAgentState {
    protected ?AgentState $agentState = null;
    // Injected automatically before tool execution
}
```

### Comparison

| Aspect | Pydantic AI | Instructor PHP |
|--------|-------------|----------------|
| **State hierarchy** | Flat (state + deps) | Nested (session → execution → step) |
| **State persistence** | Ephemeral; message history is the persistence unit | `AgentState` designed for persistence (IDs, timestamps, status) |
| **DI mechanism** | Generic type parameter + `RunContext` | Interface-based injection (`CanAccessAgentState`) |
| **Type safety** | Compile-time via generics | Runtime via interface contracts |
| **Context isolation** | `ContextVar` (async-safe) | Object references (single-threaded PHP) |
| **Multi-section messages** | Single flat list + `new_message_index` | Named sections (messages, buffer, summary, execution_buffer) |

Instructor PHP's hierarchical state model is more explicit about execution lifecycle, making it naturally suited for persistence, resumption, and observability. Pydantic AI's flat model is simpler but requires manual state management for long-running workflows (addressed by Temporal/DBOS integrations).

---

## 4. Conversation Data Model

### Pydantic AI — Discriminated Unions

Messages use Pydantic dataclasses with discriminator fields:

```python
ModelMessage = ModelRequest | ModelResponse  # Discriminated by 'kind'
ModelRequestPart = SystemPromptPart | UserPromptPart | ToolReturnPart | RetryPromptPart
ModelResponsePart = TextPart | ToolCallPart | ThinkingPart | FilePart | ...
```

Rich multimodal support: `ImageUrl`, `AudioUrl`, `VideoUrl`, `DocumentUrl`, `BinaryContent`.

Serialization via `ModelMessagesTypeAdapter`:
```python
json_bytes = ModelMessagesTypeAdapter.dump_json(messages)
messages = ModelMessagesTypeAdapter.validate_json(json_bytes)
```

### Instructor PHP — Role-Based Messages

Messages use a role-based model from the `cognesy/instructor-messages` package:
- `Messages` collection class
- Each message has a `role` (system/user/assistant/tool) and `content`
- The agent's `AgentContext` organizes messages into named sections

In the agent package, `RequestMaterializer` assembles messages from multiple sections with template support:
```
system → pre-cached → cached-prompt → messages → retries → ...
```

### Comparison

| Aspect | Pydantic AI | Instructor PHP |
|--------|-------------|----------------|
| **Message structure** | Part-based discriminated unions | Role-based with content |
| **Type safety** | Exhaustive via `assert_never()` | Interface-based |
| **Multimodal** | First-class (ImageUrl, AudioUrl, VideoUrl, etc.) | Via content arrays |
| **Serialization** | Pydantic TypeAdapter (JSON with base64 binary) | Array-based `toArray()`/`fromArray()` |
| **History management** | Flat list + `HistoryProcessor` callbacks | Multi-section `MessageStore` with summarization |
| **Forking/rewind** | Manual (pass truncated `message_history`) | Manual (snapshot `AgentState`) |
| **Caching** | `CachePoint` in message parts | `CachedInferenceContext` in request |

Pydantic AI's discriminated union approach provides stronger compile-time guarantees and cleaner pattern matching. Instructor PHP's section-based approach enables more sophisticated message management (summarization, buffering) out of the box.

---

## 5. LLM Abstraction

### Pydantic AI — Model ABC

All providers implement the `Model` abstract class:
```python
class Model(ABC):
    async def request(self, messages, model_settings, ...) -> ModelResponse
    async def request_stream(self, messages, model_settings, ...) -> StreamedResponse
```

Providers: OpenAI, Anthropic, Google, Bedrock, Mistral, Groq, Cohere, etc.
Special models: `FunctionModel`, `TestModel`, `FallbackModel`, `InstrumentedModel`.

String-based model selection: `Agent('openai:gpt-4o')`.

### Instructor PHP (Polyglot) — Driver + Adapter Pattern

```
Inference (facade) → LLMProvider (config) → InferenceDriverFactory → BaseInferenceDriver
                                                                       ├── RequestAdapter (→ HttpRequest)
                                                                       └── ResponseAdapter (→ InferenceResponse)
```

25+ drivers with explicit request/response adapter separation. Each driver declares its `capabilities()` (supported output modes, streaming, tool calling, JSON schema).

Preset-based configuration with DSN overrides:
```php
$inference = Inference::using('openai');
// or DSN-style:
$inference = Inference::dsn('anthropic://model=claude-sonnet-4-20250514');
```

### Comparison

| Aspect | Pydantic AI | Instructor PHP (Polyglot) |
|--------|-------------|--------------------------|
| **Abstraction** | Single `Model` ABC | Driver + RequestAdapter + ResponseAdapter |
| **Providers** | ~12 built-in | 25+ built-in |
| **Config** | String shorthand (`'openai:gpt-4o'`) | Presets + DSN strings |
| **Capability negotiation** | Implicit (per-provider behavior) | Explicit `capabilities()` method |
| **Fallback** | `FallbackModel` class | Not built into polyglot (handled at agent level) |
| **Testing** | `FunctionModel`/`TestModel` | `FakeAgentDriver` |
| **Standalone use** | Part of agent package | Independent package |
| **Cost tracking** | Via `Usage` in response | `Usage.calculateCost(Pricing)` with per-model pricing |

Polyglot's explicit adapter separation makes adding new providers more mechanical — implement two small adapter classes. Pydantic AI requires implementing the full `Model` interface. Polyglot's capability negotiation is also more explicit, allowing the framework to adapt behavior based on what each provider supports.

---

## 6. Structured Output / Output Validation

### Pydantic AI — Built Into the Agent Loop

Output validation is integral to the agent loop:
1. `OutputSchema` built from output type parameter
2. Model receives tool definitions for structured output (or text schema for text mode)
3. `CallToolsNode` validates output via Pydantic
4. Failed validation → `RetryPromptPart` sent back to model
5. Output validators (`@agent.output_validator`) for custom post-validation

```python
agent = Agent('openai:gpt-4o', output_type=MyModel)  # Pydantic model as output
```

### Instructor PHP — Dedicated Package

The `instructor` package is a standalone structured extraction pipeline:

```
StructuredOutput.create().get()
  → ResponseModel (schema)
  → RequestMaterializer (messages)
  → AttemptIterator (retry loop)
    → ResponseGenerator
      → Extract (5 extraction strategies)
      → Deserialize (Symfony)
      → Validate (Symfony)
      → Transform
```

Multiple extraction strategies tried in order:
1. `DirectJsonExtractor` — assume valid JSON
2. `ResilientJsonExtractor` — fix common JSON issues
3. `MarkdownBlockExtractor` — extract ```json blocks
4. `BracketMatchingExtractor` — find matching braces
5. `SmartBraceExtractor` — intelligent brace matching

Supports multiple output modes: `Tools`, `Json`, `MdJson`, `JsonSchema`, `Text`.

### Comparison

| Aspect | Pydantic AI | Instructor PHP |
|--------|-------------|----------------|
| **Coupling** | Built into agent loop | Standalone package, composable |
| **Schema source** | Python type annotations → Pydantic | PHP class/JSON schema → Symfony |
| **Extraction** | Direct (model returns structured) | Multi-strategy chain with fallbacks |
| **Output modes** | Tool calls or text | Tools, Json, MdJson, JsonSchema, Text |
| **Validation** | Pydantic validators + custom output validators | Symfony Validator + `CanValidateSelf` |
| **Deserialization** | Pydantic (built-in) | Symfony Serializer + custom deserializers |
| **Retry feedback** | `RetryPromptPart` with error details | Configurable retry prompt template |
| **Streaming partial** | `allow_partial='trailing-strings'` | `PartialJsonExtractor` + `ExtractingBuffer` |

Instructor PHP's extraction pipeline is significantly more robust for unreliable models — the fallback extractor chain handles malformed JSON, markdown-wrapped responses, etc. Pydantic AI relies more on the model producing correct output, with validation-and-retry as the recovery mechanism.

---

## 7. Tool System

### Pydantic AI — Composable Toolset Hierarchy

```
AbstractToolset (ABC)
├── FunctionToolset (wraps Python functions)
├── CombinedToolset (merges multiple)
├── FilteredToolset (.filtered())
├── PrefixedToolset (.prefixed())
├── RenamedToolset (.renamed())
├── PreparedToolset (.prepared())
├── ApprovalRequiredToolset (.approval_required())
├── DeferredToolset / ExternalToolset
├── DynamicToolset
└── MCP Servers (MCPServerStdio, MCPServerHTTP, MCPServerSSE)
```

Tools are registered via decorators or constructor. Schema generated from function signatures + type hints + docstrings. The `ToolManager` coordinates tool lookup, validation, and dispatch.

Tool kinds: `function`, `output`, `external`, `unapproved`.

### Instructor PHP — Interface-Based Tools

```
ToolInterface
└── BaseTool
    ├── __invoke() — concrete implementation
    ├── toToolSchema() — auto-generated from __invoke signature
    └── metadata() — discovery metadata
```

Tools registered via `AgentBuilder`:
```php
$builder->withTools([new MyTool(), new AnotherTool()])
```

`ToolExecutor` handles invocation with hook interception (BeforeToolUse, AfterToolUse can block or modify).

### Comparison

| Aspect | Pydantic AI | Instructor PHP |
|--------|-------------|----------------|
| **Registration** | Decorators + constructor + toolsets | Builder + capability system |
| **Schema generation** | From type hints + docstrings (Google/Sphinx/Numpy) | From `__invoke()` parameter types |
| **Composition** | Rich decorator chain (filter, prefix, rename, prepare) | Flat registry, capability-based |
| **MCP support** | First-class (Stdio, HTTP, SSE, StreamableHTTP) | Not in core (available in addons) |
| **Approval** | `ApprovalRequiredToolset` + `DeferredToolResults` | Hook-based (`BeforeToolUse` can block) |
| **Parallel execution** | 3 modes (parallel, sequential, parallel_ordered_events) | Sequential (PHP is single-threaded) |
| **State access** | Via `RunContext` parameter injection | Via `CanAccessAgentState` interface |
| **Tool kinds** | function/output/external/unapproved | Single kind (all are function tools) |

Pydantic AI's toolset composition is more powerful — you can dynamically filter, rename, and prepare tools per-step. Instructor PHP's approach is simpler but less flexible. The parallel execution support in Pydantic AI (via asyncio) has no equivalent in PHP due to language constraints.

---

## 8. Sub-Agent Support

### Pydantic AI — No First-Class Primitive

Agents compose via tool delegation:
```python
@agent.tool
async def delegate(ctx: RunContext[Deps], query: str) -> str:
    result = await sub_agent.run(query, deps=ctx.deps)
    return result.output
```

Also: `WrapperAgent`, `AbstractAgent` ABC, A2A protocol support.

### Instructor PHP — Built-In Capability

`UseSubagents` capability installs `SpawnSubagentTool`:
```php
$builder->withCapability(new UseSubagents(maxDepth: 3));
```

Features:
- Agent definitions loaded from `.md`/`.yaml` files
- Depth control prevents infinite recursion
- Tool filtering per sub-agent
- State collection from sub-agents
- Events bubble to parent

### Comparison

| Aspect | Pydantic AI | Instructor PHP |
|--------|-------------|----------------|
| **Mechanism** | Manual (tool-based delegation) | Built-in capability |
| **Depth control** | Manual | `maxDepth` policy |
| **Agent discovery** | Manual instantiation | Registry + definition files |
| **State propagation** | Via deps/context | `SubagentStateCollector` |
| **Protocol** | A2A (external) | Internal tool call |

Instructor PHP treats sub-agents as a first-class architectural concept. Pydantic AI leaves agent composition to the user, providing building blocks but no structured orchestration.

---

## 9. Event System & Observability

### Pydantic AI — OpenTelemetry Native

Observability is exclusively via OpenTelemetry:
- `InstrumentationSettings` wraps models with OTel spans
- Spans: agent run, model request, tool execution
- Events: all message parts implement `otel_event()` and `otel_message_parts()`
- Logfire integration for the Pydantic ecosystem
- No custom event bus — OTel is the only mechanism

```python
agent = Agent('openai:gpt-4o', instrument=True)
```

### Instructor PHP — Custom Event System + Hooks

Dual observability system:

**Events** (PSR-14 EventDispatcher):
- 30+ event types across all three packages
- `AgentExecutionStarted`, `ToolCallCompleted`, `InferenceResponseCreated`, `ContinuationEvaluated`, etc.
- Listeners registered via standard PSR-14

**Hooks** (lifecycle interceptors):
- Triggers: `BeforeExecution`, `BeforeStep`, `BeforeToolUse`, `AfterToolUse`, `AfterStep`, `OnStop`, `AfterExecution`, `OnError`
- `HookContext` carries state and allows modification
- Priority-based ordering
- Can block execution (e.g., prevent a tool call)

### Comparison

| Aspect | Pydantic AI | Instructor PHP |
|--------|-------------|----------------|
| **Primary mechanism** | OpenTelemetry spans + events | PSR-14 events + hook interceptors |
| **Standards compliance** | OTel GenAI spec (v1/v2/v3) | PSR-14 (PHP-FIG standard) |
| **Interception** | Read-only (observe spans) | Read-write (hooks can modify state, block calls) |
| **Event count** | ~10 OTel event types | 30+ custom event types |
| **Metrics** | `gen_ai.client.token.usage`, `operation.cost` | `TokenUsageReported` event |
| **External integration** | Logfire, any OTel backend | Any PSR-14 compatible listener |
| **Content control** | `include_content` / `include_binary_content` flags | Per-event data |

Pydantic AI bets on the OpenTelemetry ecosystem for observability, which provides mature tooling and standardized data. Instructor PHP's event + hook system is more flexible — hooks can actually modify execution, not just observe it — but lacks the standardized data format and ecosystem tooling.

---

## 10. Streaming

### Pydantic AI — Three-Layer Architecture

1. **Model layer**: Provider-specific `StreamedResponse`
2. **Agent layer**: `AgentStreamEvent` (PartStart, PartDelta, PartEnd, FinalResult, ToolCall, ToolResult)
3. **User layer**: `StreamedRunResult` with `.stream_text()`, `.stream_output()`, `.stream_responses()`

Debouncing support (default 0.1s) to reduce validation overhead during streaming.

### Instructor PHP — Generator-Based

Polyglot's `InferenceStream` yields `PartialInferenceResponse` with accumulation:
```php
foreach ($stream->responses() as $partial) {
    echo $partial->contentDelta;
}
```

Instructor's `StructuredOutputStream` adds structured extraction on top:
```php
foreach ($stream->partials() as $partialObject) {
    // Partially deserialized PHP object
}
```

Agent-level: `AgentEventBroadcaster` for UI delivery.

### Comparison

| Aspect | Pydantic AI | Instructor PHP |
|--------|-------------|----------------|
| **Architecture** | 3 layers (model → agent → user) | 3 layers (polyglot → instructor → agent) |
| **Iteration model** | Async iterators | PHP generators |
| **Partial validation** | `allow_partial='trailing-strings'` | `PartialJsonExtractor` in extractor chain |
| **Debouncing** | Built-in (configurable) | Not built-in |
| **Event granularity** | Part start/delta/end per response part | Content delta per response |
| **Caching** | Not built-in for streams | `ResponseCachePolicy` (Memory, Disk) |

Both provide layered streaming with partial structured output support. Instructor PHP adds response caching for streams, which is useful for re-iteration. Pydantic AI's event granularity is finer (per-part lifecycle events).

---

## 11. Error Handling & Retry

### Pydantic AI

```
Exception
├── ModelRetry              → RetryPromptPart (tool self-correction)
├── CallDeferred            → Pause for external execution
├── ApprovalRequired        → Pause for human approval
├── UserError               → Developer mistake
├── AgentRunError
│   ├── UsageLimitExceeded  → Token/request limits
│   ├── UnexpectedModelBehavior → Model misbehavior
│   │   ├── ContentFilterError
│   │   └── IncompleteToolCall
│   └── ModelAPIError → ModelHTTPError
└── FallbackExceptionGroup  → All fallback models failed
```

HTTP retries via `TenacityTransport` (exponential backoff, Retry-After support). Tool retries via per-tool `max_retries` counter. Output validation retries via `max_result_retries`.

### Instructor PHP

**Polyglot** (LLM layer):
```
ProviderException (abstract)
├── ProviderRateLimitException     (retriable)
├── ProviderTransientException     (retriable)
├── ProviderQuotaExceededException (not retriable)
├── ProviderAuthenticationException (not retriable)
└── ProviderInvalidRequestException (not retriable)
```

`ProviderErrorClassifier` maps HTTP status codes → exception types. `InferenceRetryPolicy` with exponential backoff + jitter + length recovery.

**Instructor** (extraction layer):
```
ExtractionException → DeserializationException → ValidationException
```
`DefaultRetryPolicy` with `shouldRetry()`/`recordFailure()`/`finalizeOrThrow()` lifecycle.

**Agent** layer:
```
AgentException, ToolExecutionException, SubagentDepthExceededException, ...
```

### Comparison

| Aspect | Pydantic AI | Instructor PHP |
|--------|-------------|----------------|
| **Error classification** | Exception hierarchy | `ProviderErrorClassifier` + exception hierarchy |
| **HTTP retry** | `TenacityTransport` | `InferenceRetryPolicy` (exponential backoff + jitter) |
| **Tool retry** | Per-tool `max_retries` via `ToolManager` | Hook-based (AfterToolUse) |
| **Output retry** | `max_result_retries` in agent loop | `maxRetries` in `StructuredOutputConfig` |
| **Length recovery** | Not built-in | `lengthRecovery: 'continue'|'increase_max_tokens'` |
| **Self-correction** | `ModelRetry` exception → `RetryPromptPart` | Validation errors → retry prompt template |
| **Retriability classification** | Implicit (exception types) | Explicit `isRetriable()` method |

Instructor PHP's error handling is more sophisticated at the LLM layer — explicit retriability classification, length recovery strategies, and jitter-based backoff. Pydantic AI's `ModelRetry` mechanism for tool self-correction is cleaner from the tool author's perspective.

---

## 12. Extension Points Summary

| Extension Point | Pydantic AI | Instructor PHP |
|-----------------|-------------|----------------|
| **Custom LLM provider** | Implement `Model` ABC | Implement driver + adapters, register via factory |
| **Custom tools** | Decorated functions or `AbstractToolset` | Extend `BaseTool` |
| **Tool composition** | `.filtered()`, `.prefixed()`, `.renamed()`, `.prepared()` | Capabilities + hook interception |
| **Output validation** | `@agent.output_validator` | `CanValidateObject` / `CanValidateSelf` |
| **History processing** | `HistoryProcessor` callbacks | `UseSummarization` capability + hooks |
| **System prompts** | `@agent.system_prompt` / `@agent.instructions` (dynamic) | `AgentDefinition` files + `ApplyContextConfigHook` |
| **Agent composition** | Manual tool delegation | `UseSubagents` capability |
| **Observability** | OpenTelemetry instrumentation | PSR-14 events + hook triggers |
| **Execution control** | Step-by-step via `agent.iter()` | Hook-based (`BeforeStep`, `AfterStep`, `OnStop`) |
| **Testing** | `FunctionModel`/`TestModel` | `FakeAgentDriver` |
| **Durable execution** | Temporal/DBOS/Prefect integrations | Not built-in (state model supports it) |

---

## 13. Key Architectural Differences

### What Pydantic AI Does Better

1. **Type safety**: Generic type parameters (`Agent[MyDeps, MyOutput]`) provide compile-time guarantees. Python's type system + Pydantic validators catch errors before runtime.

2. **Parallel tool execution**: `asyncio`-based parallel tool calls with three execution modes. PHP's single-threaded nature limits Instructor PHP to sequential execution.

3. **Toolset composition**: The decorator pattern (`.filtered().prefixed().renamed()`) enables powerful dynamic tool manipulation without modifying tool code.

4. **Step-by-step granularity**: Typed graph nodes allow inspection and modification at finer granularity (between model request and tool execution, not just between iterations).

5. **MCP integration**: First-class MCP server support (Stdio, HTTP, SSE, StreamableHTTP) as toolsets.

6. **Durable execution**: Built-in Temporal, DBOS, and Prefect integrations for fault-tolerant workflows.

### What Instructor PHP Does Better

1. **Package modularity**: Each layer usable independently. You can use `polyglot` as a standalone LLM client without the agent framework.

2. **Extraction robustness**: Five extraction strategies with fallback chain handle malformed LLM output gracefully. Pydantic AI relies on model compliance + retry.

3. **Sub-agent support**: Built-in capability with depth control, agent definitions from files, and state collection.

4. **Event system expressiveness**: 30+ event types + hooks that can modify execution (not just observe). Hooks can block tool calls, modify state, and control flow.

5. **State model richness**: Hierarchical state (session → execution → step) with named message sections and built-in summarization. Better suited for long-running conversations.

6. **Error classification**: Explicit retriability classification for provider errors + length recovery strategies.

7. **Provider coverage**: 25+ LLM drivers with explicit capability negotiation. Drivers declare what they support (tool calling, JSON schema, streaming, etc.).

8. **Agent definitions**: Declarative agent specs from `.md`/`.yaml` files with blueprint registry. Enables non-code agent configuration.

---

## 14. Philosophical Summary

**Pydantic AI** is a _framework-first_ design — the agent is the central abstraction, and everything (models, tools, output, observability) is designed to work within that abstraction. It leverages Python's async ecosystem and type system for maximum safety and performance. It favors composability through typed abstractions over configuration.

**Instructor PHP** is a _library-first_ design — each layer solves a specific problem independently, and they compose vertically. It favors flexibility and resilience (extraction fallbacks, hook interception, event-driven architecture) over strict typing. It treats the agent as one possible composition of lower-level primitives rather than the fundamental unit.

Both frameworks reflect their language ecosystems well:
- Pydantic AI leans into Python's `asyncio`, generics, `ContextVar`, and the Pydantic validation ecosystem
- Instructor PHP leans into PHP's interfaces, PSR standards, Symfony components, and builder patterns
