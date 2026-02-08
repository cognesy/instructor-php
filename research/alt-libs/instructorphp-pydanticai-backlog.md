# Instructor PHP — Design Improvement Backlog

Lessons learned from Pydantic AI architecture analysis, mapped to concrete improvements for the Instructor PHP agents package.

---

## 1. AGENT LOOP: From Imperative While-Loop to Typed State Machine

### Problem

`AgentLoop::iterate()` is an imperative `while(true)` loop with hook interception. The loop conflates three distinct phases — LLM request, tool execution, and continuation evaluation — into a single `handleToolUse()` call delegated to the driver. This creates several issues:

- **No finer-grained step control.** The caller gets one yield per iteration (inference + tool execution bundled). There's no way to intercept between the model response and tool execution.
- **`shouldStop()` is evaluated after the fact.** By the time `shouldStop()` is checked (line 74), all tools have already executed. You cannot abort before executing expensive tools.
- **Triple `withCurrentStepCompleted()` calls** (lines 76, 79, 83) create state consistency risks — if an exception occurs between paths, step completion semantics become ambiguous.
- **No pause/resume semantics.** The loop cannot be paused for external input (human-in-the-loop) and resumed. `AgentState` has no "paused pending input" status.

### What Pydantic AI Does

The agent loop is a typed graph with **four distinct node types**: `UserPromptNode → ModelRequestNode → CallToolsNode → End`. Each node executes independently and returns the next node. The caller can intercept between any two nodes:

```python
async with agent.iter("prompt") as run:
    async for node in run:
        if isinstance(node, ModelRequestNode):
            # Intercept BEFORE tool execution
            ...
        elif isinstance(node, CallToolsNode):
            # Inspect model response, potentially skip tools
            ...
```

The graph also supports deferred execution — tools can raise `CallDeferred` or `ApprovalRequired` to pause the loop, returning `DeferredToolRequests` as output. The run can be resumed later with `deferred_tool_results`.

### Recommended Changes

**1.1 Decompose the loop into distinct phases**

Split the current `handleToolUse()` into separate phases that the loop yields between:

```
Phase 1: Prepare inference request (system prompt, tools, message assembly)
Phase 2: Execute inference (LLM call)
Phase 3: Evaluate response (classify tool calls, check for final response)
Phase 4: Execute tools (run tool calls, collect results)
Phase 5: Incorporate results (format tool results into messages)
Phase 6: Evaluate continuation (should stop?)
```

Each phase should be its own step type in the iterator, allowing the caller to intercept between any two phases.

**1.2 Add explicit ExecutionStatus for "paused" state**

Add `ExecutionStatus::Paused` and support for deferred/approval-required tool execution. When a tool needs external input:
- The loop yields a state with `ExecutionStatus::Paused` and a `DeferredToolCalls` collection
- The caller provides results externally
- The loop is resumed with `$loop->resume($state, $deferredResults)`

**1.3 Use `StopReason::priority()` in `shouldStop()`**

The `StopReason` enum already has `priority()` defined but is never consulted. Multiple stop signals should be resolved by priority (Error > StopRequested > StepsLimit > TokenLimit > etc.), not by arrival order.

**1.4 Evaluate stop conditions BEFORE tool execution**

Check budget limits (steps, tokens, time) before executing tools, not only after. Pydantic AI checks `UsageLimits` before each model request AND after token counting.

**Impact: HIGH** — Enables human-in-the-loop, prevents wasted tool execution, gives callers fine-grained control.

---

## 2. AGENT STATE: Eliminate Ghost State, Add Transition Validation

### Problem

The `ensureExecution()` and `ensureCurrentStep()` patterns silently create fresh state objects when accessed on null:

```php
// AgentState.php:464
private function ensureExecution(): ExecutionState {
    return $this->execution ?? ExecutionState::fresh();
}

// ExecutionState.php:296
private function ensureCurrentStep(): AgentStep {
    return $this->currentStep ?? AgentStep::empty();
}
```

These are called in 14+ critical paths including `usage()`, `errors()`, `executionContinuation()`, `withFailure()`, `withStopSignal()`, `withCurrentStep()`. The result: operations on null execution silently succeed with incorrect data rather than failing explicitly.

