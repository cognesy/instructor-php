# InstructorPHP: Design Changes & Improvements Backlog

Comparative analysis of InstructorPHP's agentic framework against Pydantic AI and pi-mono. Focused on actionable design improvements organized by priority.

**Legend:** PAI = Pydantic AI, PM = pi-mono, IP = InstructorPHP

---

## Current Strengths (No Action Needed)

Before the backlog, it's worth noting where InstructorPHP is already ahead of both reference implementations:

1. **Immutable readonly state model** — `final readonly class` throughout (`AgentState`, `ExecutionState`, `AgentStep`, `HookContext`). PAI uses mutable `@dataclass`; PM uses mutable class fields. IP's approach is the most disciplined.

2. **Three-layer state separation** — `AgentState` (session) → `ExecutionState` (per-run) → `AgentStep` (per-iteration). PAI has `GraphAgentDeps` (immutable config) + `GraphAgentState` (mutable run state) but no per-step data object. PM mixes everything in `AgentState`.

3. **Budget propagation through subagent hierarchy** — `SpawnSubagentTool` computes `remaining budget = parent.budget.remainingFrom(execution).cappedBy(spec.limits)`. Neither PAI nor PM has hierarchical budget management.

4. **Full serialization** — `toArray()`/`fromArray()` on all state objects (`AgentState`, `ExecutionState`, `AgentStep`, `StepExecution`, `AgentContext`, `Budget`, `ExecutionContinuation`). PAI serializes messages only. PM persists via JSONL but with no structured state serialization.

5. **StopSignal priority system** — `StopReason` enum with numeric priorities (ErrorForbade=0 highest → Completed=8 lowest) for deterministic conflict resolution. Neither PAI nor PM has signal prioritization.

6. **HookStack with priority ordering** — Registered hooks execute by priority (higher first), with typed triggers. More structured than PM's extension callbacks or PAI's simple callback lists.

7. **Capability pattern** — `AgentCapability.install(AgentBuilder)` is a clean composition mechanism. PAI uses constructor parameters and decorator methods. PM has no equivalent.

8. **Sandbox package** — Built-in sandboxed execution (Docker, Podman, Firejail, Bubblewrap, Host) with execution policies. Neither PAI nor PM has this.

9. **Agent-ctrl bridges** — Bridge abstraction for external CLI agents (Claude Code, Codex, OpenCode). Unique to IP.

10. **Structured output extraction** — The `instructor` package with retry logic, extraction strategies (JSON, bracket matching, resilient parsing), validation, and deserialization pipeline is more mature than PAI's output handling.

---

## Backlog Items

### B-01: Composable Toolset Decorator Hierarchy

**Priority:** P0 | **Impact:** High | **Effort:** Medium

**Current state:** Tools are managed via a flat `Tools` collection and `ToolRegistry`. The `ToolRegistry` supports registration, search, and factory-based lazy loading, but the `Tools` object is a simple named collection with no composable transformations.