Additionally, `withExecutionCompleted()` creates a ghost execution when `execution === null`:
```php
$this->execution === null => $this->with(execution: ExecutionState::fresh()->completed())
```
This produces a "completed" execution with zero steps, zero usage, and no history.

### What Pydantic AI Does

Pydantic AI separates mutable state (`GraphAgentState`) from immutable configuration (`GraphAgentDeps`). State is created once at the start of `iter()` and never re-created from null. There are no "ensure" patterns — if state is expected, it must exist.

### Recommended Changes

**2.1 Replace `ensure*()` with explicit null checks or required state**

Instead of silently creating fresh state, either:
- Throw `InvalidStateException` when accessing execution on a non-executing agent
- Or make `execution` non-nullable with an explicit `Pending` status

**2.2 Add state transition validation**

Validate that transitions are legal:
- `InProgress → Completed` (valid)
- `InProgress → Failed` (valid)
- `Completed → InProgress` (invalid without `forNextExecution()`)
- `null → Completed` (invalid — should not create ghost executions)

**2.3 Separate mutable state from immutable configuration**

Follow Pydantic AI's pattern: split current `AgentState` into:
- `AgentConfig` (immutable): agentId, parentAgentId, budget, system prompt, response format
- `AgentState` (mutable per-run): execution, context/messages, metadata

This eliminates the awkward `forNextExecution()` which selectively clears some fields but not others.

**Impact: HIGH** — Eliminates a class of silent data corruption bugs, makes state machine debuggable.

---

## 3. CONTEXT & MESSAGE MODEL: Explicit Section Semantics, Prevent Unbounded Growth

### Problem

`AgentContext::messagesForInference()` assembles messages from four sections in a hardcoded order:
```php
$sectionNames = [SUMMARY, BUFFER, DEFAULT, EXECUTION_BUFFER];
```

This creates several issues:
- **Implicit ordering** — no documentation of why this order exists or what each section's semantics are
- **Custom sections are ignored** — only these four are assembled
- **EXECUTION_BUFFER is silently cleared** when a final response is appended (line 194), with no event emitted
- **Message accumulation** — `DEFAULT_SECTION` grows indefinitely across executions since `forNextExecution()` only clears `EXECUTION_BUFFER`
- **No message-to-step correlation** — you can't determine which messages belong to which step

### What Pydantic AI Does

Messages are a single flat list with a `new_message_index` that tracks where prior history ends and new messages begin. `HistoryProcessor` callbacks can transform the message history before each model request (sliding window, summarization, etc.). Message parts carry `run_id` for correlation.

### Recommended Changes

**3.1 Add explicit section registry with declared semantics**

Define each section's role, ordering constraint, and lifecycle (cleared per-step, per-execution, or persistent). Custom sections should be registrable with explicit ordering.

**3.2 Add `HistoryProcessor` hook point**

Before each inference call, allow registered processors to transform the message list. This is cleaner than the current buffer/summary pattern because it's composable and explicit:
```php
interface HistoryProcessor {
    public function process(Messages $messages, AgentState $state): Messages;
}
```

**3.3 Tag messages with step ID and execution ID**

Each message added to the store should carry metadata identifying which step and execution produced it. This enables:
- Replay/debugging (which step produced which message)
- Forking (copy messages up to step N)
- Cleanup (remove messages from failed executions)

**3.4 Add message windowing / budget awareness**

Before inference, check total message token count against budget. If exceeding, apply configured strategy (truncate oldest, summarize, error).

**Impact: HIGH** — Prevents token bloat, enables conversation forking, makes message flow debuggable.

---

## 4. TOOL EXECUTION: Validation, Result Formatting, and Self-Correction

### Problem

Several issues in the tool execution pipeline:

**Weak argument validation** — `ToolExecutor::validateArgs()` only checks for missing required parameters. No type checking, no constraint validation, no enum validation. LLM-generated arguments are passed directly to tools.

**No self-correction loop** — When a tool call fails (validation or execution), the error is recorded but there's no mechanism to ask the LLM to retry with corrected arguments. The error is formatted as a message, but the loop doesn't distinguish "tool failed, please retry" from "tool succeeded, here are results."

**Naive result formatting** — `ToolExecutionFormatter::formatResultContent()` uses `var_export()` as a fallback and has no size limits. Large tool results can blow up the context window. `AgentState` results are reduced to just `finalResponse()->toString()`, losing all context.