**What PAI does:** `AbstractToolset` hierarchy with composable decorators:
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
└── MCP toolsets         — MCPServerStdio, MCPServerHTTP
```

Composable: `toolset.filtered(...).prefixed("sub_").approval_required()`.

**What PM does:** Flat `AgentTool[]` array. No composition.

**Gap in IP:** No dynamic per-step tool filtering, no namespacing for subagent tool inheritance, no approval/deferred patterns at the tool level (only via hooks). The `SpawnSubagentTool.filterTools()` manually handles allow/deny lists per `AgentDefinition`, but this is subagent-specific, not a general composable pattern.

**Recommendation:**
- Define a `Toolset` interface with `getToolSchemas(): array` and `executeTool(string $name, array $args, AgentState $state): Result`
- Implement: `FunctionToolset`, `CombinedToolset`, `FilteredToolset` (per-step filtering via callback), `PrefixedToolset`, `ApprovalRequiredToolset`
- `Tools` becomes one implementation; `ToolExecutor` delegates to `Toolset` instead of `Tools`
- `SpawnSubagentTool`'s tool filtering logic migrates to composable `FilteredToolset`
- MCP integration becomes a `MCPToolset` implementation

**Key files affected:** `ToolExecutor.php`, `Tools.php` (collection), `AgentLoop.php` (tool schema passing), `AgentBuilder.php` (builder API)

---

### B-02: Per-Tool Retry with Model-Directed Correction

**Priority:** P0 | **Impact:** High | **Effort:** Medium

**Current state:** Tool execution captures failures as `Result::failure()` in `ToolExecution`. The failure is reported back to the LLM as a tool result. There is no per-tool retry counting, no mechanism for tools to explicitly request the model to try again with different arguments, and no per-tool retry budget.

The `StructuredOutput` package has retry logic (`maxRetries`, `RetryPromptPart`-equivalent via retry messages), but this is for structured output extraction, not for agentic tool calls.

**What PAI does:**
- Per-tool retry counting in `ToolManager` (tracks `retries: dict[str, int]`)
- `ModelRetry("try with different args")` exception in tools → converted to `RetryPromptPart` sent to model
- Per-tool `max_retries` (configurable per tool registration)
- Exceeding retries raises `ToolRetryError`
- Automatic validation error → retry (Pydantic validation errors become retry prompts)

**What PM does:** No per-tool retry. Errors go back to LLM as `isError: true` tool results.

**Gap in IP:** `ToolExecutor.validateArgs()` returns `Result::failure(InvalidToolArgumentsException)` but this failure is just stored — it's not converted into a structured retry prompt that teaches the model what went wrong. The `handleFailure()` method either throws or silently continues based on `throwOnToolFailure`. There's no retry loop around individual tool calls.

**Recommendation:**
- Add `maxRetries` to `ToolInterface` metadata (default: 1)
- Add `ToolRetryException` that tools can throw to request re-invocation
- Track per-tool retry count in `ToolExecutor` or `ExecutionState`
- When validation fails or tool throws `ToolRetryException`, build a structured retry message (include error details, argument schema, what went wrong) and re-request from the model
- Cap retries at the per-tool limit; on exhaustion, record as `ToolExecution::failed()`

**Key files affected:** `ToolExecutor.php`, `ToolInterface.php`, `ToolCallingDriver.php` (builds follow-up messages), `AgentStep.php`

---

### B-03: Parallel Tool Execution

**Priority:** P1 | **Impact:** High | **Effort:** Low

**Current state:** `ToolExecutor.executeTools()` iterates tool calls with `foreach ($toolCalls->each() as $toolCall)` — strictly sequential. When the LLM returns 3+ independent tool calls (e.g., read 3 files), they execute one-at-a-time.

**What PAI does:** Three modes:
- `sequential` — one at a time
- `parallel_ordered_events` — all at once via `asyncio.create_task`, events emitted in call order
- `parallel_unordered_events` — all at once, events as completed

Configurable per-toolset via `parallel_execution_mode`.

**What PM does:** Sequential only (`for` loop in `executeToolCalls()`).

**Gap in IP:** No parallel execution. PHP doesn't have native async like Python's asyncio, but `pcntl_fork`, `proc_open`, `Fiber`, or ReactPHP/Swoole/Amp could provide concurrency. Alternatively, a `ParallelToolExecutor` could use `proc_open` for process-based parallelism for I/O-bound tools.

**Recommendation:**
- Add `ExecutionMode` enum: `Sequential | Parallel`
- Allow tools to declare `executionMode()` (default: `Parallel`)
- Tools that modify shared state (bash, file writes) can opt into `Sequential`
- Implement `ParallelToolExecutor` using either:
  - `Fiber`-based cooperative concurrency (simplest)
  - `proc_open` for process-based parallelism (most robust for I/O tools)
  - Or simply `array_map` + `curl_multi_exec` for HTTP-only tools
- Maintain hook interception order (before/after hooks run sequentially even if tools run in parallel)

**Key files affected:** `ToolExecutor.php`, `ToolInterface.php` (add `executionMode()`), `AgentBuilder.php`

---

### B-04: Human-in-the-Loop / Deferred Tool Execution

**Priority:** P1 | **Impact:** High | **Effort:** Medium

**Current state:** The `beforeToolUse` hook can block tool execution via `$hookContext->withToolExecutionBlocked()`. When `stopOnToolBlock` is true, the entire tool execution loop breaks. This provides basic gating but no structured pause/resume pattern.

**What PAI does:**
- `ApprovalRequired(metadata={...})` exception in tool → run pauses
- Run completes with `DeferredToolRequests` as output type
- Consumer reviews pending tool calls externally
- New run with `deferred_tool_results=DeferredToolResults(approvals={tool_call_id: "approved"/"denied"})` resumes execution
- `DeferredToolset` wraps any toolset with this pattern

**What PM does:** Steering mechanism can interrupt mid-run. Extension system can wrap tool execution with approval UI. But no structured pause/resume.

**Gap in IP:** Hook-based blocking is "block and fail" — there's no "block, pause, get approval, resume" pattern. The serializable state model (`toArray()`/`fromArray()`) is perfectly positioned for this but no mechanism exists to yield pending approvals to the caller and resume after.

**Recommendation:**
- Add `ToolApprovalRequired` exception (similar to `AgentStopException` but with pending tool calls)
- Add `DeferredToolExecution` data class: `{ toolCall, metadata, status: pending|approved|denied }`
- When `iterate()` encounters approval-required tools, yield a special state with `status: AwaitingApproval`
- Consumer calls `$state->withDeferredToolResults([toolCallId => 'approved'])` and resumes
- This builds naturally on the existing serialization infrastructure

**Key files affected:** `ToolExecutor.php`, `AgentLoop.php` (iterate method), `ExecutionState.php`, new `DeferredToolExecution.php`

---

### B-05: Dynamic Per-Step Tool Filtering

**Priority:** P1 | **Impact:** Medium | **Effort:** Low

**Current state:** Tools are fixed at build time via `AgentBuilder.build()`. The built `AgentLoop` receives an immutable `Tools` collection. There's no mechanism to add/remove/filter tools between steps based on conversation context.

**What PAI does:**
- `PreparedToolset` — runs a `prepare` function before each model request
- `ToolsPrepareFunc` — callback that receives `RunContext` and tool definitions, can modify/filter/add tools per-step
- `FilteredToolset` — wraps a toolset with a filter function

**What PM does:** `AgentSession._buildRuntime()` rebuilds tools per-session, but not per-step.

**Gap in IP:** The `beforeStep` hook receives `HookContext` with state but cannot modify the tool list. Tools are immutable after build.

**Recommendation:**
- Add `CanPrepareTools` hook trigger (runs before inference, after `beforeStep`)
- The hook receives current `Tools` + `AgentState` and returns a (potentially filtered) `Tools`
- This allows: progressive tool disclosure (simple tools first, advanced tools after the agent demonstrates competence), context-dependent tools (different tools for different conversation phases), cost optimization (exclude expensive tools when budget is low)

**Key files affected:** `AgentLoop.php` (add tool preparation step), `HookTrigger.php` (new trigger), `ToolCallingDriver.php` (accept dynamic tools)

---

### B-06: Session Branching and Tree-Structured History

**Priority:** P1 | **Impact:** High | **Effort:** High

**Current state:** `AgentContext` uses `MessageStore` with named sections (summary, buffer, messages, execution_buffer). Messages are flat within each section. `forNextExecution()` clears the execution buffer. There's no branching, no tree structure, no ability to fork a conversation and explore alternatives.

**What PAI does:** No session concept. Flat `list[ModelMessage]`. Forking is "copy the list."

**What PM does:** `SessionManager` with full tree structure:
- Every entry has `id`/`parentId`
- `branch(entryId)` — creates a new branch point
- `branchWithSummary()` — LLM-summarizes abandoned path
- `buildSessionContext()` — reconstructs linear context from tree by walking parent chain
- Labels, compaction, version migration
- JSONL append-only storage

**Gap in IP:** The serializable state model is well-suited for snapshots, but there's no tree structure or branching API. The `MessageStore` sections are a flat sequence.

**Recommendation:**
- Add `ConversationTree` that wraps `MessageStore` with tree semantics
- Each message gets an `id` and optional `parentId`
- `branch(messageId)` creates a new branch from that point
- `buildContext()` walks the parent chain to reconstruct linear history
- Integrate with existing `toArray()`/`fromArray()` for persistence
- The `UseSummarization` capability could use branching to summarize abandoned exploration paths
- This is a major architectural addition — consider implementing as a separate `Capability` first (`UseConversationBranching`) rather than modifying `AgentContext` core

**Key files affected:** New `ConversationTree.php`, `MessageStore.php` (optional tree support), `AgentContext.php`, new capability `UseConversationBranching.php`

---

### B-07: Mid-Run Message Injection (Steering)

**Priority:** P2 | **Impact:** Medium | **Effort:** Medium

**Current state:** The `iterate()` generator yields intermediate `AgentState` objects, allowing external observation. But there's no mechanism for the consumer to inject messages into the running loop (e.g., "stop what you're doing and focus on X" or "here's additional context").

**What PAI does:** No mid-run injection. The `iter()` API allows observation and manual node driving but not message injection.

**What PM does:** Two queues:
- **Steering queue** — `addSteeringMessage()` injects a message that interrupts the current tool execution cycle
- **Follow-up queue** — `addFollowUpMessage()` queues a message for after the current turn completes
- Steering can skip remaining tool calls (practical for human guidance)

**Gap in IP:** `iterate()` is read-only from the consumer's perspective. The hook system could theoretically modify state via `beforeStep`, but there's no standard API for external message injection.

**Recommendation:**
- Add `withSteeringMessage(Message)` and `withFollowUpMessage(Message)` to `AgentState`
- Store these in `ExecutionState` as pending queues
- The loop checks for steering messages after each tool execution; if present, it inserts the message and proceeds to the next inference call
- Follow-up messages are appended after the current step completes
- This pairs well with `iterate()` — the consumer yields a state, adds a steering message, and feeds it back

**Key files affected:** `ExecutionState.php` (add message queues), `AgentLoop.php` (check queues in loop), `AgentState.php` (convenience methods)

---

### B-08: OpenTelemetry Span Integration

**Priority:** P2 | **Impact:** Medium | **Effort:** Low

**Current state:** IP has a comprehensive event system (`AgentEventEmitter` with ~20 typed events) and a separate `metrics` package with collectors and exporters. But there are no OTel spans wrapping execution phases.

**What PAI does:**
- Agent run span (wraps entire execution)
- Model request spans (wraps each LLM API call)
- Tool execution spans (wraps each tool call)
- Structured attributes: model name, tool name, token counts, arguments, results
- `InstrumentationSettings` with version-aware naming

**What PM does:** No OTel integration.

**Gap in IP:** The events are dispatched but not structured as spans. The `metrics` package exports to custom exporters (Log, Callback, Null) but not to OTel.

**Recommendation:**
- Add optional `OTelTracer` that wraps existing events as spans
- Three span types: execution span, inference span, tool execution span
- Implement as a hook or event listener (not core loop changes)
- Use the existing event timestamps (`startedAt`, `completedAt` on `ToolExecution`, `StepExecution`)
- Could be a new `UseOpenTelemetry` capability that registers hooks to create spans
- Leverage `CanEmitAgentEvents` to decorate events with trace context

**Key files affected:** New `UseOpenTelemetry.php` capability, new `OTelSpanHook.php`, optionally new package `packages/otel/`

---

### B-09: MCP (Model Context Protocol) Integration

**Priority:** P2 | **Impact:** High | **Effort:** Medium

**Current state:** No MCP support. Tools are defined as PHP classes implementing `ToolInterface` or as callables via `FunctionTool`.

**What PAI does:** `MCPServerStdio`, `MCPServerHTTP`, `MCPServerSSE`, `MCPServerStreamableHTTP` — each wraps an MCP server as an `AbstractToolset`. Tools from MCP servers are automatically converted to tool definitions. Lifecycle management (connect/disconnect) integrated with agent's exit stack.

**What PM does:** No MCP integration in the core packages.

**Gap in IP:** The `agent-ctrl` package bridges to external CLI agents (Claude Code, Codex), but there's no generic MCP client that exposes external tools through the standard `ToolInterface`.

**Recommendation:**
- Implement `MCPToolset` that connects to an MCP server and exposes its tools as `ToolInterface` implementations
- Support transport types: stdio (process-based), HTTP/SSE, StreamableHTTP
- Leverage existing `Sandbox` package for process management
- Integrate as a capability: `UseMCPServer($serverConfig)`
- This would allow IP agents to use any MCP-compatible tool server (file system, database, browser, etc.)
- Consider whether the composable toolset pattern (B-01) should be in place first to make MCP integration cleaner

**Key files affected:** New package or capability, `ToolInterface.php` (possibly extend for MCP metadata)

---

### B-10: Composable Context Transforms (History Processors)

**Priority:** P2 | **Impact:** Medium | **Effort:** Medium

**Current state:** `AgentContext.messagesForInference()` assembles messages from sections in fixed order: summary → buffer → messages → execution_buffer. The `UseSummarization` capability implements one specific transform (LLM-powered summarization of old context). There's no general pipeline for context transforms.

**What PAI does:** `HistoryProcessor` — a callable that transforms `list[ModelMessage]` before each model request. Can be sync/async, with/without `RunContext`. Multiple processors compose in sequence.

**What PM does:** Two-stage pipeline: `transformContext()` → `convertToLlm()`. First modifies app-level messages (compaction, context window management), second converts to LLM format. Clean separation.

**Gap in IP:** Context transformation is hard-coded in `AgentContext` section ordering. Adding new transform strategies (sliding window, token-budget truncation, tool result summarization, semantic deduplication) requires modifying `AgentContext` or creating new capabilities that manipulate the `MessageStore` directly.

**Recommendation:**
- Add `ContextTransform` interface: `transform(Messages $messages, AgentState $state): Messages`
- Register transforms in `AgentBuilder` with priority ordering
- Run transforms in `AgentContext.messagesForInference()` after section assembly
- Built-in transforms: `SlidingWindowTransform(maxMessages)`, `TokenBudgetTransform(maxTokens, estimator)`, `ToolResultTruncationTransform(maxCharsPerResult)`, `SemanticDeduplicationTransform()`
- The existing `UseSummarization` becomes one implementation of `ContextTransform`

**Key files affected:** `AgentContext.php` (run transforms), `AgentBuilder.php` (register transforms), new `ContextTransform.php` interface, new transform implementations

---

### B-11: Agent Override for Testing

**Priority:** P2 | **Impact:** Medium | **Effort:** Low

**Current state:** `FakeAgentDriver` exists for testing, and tools have mock implementations (`MockTool`). But there's no mechanism to temporarily override an agent's configuration (model, tools, system prompt) in tests without rebuilding.

**What PAI does:** `agent.override()` context manager using `ContextVar`:
```python
with agent.override(model='test', deps=test_deps):
    result = await agent.run("test input")