**Tool args leak filter uses loose equality** — `ToolCallingDriver::isToolArgsLeak()` uses `==` instead of `===` for comparison, causing false positives where `{x: 0}` matches `{x: false}`.

### What Pydantic AI Does

- **Full Pydantic validation** of tool arguments against JSON Schema before execution
- **`ModelRetry` exception** — tools can raise `ModelRetry("please provide X instead")` which becomes a `RetryPromptPart` sent back to the LLM. The LLM gets the error details and corrects its call.
- **Per-tool retry counting** — each tool has its own `max_retries`. The `ToolManager` tracks retries per tool name and stops after the limit.
- **Rich `ToolReturnContent`** — supports nested structures, multimodal content, and recursive types. Results are validated and formatted consistently.
- **Result size is implicit** — all content goes through Pydantic serialization which keeps output clean.

### Recommended Changes

**4.1 Add schema-based argument validation**

Validate all arguments against the JSON Schema generated by `BaseTool::paramsJsonSchema()`:
- Type checking (string, int, float, bool, array, object)
- Enum constraint validation
- Required field validation (already exists)
- Type coercion where safe (string "123" → int 123)

**4.2 Implement tool retry with LLM feedback**

Add a `ToolRetryException` (equivalent to Pydantic AI's `ModelRetry`) that tools can throw to request self-correction:
```php
class ToolRetryException extends \RuntimeException {
    public function __construct(string $feedback, public readonly array $context = []) {
        parent::__construct($feedback);
    }
}
```

When caught by `ToolExecutor`, format as a retry prompt message with the feedback text and re-send to LLM. Track retries per tool with configurable `maxRetries`.

**4.3 Add per-tool retry tracking**

Add a `retries: array<string, int>` map to `ExecutionState` tracking how many times each tool has been retried. Stop retrying when `maxRetries` is exceeded.

**4.4 Add result size limits and truncation**

Before formatting a tool result into a message, check its size. If it exceeds a configurable limit:
- Truncate with a notice: `"[Result truncated: {actual_size} chars, showing first {limit}]"`
- Or summarize using a separate LLM call (optional, expensive)

**4.5 Fix loose equality in tool args leak detection**

Change `==` to `===` in `ToolCallingDriver::isToolArgsLeak()` line 233. Add logging when content is filtered.

**Impact: HIGH** — Self-correction dramatically improves agent reliability. Schema validation prevents tool crashes from malformed LLM output.

---

## 5. SUBAGENT ISOLATION & CONTEXT PROPAGATION

### Problem

`SpawnSubagentTool::createInitialState()` creates a completely fresh `AgentState` for the subagent:
```php
return AgentState::empty()
    ->withMessages($messages)
    ->withBudget($effectiveBudget)
    ->with(parentAgentId: $parentAgentId);
```

The subagent gets:
- Fresh messages (only system prompt + task prompt)
- A budget derived from parent's remaining budget
- Parent agent ID

The subagent does NOT get:
- Parent's conversation context (no awareness of what the parent was working on)
- Parent's metadata
- Any visibility into sibling subagent results
- Knowledge of the parent's constraints or failed attempts

Additionally, tool filtering uses allow/deny lists that can conflict silently, and there's no audit trail of which tools were removed and why.

### What Pydantic AI Does

No first-class subagent mechanism, but the tool-based delegation pattern naturally shares context through `RunContext.deps`:
```python
@agent.tool
async def delegate(ctx: RunContext[Deps], query: str) -> str:
    result = await sub_agent.run(query, deps=ctx.deps)
    return result.output
```

The dependency injection system ensures shared state is explicit and typed.

### Recommended Changes

**5.1 Add optional context inheritance policy**

Allow parent to control what context flows to subagent:
```php
class SubagentContextPolicy {
    public bool $inheritMessages = false;    // Share parent conversation
    public bool $inheritMetadata = false;    // Share parent metadata
    public int $messageWindowSize = 0;       // Last N messages from parent
    public array $metadataKeys = [];         // Specific keys to inherit
    public array $contextInjections = [];    // Additional context to inject
}
```

**5.2 Make tool filtering auditable**

When tools are filtered for a subagent, emit an event and record which tools were removed and why. Make filtered tool list available to the subagent (so it knows what it can't do).

**5.3 Add subagent result propagation**

After subagent execution, propagate more than just `finalResponse().toString()`:
- Subagent's execution summary (steps taken, tools used, budget consumed)
- Any metadata the subagent recorded
- Error details if the subagent failed

Currently, `ToolExecutionFormatter::formatResultContent()` reduces `AgentState` to a string. Instead, create a structured `SubagentResult` that carries execution metadata alongside the response.

**5.4 Add breadth tracking alongside depth tracking**

Track how many subagents have been spawned at each depth level. Add configurable limits:
```php
class SubagentPolicy {
    public int $maxDepth = 3;
    public int $maxBreadthPerParent = 5;   // Max subagents per parent
    public int $maxTotalSubagents = 10;     // Total across entire tree
}
```

**5.5 Subagent budget accounting**

After subagent completes, deduct its actual consumption from the parent's budget. Currently, the budget is "capped by" the parent's remaining budget but actual consumption is not tracked back.

**Impact: HIGH** — Context-aware subagents produce dramatically better results. Budget accounting prevents runaway costs.

---

## 6. MULTI-EXECUTION SESSIONS: Explicit Boundaries and History Management

### Problem

`AgentState::forNextExecution()` handles the transition between consecutive executions, but its semantics are unclear:
- It clears `EXECUTION_BUFFER_SECTION` but leaves `DEFAULT_SECTION` intact
- All previous conversation messages accumulate indefinitely
- There's no explicit "session boundary" marker in the message history
- No way to distinguish messages from different executions when reviewing history

### What Pydantic AI Does

Multi-turn conversations are explicit: pass `message_history=result.all_messages()` to the next run. The `new_message_index` cleanly separates old from new messages. There's no accumulated state — each run starts clean with an explicit history.

### Recommended Changes

**6.1 Add execution boundary markers in message history**

When transitioning between executions, insert a boundary marker:
```php
// In forNextExecution():
$store = $store->section(DEFAULT_SECTION)->appendMessages(
    Message::system("[Execution {$executionId} completed]")
);
```

**6.2 Add configurable message retention policy**

Define how many previous execution messages to retain:
```php
class MessageRetentionPolicy {
    public int $maxMessages = 100;          // Total messages to keep
    public int $maxExecutions = 5;          // Keep messages from last N executions
    public bool $summarizeOld = false;      // Summarize messages beyond retention
}
```

**6.3 Support conversation forking**

Add `AgentState::fork(int $upToStepN): AgentState` that creates a new state containing only messages up to a specific step. This enables exploring alternative paths.

**Impact: MEDIUM** — Prevents context window bloat in long-running sessions, enables sophisticated conversation management.

---

## 7. EXTENSIBILITY: Composable Tool Decorators

### Problem

Tool behavior can only be modified through hooks (BeforeToolUse, AfterToolUse). This is powerful but coarse — hooks apply to ALL tool executions and must filter by tool name internally. There's no way to compose behaviors per-tool or per-toolset.

### What Pydantic AI Does

The `AbstractToolset` class provides a decorator chain:
```python
toolset.filtered(lambda ctx, tool: tool.name != "dangerous")
       .prefixed("safe_")
       .approval_required()
```

Each decorator wraps the toolset transparently. Tools can be dynamically filtered, renamed, prefixed, or put behind approval — per-toolset, not globally.

### Recommended Changes

**7.1 Add composable tool wrappers**

```php
interface ToolWrapper {
    public function wrap(ToolInterface $tool): ToolInterface;
}
```

Implementations:
- `ApprovalRequiredWrapper` — requires external approval before execution
- `RateLimitedWrapper` — limits calls per time window
- `TimeoutWrapper` — enforces execution time limit
- `RetryWrapper` — automatic retry with configurable policy

**7.2 Add per-tool configuration**

Allow tools to declare their own retry limits, timeout, and approval requirements:
```php
class BaseTool {
    protected int $maxRetries = 1;
    protected ?float $timeout = null;
    protected bool $requiresApproval = false;
}
```

**7.3 Add dynamic tool filtering per-step**

Allow tools to be filtered based on current state before each inference call:
```php
interface ToolFilter {
    public function filter(ToolInterface $tool, AgentState $state): bool;
}
```

This enables scenarios like: "only allow file-write tools after confirmation" or "disable expensive tools when budget is low."

**Impact: MEDIUM** — Cleaner than global hooks for tool-specific behavior. Reduces hook complexity.

---

## 8. ERROR HANDLING: Unified Recovery Strategy

### Problem

Error handling is inconsistent across the stack:
- **Argument validation** returns `Result::failure()` (silent, tool not executed)
- **Tool execution** catches `Throwable` and wraps in `Result::failure()` (silent unless `throwOnToolFailure=true`)
- **Hook blocking** throws `ToolExecutionBlockedException` (exception-based)
- **Stop conditions** throw `AgentStopException` (exception-based, caught by loop)
- **Driver failures** propagate unhandled to the loop's catch-all

There's no distinction between recoverable errors (retry the tool call) and fatal errors (abort the execution). The LLM receives the same generic "Error in tool call: {message}" regardless of error type.

### What Pydantic AI Does

Clear exception hierarchy with distinct semantics:
- `ModelRetry` → retry prompt sent to LLM (recoverable)
- `CallDeferred` → pause execution for external handling
- `ApprovalRequired` → pause for human approval
- `UsageLimitExceeded` → abort (budget exhausted)
- `UnexpectedModelBehavior` → abort (model misbehaving)

Each exception type has a clear handler in the loop. `RetryPromptPart` carries detailed validation errors that the LLM can use to self-correct.

### Recommended Changes

**8.1 Create error classification enum**

```php
enum ToolErrorSeverity {
    case Retryable;      // Ask LLM to try again with different args
    case Recoverable;    // Error can be worked around
    case Fatal;          // Must abort this tool call
    case Abortive;       // Must abort entire execution
}
```

**8.2 Format error messages by type for LLM**

Instead of generic "Error in tool call":
- **Validation error**: Include the schema, the invalid value, and a hint: "Parameter 'count' must be an integer, got string '3'. Please retry with correct types."
- **Execution error**: Include the tool name and error type: "Tool 'search_files' failed: FileNotFoundException. The path '/foo' does not exist. Try a different path."
- **Timeout**: "Tool 'analyze_data' timed out after 30s. Consider using a smaller dataset."

**8.3 Add error budgets**

Track errors per execution and abort if too many:
```php
class ErrorBudget {
    public int $maxToolErrors = 5;
    public int $maxConsecutiveErrors = 3;
    public int $maxRetryErrors = 10;
}
```

**Impact: HIGH** — Self-correcting error messages dramatically improve agent reliability. Error classification prevents infinite retry loops.

---

## 9. CONTEXT ISOLATION: Driver Contract & Hook Side Effects

### Problem

**Driver can modify state invisibly.** `ToolCallingDriver::useTools()` passes `AgentState` to `ToolExecutor::executeTools()`, where hooks can modify the state. But those modifications are not returned — the driver builds a step from the original state's context (line 126), not from the potentially-modified state:

```php
$executions = $executor->executeTools($toolCalls, $state);  // state passed by value
// ... hooks inside executor modify state, but changes are lost
$context = $state->context()->messagesForInference();  // Uses ORIGINAL state
```

**Hooks have unrestricted mutation power.** `HookContext::with()` allows modifying any field including state, toolCall, and metadata. There's no validation that a BeforeToolUse hook doesn't access toolExecution (which doesn't exist yet). Hooks in the same chain can't see each other's mutations.

### What Pydantic AI Does

Context isolation uses Python's `ContextVar` — each async task gets its own copy of the run context. Tools receive a `RunContext` built specifically for that tool call via `build_run_context()`, which includes the tool-specific retry count, tool name, and call ID. The context is read-only from the tool's perspective.

### Recommended Changes

**9.1 Make tool execution context immutable for tools**

Tools should receive a read-only context snapshot:
```php
interface ToolContext {
    public function agentId(): string;
    public function stepNumber(): int;
    public function toolCallId(): string;
    public function retryCount(): int;
    public function maxRetries(): int;
    public function metadata(): Metadata;  // read-only
    public function messages(): Messages;  // read-only snapshot
}
```

This replaces `CanAccessAgentState` (which gives full mutable access).

**9.2 Return modified state from tool executor**

Change `executeTools()` signature to return the state as modified by hooks:
```php
/** @return array{ToolExecutions, AgentState} */
public function executeTools(ToolCalls $toolCalls, AgentState $state): array;
```

The driver should use the returned state for subsequent operations.

**9.3 Validate hook mutations by trigger type**

Add validation that hooks only modify fields relevant to their trigger:
- `BeforeToolUse` can modify: toolCall, isToolExecutionBlocked, metadata
- `AfterToolUse` can modify: toolExecution, metadata
- `BeforeStep` can modify: state, metadata
- `AfterStep` can modify: state, metadata

**Impact: HIGH** — Prevents a class of subtle bugs where hook side effects are lost or create inconsistent state.

---

## 10. STEP DATA CAPTURE: Enable Replay and Debugging

### Problem

`AgentStep` captures `inputMessages`, `outputMessages`, `inferenceResponse`, `toolExecutions`, and `errors`. It does NOT capture:
- The system prompt that was active during this step
- The tool definitions that were available
- The LLM model and parameters used
- The budget state at step start
- Timing breakdown (LLM latency vs tool execution time)

This makes step replay impossible and debugging difficult.

### What Pydantic AI Does

`GraphAgentDeps` carries the full configuration (model, settings, tools, output schema, etc.) alongside the mutable `GraphAgentState`. OTel spans capture tool arguments, results, and timing at fine granularity. Every step can be fully reconstructed from the span data.

### Recommended Changes

**10.1 Add step context snapshot**

```php
final readonly class StepContext {
    public string $model;
    public array $toolNames;
    public string $systemPromptHash;  // Not full prompt, just hash for comparison
    public Budget $budgetAtStart;
    public int $stepNumber;
}
```

Attach to `AgentStep` for replay and debugging.

**10.2 Add sub-step timing**

Break down step duration into:
- `inferenceLatency` — time for LLM API call
- `toolExecutionTime` — total time for all tool calls
- `hookOverhead` — time spent in hooks
- `formatTime` — time for message formatting

**Impact: MEDIUM** — Essential for debugging and optimization, especially in production.

---

## 11. OBSERVABILITY: Structured Events Over Generic Dispatch

### Problem

Events are dispatched via `AgentEventEmitter` but carry minimal structured data. There's no standard for what data each event includes, and events cannot be correlated across steps or subagent boundaries.

### What Pydantic AI Does

All message parts implement `otel_event()` and `otel_message_parts()` for structured OpenTelemetry export. Spans carry standardized attributes (`gen_ai.tool.name`, `gen_ai.tool.call.id`). Version-aware naming ensures forward compatibility.

### Recommended Changes

**11.1 Add correlation IDs to all events**

Every event should carry: `executionId`, `stepId`, `toolCallId` (where applicable), `parentAgentId` (for subagent events). This enables trace reconstruction.

**11.2 Add structured event payloads**

Define typed event payload classes instead of passing arrays:
```php
class ToolCallCompletedEvent extends AgentEvent {
    public function __construct(
        public readonly string $toolName,
        public readonly string $toolCallId,
        public readonly string $stepId,
        public readonly float $durationMs,
        public readonly bool $success,
        public readonly ?string $errorType,
        public readonly Usage $usage,
    ) {}
}
```

**Impact: MEDIUM** — Enables production monitoring, distributed tracing, and cost attribution.

---

## Priority Summary

| # | Area | Impact | Effort | Priority |
|---|------|--------|--------|----------|
| 4 | Tool self-correction (retry with LLM feedback) | HIGH | Medium | P0 |
| 1 | Loop phase decomposition | HIGH | High | P0 |
| 2 | State transition validation | HIGH | Medium | P1 |
| 9 | Context isolation (tool & hook safety) | HIGH | Medium | P1 |
| 8 | Error classification & recovery | HIGH | Medium | P1 |
| 5 | Subagent context propagation | HIGH | Medium | P1 |
| 3 | Message model & history management | HIGH | Medium | P2 |
| 7 | Composable tool decorators | MEDIUM | Medium | P2 |
| 6 | Multi-execution session boundaries | MEDIUM | Low | P2 |
| 10 | Step data capture for replay | MEDIUM | Low | P3 |
| 11 | Structured observability events | MEDIUM | Medium | P3 |