```
Temporarily replaces model, deps, tools, instructions, metadata — isolated per async task.

**What PM does:** No override mechanism.

**Gap in IP:** Testing requires either rebuilding via `AgentBuilder` with test configuration, or using `FakeAgentDriver` globally. There's no scoped override.

**Recommendation:**
- Add `AgentLoop.withOverride(model?, tools?, systemPrompt?, budget?)` that returns a new `AgentLoop` with overridden configuration
- Since `AgentLoop` and all its components are immutable/readonly, this is simply constructing a new instance with replaced dependencies
- This is straightforward given the existing architecture — it's basically `AgentBuilder.build()` with selective overrides
- For integration tests: `$testLoop = $agentLoop->withOverride(driver: new FakeAgentDriver(...))`

**Key files affected:** `AgentLoop.php` (add `withOverride()` method)

---

### B-12: End Strategy (Early vs Exhaustive)

**Priority:** P3 | **Impact:** Low | **Effort:** Low

**Current state:** The `shouldStop()` logic in `ExecutionState` checks: stop signals → continuation requested → has tool calls. If a final response is detected, the loop stops. There's no concept of "exhaustive" mode where remaining tool calls execute even after a result is available.

**What PAI does:** `EndStrategy = Literal['early', 'exhaustive']`:
- `early` (default): Once valid output tool result is found, remaining function tools are skipped
- `exhaustive`: All function tools execute even after final result

**Gap in IP:** The `ToolCallingDriver` processes all tool calls but the `AgentLoop` stops when `shouldStop()` returns true. If the model returns both a final response and tool calls in the same response, the behavior depends on which the driver detects first.

**Recommendation:**
- Add `EndStrategy` enum to `AgentBuilder`: `Early | Exhaustive`
- In `Early` mode (default): stop after first final response detection
- In `Exhaustive` mode: execute all tool calls even if a final response is present, then stop
- Useful for agents that trigger side-effect tools (logging, metrics, cleanup) alongside their final answer

**Key files affected:** `AgentLoop.php`, `ExecutionState.php`, `AgentBuilder.php`

---

### B-13: Explicit App-to-LLM Message Boundary

**Priority:** P3 | **Impact:** Medium | **Effort:** Medium

**Current state:** `AgentContext.messagesForInference()` compiles sections and returns `Messages` that include system prompt injection. The `CachedInferenceContext` wraps messages + tools + response format. But the conversion from app-level messages to LLM-level messages is implicit — messages in the store are already in near-LLM format.

**What PM does:** Explicit two-stage pipeline:
- App messages (`AgentMessage[]`) are extensible via declaration merging (can include `BashExecutionMessage`, `CompactionSummaryMessage`, etc.)
- `convertToLlm()` explicitly converts each app message type to LLM-compatible `Message[]`
- This separation means app messages can carry richer structure than what the LLM sees

**What PAI does:** Messages are LLM-native (`ModelRequest`/`ModelResponse`). No app-level abstraction.

**Gap in IP:** The `Message` class in the messages package supports metadata and a parentId, but there's no explicit "app message type → LLM message" conversion step. If a tool produces structured results (e.g., file tree, bash output with exit code), these must be serialized to strings before storage.

**Recommendation:**
- Consider adding a `toLlmMessage()` method on `Message` or an `AppToLlmConverter` interface
- This would allow storing richer app-level message data (structured tool results, metadata, annotations) while converting to plain text/JSON for LLM consumption
- Benefits: better debugging (app messages preserve full structure), replay with different LLM formatting, custom rendering per LLM provider
- This is a philosophical design choice — evaluate whether the added complexity is justified by current use cases

**Key files affected:** `Message.php`, `AgentContext.php`, `Messages.php`

---

### B-14: Auto-Compaction as a Core Pattern

**Priority:** P3 | **Impact:** Medium | **Effort:** Low

**Current state:** `UseSummarization` capability exists and implements context summarization. But it must be explicitly installed and configured. There's no automatic compaction triggered by context window pressure.

**What PM does:** `AgentSession` has auto-compaction:
- Monitors context size
- When approaching context limit, automatically triggers LLM summarization of old messages
- Compacted messages replaced with a summary entry
- `firstKeptEntryId` marks the compaction boundary
- Transparent to the agent loop

**Recommendation:**
- Add `AutoCompactionHook` that monitors token usage in `beforeStep` trigger
- When `messagesForInference()` exceeds a configurable threshold, automatically invoke summarization
- Use the existing `UseSummarization` capability's summarizer
- Register at a low priority in the hook stack so it runs early
- This could be bundled into `UseSummarization` as an auto-trigger mode

**Key files affected:** `UseSummarization.php` (add auto-trigger), or new `AutoCompactionHook.php`

---

### B-15: Durable Execution Support

**Priority:** P3 | **Impact:** High | **Effort:** High

**Current state:** Full `toArray()`/`fromArray()` serialization exists on all state objects, making pause/resume technically possible. But there's no framework for durable execution (persist state on each step, resume from failure, at-least-once execution guarantees).

**What PAI does:** Optional integrations:
- Temporal (`durable_exec/temporal/`)
- DBOS (`durable_exec/dbos/`)
- Prefect (`durable_exec/prefect/`)
These wrap models, toolsets, and the agent to make execution resumable.

**Gap in IP:** The serialization infrastructure is ready but unused for durability. There's no checkpoint/resume pattern.

**Recommendation:**
- Add a `DurableExecutionHook` that serializes `AgentState` after each step
- Storage backend interface: `CanPersistAgentState` with `save(executionId, state)` and `load(executionId)`
- Implement backends: `FileDurableStore`, `DatabaseDurableStore`, `RedisDurableStore`
- `AgentLoop::resume(executionId, store)` loads state and continues from last checkpoint
- The existing `iterate()` generator naturally supports this — serialize after each `yield`, deserialize to resume
- This is the payoff of the immutable, serializable state design

**Key files affected:** New `DurableExecutionHook.php`, new `CanPersistAgentState.php` interface, `AgentLoop.php` (add `resume()`)

---

## Priority Matrix

| # | Item | Impact | Effort | Priority |
|---|------|--------|--------|----------|
| B-01 | **Composable toolset hierarchy** | High | Medium | **P0** |
| B-02 | **Per-tool retry with model correction** | High | Medium | **P0** |
| B-03 | **Parallel tool execution** | High | Low | **P1** |
| B-04 | **Human-in-the-loop / deferred tools** | High | Medium | **P1** |
| B-05 | **Dynamic per-step tool filtering** | Medium | Low | **P1** |
| B-06 | **Session branching / tree history** | High | High | **P1** |
| B-07 | **Mid-run message injection (steering)** | Medium | Medium | **P2** |
| B-08 | **OpenTelemetry span integration** | Medium | Low | **P2** |
| B-09 | **MCP integration** | High | Medium | **P2** |
| B-10 | **Composable context transforms** | Medium | Medium | **P2** |
| B-11 | **Agent override for testing** | Medium | Low | **P2** |
| B-12 | **End strategy (early/exhaustive)** | Low | Low | **P3** |
| B-13 | **Explicit app-to-LLM message boundary** | Medium | Medium | **P3** |
| B-14 | **Auto-compaction** | Medium | Low | **P3** |
| B-15 | **Durable execution support** | High | High | **P3** |

---

## Dependency Graph

```
B-01 (Composable Toolsets)
 ├── B-04 (Deferred/Approval) — ApprovalRequiredToolset decorator
 ├── B-05 (Per-step filtering) — FilteredToolset/PreparedToolset
 ├── B-09 (MCP) — MCPToolset implementation
 └── B-03 (Parallel execution) — per-toolset execution mode

B-02 (Per-tool retry) — independent

B-06 (Session branching)
 └── B-14 (Auto-compaction) — compaction uses branch summaries

B-07 (Steering) — independent, pairs with B-04

B-08 (OTel) — independent, leverages existing events

B-10 (Context transforms)
 └── B-14 (Auto-compaction) — compaction as a transform

B-15 (Durable execution)
 └── relies on existing serialization (already complete)
```

**Recommended implementation order:**
1. **B-01** (Composable Toolsets) — foundational, enables B-03, B-04, B-05, B-09
2. **B-02** (Per-tool retry) — independent, high value
3. **B-03** (Parallel execution) — quick win after B-01
4. **B-05** (Per-step filtering) — quick win after B-01
5. **B-11** (Testing override) — quick win, independent
6. **B-04** (Human-in-the-loop) — builds on B-01
7. **B-08** (OTel) — independent, low effort
8. **B-10** (Context transforms) — independent
9. **B-07** (Steering) — independent
10. **B-09** (MCP) — builds on B-01
11. **B-06** (Session branching) — large, plan carefully
12. **B-12**, **B-13**, **B-14** — P3, as needed
13. **B-15** (Durable execution) — when demand justifies
