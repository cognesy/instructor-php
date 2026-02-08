# Pi Mono Architecture Analysis

## Overview

Pi Mono is a TypeScript monorepo implementing an AI coding agent framework. The architecture follows a layered design with clear separation between the generic agent runtime, the AI provider abstraction, and the coding-specific application logic.

### Package Structure

| Package | npm Name | Role |
|---------|----------|------|
| `agent` | `@mariozechner/pi-agent-core` | Generic agent loop, state, event stream |
| `ai` | `@mariozechner/pi-ai` | LLM provider abstraction, streaming, models |
| `coding-agent` | `@mariozechner/pi-coding-agent` | Coding-specific session, tools, extensions, SDK |
| `mom` | `@mariozechner/pi-mom` | Orchestration daemon (sandbox, Slack, store) |
| `pods` | `@mariozechner/pi` | CLI entry point, deployment configs |
| `tui` | `@mariozechner/pi-tui` | Terminal UI (Ink-based) |
| `web-ui` | `@mariozechner/pi-web-ui` | Web UI frontend |

### Dependency Flow

```
pods (CLI) / tui / web-ui
        ↓
   coding-agent
    ↓        ↓
  agent      ai
```

The `mom` package is a separate orchestration layer (supervisor/daemon) that wraps the agent in a sandbox with Slack integration.

---

## 1. Agent Loop

**Key files:** `packages/agent/src/agent-loop.ts`, `packages/agent/src/agent.ts`

### Architecture

The agent loop implements a **dual-loop** pattern with an outer follow-up loop and an inner tool-execution loop:

```
agentLoop(prompts, context, config, signal)
  └─ runLoop()
       ├─ OUTER LOOP: while(true)  ← follow-up messages keep it alive
       │    ├─ INNER LOOP: while(hasMoreToolCalls || pendingMessages)
       │    │    ├─ Process pending steering messages
       │    │    ├─ streamAssistantResponse()  ← LLM call
       │    │    ├─ Check for tool calls
       │    │    ├─ executeToolCalls()  ← sequential, with steering checks
       │    │    └─ Check for new steering messages
       │    └─ getFollowUpMessages()  ← agent would stop, check queue
       └─ agent_end + stream.end()
```

### Entry Points

- **`agentLoop()`** (`agent-loop.ts:28`): Starts a new loop with prompt messages. Adds prompts to context, emits `agent_start`/`turn_start`, then enters `runLoop`.
- **`agentLoopContinue()`** (`agent-loop.ts:65`): Continues from existing context without new messages. Used for retry after compaction/overflow. Validates that the last message is not `assistant`.

Both return an `EventStream<AgentEvent, AgentMessage[]>` — an async iterable that yields events and resolves to the accumulated new messages.

### LLM Call Boundary

The critical design principle: **AgentMessages flow throughout the loop, but are transformed to provider-compatible `Message[]` only at the LLM call boundary** (`agent-loop.ts:204-289`):

1. `config.transformContext(messages)` — optional AgentMessage[] → AgentMessage[] transform (e.g., context pruning)
2. `config.convertToLlm(messages)` — AgentMessage[] → Message[] for the LLM
3. Build `Context` object with system prompt, messages, tools
4. Call `streamFn(model, context, options)` — returns `AssistantMessageEventStream`

Streaming events are relayed through the event stream: `start` → `text_delta`/`thinking_delta`/`toolcall_delta` → `done`/`error`.

### Tool Execution

Tools execute **sequentially** within a turn (`agent-loop.ts:294-378`):

1. For each tool call in the assistant message:
   - Emit `tool_execution_start`
   - Find tool by name, validate arguments via `validateToolArguments()`
   - Call `tool.execute(toolCallId, params, signal, onUpdate)` with abort signal and progress callback
   - Emit `tool_execution_end`
   - **Check for steering messages** after each tool — if present, skip remaining tools with "Skipped due to queued user message" error results

### Agent Class

`Agent` (`agent.ts:90`) wraps the loop functions in a stateful class:

- **State management**: `AgentState` with model, tools, messages, streaming status
- **Message queuing**: `steer()` and `followUp()` with configurable modes (`"all"` vs `"one-at-a-time"`)
- **AbortController**: Each `_runLoop()` creates a new controller; `abort()` triggers signal
- **Event subscription**: Simple `Set<(e: AgentEvent) => void>` listener pattern
- **Idle tracking**: `waitForIdle()` returns a Promise that resolves when the loop finishes

---

## 2. Agent State Data Model

**Key file:** `packages/agent/src/types.ts`

### AgentState

```typescript
interface AgentState {
  systemPrompt: string;
  model: Model<any>;
  thinkingLevel: ThinkingLevel;      // "off" | "minimal" | "low" | "medium" | "high" | "xhigh"
  tools: AgentTool<any>[];
  messages: AgentMessage[];           // Full conversation history
  isStreaming: boolean;
  streamMessage: AgentMessage | null; // Currently streaming partial message
  pendingToolCalls: Set<string>;      // Tool call IDs currently executing
  error?: string;
}
```

### AgentMessage — Extensible Union Type

```typescript
type AgentMessage = Message | CustomAgentMessages[keyof CustomAgentMessages];
```

The `CustomAgentMessages` interface uses **TypeScript declaration merging** to allow applications to add custom message types without modifying the core:

```typescript
// In coding-agent:
declare module "@mariozechner/agent" {
  interface CustomAgentMessages {
    bashExecution: BashExecutionMessage;
    custom: CustomMessage;
    compactionSummary: CompactionSummaryMessage;
    branchSummary: BranchSummaryMessage;
  }
}
```

This means the agent loop works with arbitrary message types transparently — `convertToLlm` filters/transforms them at the LLM boundary.

### AgentTool

```typescript
interface AgentTool<TParams extends TSchema, TDetails> extends Tool<TParams> {
  label: string;
  execute: (
    toolCallId: string,
    params: Static<TParams>,
    signal?: AbortSignal,
    onUpdate?: AgentToolUpdateCallback<TDetails>,
  ) => Promise<AgentToolResult<TDetails>>;
}
```

Tools use **TypeBox schemas** for parameter validation. The `details` generic carries tool-specific metadata (e.g., `BashToolDetails` with exit code, `EditToolDetails` with diff).

### AgentLoopConfig

Configuration passed to the loop functions:

```typescript
interface AgentLoopConfig extends SimpleStreamOptions {
  model: Model<any>;
  convertToLlm: (messages: AgentMessage[]) => Message[];
  transformContext?: (messages: AgentMessage[], signal?: AbortSignal) => Promise<AgentMessage[]>;
  getApiKey?: (provider: string) => Promise<string | undefined>;
  getSteeringMessages?: () => Promise<AgentMessage[]>;
  getFollowUpMessages?: () => Promise<AgentMessage[]>;
}
```

---

## 3. Conversation Data Model — Forking and Rewind

**Key files:** `packages/coding-agent/src/core/session-manager.ts`

### Session Storage Format

Sessions are stored as **append-only JSONL files** (one JSON object per line). The first line is always a `SessionHeader`:

```typescript
interface SessionHeader {
  type: "session";
  version: number;        // Currently 3
  id: string;             // UUID
  timestamp: string;      // ISO date
  cwd: string;            // Working directory
  parentSession?: string; // Path to parent session (for forks)
}
```

### Entry Types and Tree Structure

Every entry has `id` and `parentId`, forming a **tree** (not a linear list):

| Entry Type | Purpose |
|-----------|---------|
| `message` | User/assistant/toolResult messages |
| `thinking_level_change` | Records thinking level changes |
| `model_change` | Records model switches |
| `compaction` | Summary of compacted conversation prefix |
| `branch_summary` | Summary of abandoned branch context |
| `custom` | Extension-specific data (not sent to LLM) |
| `custom_message` | Extension messages that DO participate in LLM context |
| `label` | User-defined bookmarks on entries |
| `session_info` | Session metadata (display name) |

### Tree-Based Conversation History

The `SessionManager` maintains a **leaf pointer** (`leafId`) that tracks the current position:

- **Appending** (`_appendEntry`): Creates a new entry with `parentId = leafId`, then advances `leafId` to the new entry
- **Branching** (`branch(branchFromId)`): Moves `leafId` to an earlier entry. The next append creates a child of that entry, forming a new branch. **No entries are modified or deleted.**
- **Branching with summary** (`branchWithSummary`): Same as branch, but also appends a `branch_summary` entry capturing context from the abandoned path

### Context Resolution (Rewind)

`buildSessionContext()` (`session-manager.ts:307-414`) walks from the current leaf to the root, collecting messages along the path:

1. Walk `parentId` chain from leaf → root, building a `path[]`
2. Extract settings (model, thinking level) from path
3. If a `compaction` entry exists in the path:
   - Emit compaction summary as first message
   - Emit "kept" messages (from `firstKeptEntryId` to compaction)
   - Emit messages after compaction
4. Otherwise, emit all messages in path order

This means **rewind is implicit**: changing the leaf pointer and rebuilding context gives a different conversation view.

### Fork Support

- **`createBranchedSession(leafId)`** (`session-manager.ts:1156`): Extracts a single path from root to `leafId` into a new JSONL session file. Sets `parentSession` in the header for lineage tracking.
- **`SessionManager.forkFrom(sourcePath, targetCwd)`** (`session-manager.ts:1292`): Forks a session from another project directory, creating a new session in the target with full history copied.

### Migration System

Sessions are version-migrated on load (`session-manager.ts:258-268`):
- v1→v2: Adds `id`/`parentId` tree structure (originally linear)
- v2→v3: Renames `hookMessage` role to `custom`

---

## 4. Context Isolation

### Main Thread vs. Tool Execution

Tool execution runs **in the same process and thread** as the agent loop. There is no process-level isolation for tools. However:

- Each tool receives its own `AbortSignal` for cancellation
- Tools have a dedicated `onUpdate` callback for streaming progress
- The bash tool (`bash-executor.ts`) spawns child processes via `child_process.spawn()` for command execution
- Tool execution is sequential (one at a time), not parallel

### Subagent Isolation

The architecture does not have explicit "subagent" spawning at the core `agent` package level. Subagent-like behavior is achieved through:

1. **Multiple `Agent` instances**: The `Agent` class is lightweight and can be instantiated multiple times
2. **Separate `AgentSession` instances**: Each session has its own agent, session manager, and extension runner
3. **The `mom` package**: Acts as a supervisor that manages agent instances in sandboxed environments

### The Mom Package — Sandbox Isolation

`packages/mom/src/sandbox.ts` provides process-level isolation:

- Uses `child_process` to spawn agent processes in controlled environments
- The `mom` agent (`packages/mom/src/agent.ts`) orchestrates multiple sandboxed agents
- Communication via events (`packages/mom/src/events.ts`) and a store (`packages/mom/src/store.ts`)
- Integrates with Slack (`packages/mom/src/slack.ts`) for external communication

### Proxy Stream — Network Isolation

`packages/agent/src/proxy.ts` provides a `streamProxy()` function that routes LLM calls through a remote server instead of calling providers directly:

```typescript
const agent = new Agent({
  streamFn: (model, context, options) =>
    streamProxy(model, context, {
      ...options,
      authToken: await getAuthToken(),
      proxyUrl: "https://genai.example.com",
    }),
});
```

The proxy reconstructs the partial message client-side from bandwidth-optimized server events (delta-only, no partial field).

---

## 5. Multi-Execution Session Handling

### Concurrent Execution Prevention

The `Agent` class prevents concurrent execution at the `prompt()` level:

```typescript
async prompt(input) {
  if (this._state.isStreaming) {
    throw new Error("Agent is already processing a prompt. Use steer() or followUp().");
  }
  // ...
}
```

### Steering and Follow-Up Queues

Instead of concurrent execution, the system uses **message queuing** for mid-execution interaction:

- **Steering messages** (`agent.steer()`): Delivered after the current tool completes, skip remaining tool calls. Used for interruption/redirection.
- **Follow-up messages** (`agent.followUp()`): Delivered only after the agent would otherwise stop (no more tools, no steering). Used for chaining prompts.

Both support two modes:
- `"one-at-a-time"`: Delivers one queued message per check
- `"all"`: Delivers all queued messages at once

### Session Persistence

The `SessionManager` handles concurrent writes safely through:

- **Append-only JSONL**: New entries are appended via `appendFileSync()`, avoiding read-modify-write races
- **Lazy flush**: Entries aren't written to disk until the first assistant message arrives (avoids saving sessions that fail on the first LLM call)
- **Full flush on first write**: On first persist, all accumulated entries are written together

### Session Lifecycle (AgentSession)

The `AgentSession` (`agent-session.ts:205`) manages the full session lifecycle:

1. **Subscribe to agent events** → persist messages on `message_end`
2. **Auto-compaction** → triggered on `agent_end` if context exceeds threshold or overflow
3. **Auto-retry** → exponential backoff for transient errors (429, 500, 502, 503, 504)
4. **Session switching** → disconnect/reconnect pattern preserves listeners

---

## 6. Subagents Support

Pi Mono does not have a first-class "subagent" abstraction in the core agent package. Instead, subagent patterns are achieved through:

### 1. Multiple Agent Instances

Each `Agent` is a self-contained unit with its own state, tools, and event stream. Creating a subagent means creating a new `Agent`:

```typescript
const subAgent = new Agent({
  initialState: { model, tools: subsetOfTools },
  convertToLlm: ...,
});
await subAgent.prompt("subtask instructions");
```

### 2. The Mom Supervisor

The `mom` package (`packages/mom/`) implements a supervisor pattern:
- `packages/mom/src/agent.ts` — Orchestration agent that manages child agents
- `packages/mom/src/sandbox.ts` — Process-level sandboxing for child agents
- `packages/mom/src/store.ts` — Shared state store across managed agents
- Communication via `packages/mom/src/events.ts` event bus

### 3. SDK Custom Tools

Extensions can spawn sub-conversations through the SDK by creating new `Agent` instances within tool execution, using the `sendMessage()` / `sendUserMessage()` APIs to inject results back.

---

## 7. Tool Discovery

**Key files:** `packages/coding-agent/src/core/tools/index.ts`, `packages/coding-agent/src/core/agent-session.ts`

### Built-in Tools

Seven built-in tools are defined as factory functions:

| Tool | Factory | Purpose |
|------|---------|---------|
| `read` | `createReadTool(cwd)` | File reading with image support |
| `bash` | `createBashTool(cwd)` | Shell command execution |
| `edit` | `createEditTool(cwd)` | File editing (search/replace) |
| `write` | `createWriteTool(cwd)` | File creation/overwrite |
| `grep` | `createGrepTool(cwd)` | Content search (ripgrep-style) |
| `find` | `createFindTool(cwd)` | File search (glob patterns) |
| `ls` | `createLsTool(cwd)` | Directory listing |

Default active set: `[read, bash, edit, write]`. Users can toggle tools via `/tools` command.

### Extension-Registered Tools

Extensions register tools via `ToolDefinition`:

```typescript
interface ToolDefinition {
  name: string;
  description: string;
  label: string;
  parameters: TSchema;
  execute: (toolCallId, params, signal?, onUpdate?) => Promise<AgentToolResult>;
}
```

Discovery flow (`_buildRuntime` in `agent-session.ts:1901`):
1. Create base tools from factories (or overrides)
2. Load extensions via `ResourceLoader.getExtensions()`
3. Get registered tools from `ExtensionRunner.getAllRegisteredTools()`
4. Combine SDK custom tools with extension tools
5. Wrap all tools with extension interceptors (`wrapToolsWithExtensions`)
6. Build tool registry (all tools) and active tools (subset)

### Tool Activation

Tools are managed through a two-level registry:
- `_toolRegistry`: All available tools (base + extension)
- `agent.state.tools`: Currently active tools (subset sent to LLM)

`setActiveToolsByName()` selects which tools are active, and also rebuilds the system prompt to match.

---

## 8. Serialization / Deserialization

### Session Files (JSONL)

Sessions use newline-delimited JSON:

```
{"type":"session","version":3,"id":"uuid","timestamp":"ISO","cwd":"/path"}
{"type":"message","id":"abc","parentId":null,"timestamp":"ISO","message":{...}}
{"type":"thinking_level_change","id":"def","parentId":"abc","timestamp":"ISO","thinkingLevel":"high"}
```

**Serialization**: `_persist()` → `appendFileSync(sessionFile, JSON.stringify(entry) + "\n")`

**Deserialization**: `loadEntriesFromFile()` → split by newlines, `JSON.parse()` each line, skip malformed. Then `migrateToCurrentVersion()` for backward compatibility.

### Settings

`SettingsManager` (`settings-manager.ts`) persists user preferences as JSON files:
- `~/.pi/agent/settings.json` — Global settings
- `.pi/settings.json` — Project-local settings (overrides global)

### Auth Storage

`AuthStorage` (`auth-storage.ts`) manages API credentials:
- `~/.pi/agent/auth.json` — Encrypted/stored API keys and OAuth tokens

### Model Registry

`ModelRegistry` (`model-registry.ts`) combines:
- Built-in model definitions (`models.generated.ts`)
- User-defined model configurations (`~/.pi/agent/models.json`)
- Environment variable API keys
- OAuth token resolution

---

## 9. Extension Points

### Extensions (Plugin System)

**Key files:** `packages/coding-agent/src/core/extensions/`

Extensions are JavaScript/TypeScript modules discovered from:
- `~/.pi/agent/extensions/` (user-global)
- `.pi/extensions/` (project-local)

Each extension exports a factory function:

```typescript
type ExtensionFactory = (api: ExtensionAPI) => Extension;
```

The `ExtensionAPI` provides:
- `pi.registerCommand(name, handler)` — Slash commands
- `pi.registerTool(definition)` — Custom tools
- `pi.on(event, handler)` — Event handlers
- `pi.sendMessage(message)` — Inject messages into conversation
- `pi.sendUserMessage(content)` — Send as user message
- `pi.getModel()`, `pi.setModel()` — Model control
- `pi.getActiveTools()`, `pi.setActiveTools()` — Tool control
- `pi.registerFlag(name, options)` — Feature flags
- `pi.registerShortcut(options)` — Keyboard shortcuts
- `pi.registerWidget(options)` — UI widgets
- `pi.exec(command)` — Execute shell commands
- `pi.compact(options)` — Trigger compaction

### Extension Events

Extensions can handle a rich set of lifecycle events:

| Category | Events |
|----------|--------|
| Agent | `agent_start`, `agent_end`, `before_agent_start` |
| Turn | `turn_start`, `turn_end` |
| Tool | `tool_call` (per-tool: `bash_call`, `edit_call`, etc.), `tool_result` |
| Session | `session_start`, `session_switch`, `session_shutdown`, `session_compact`, `session_fork`, `session_tree` |
| Input | `input` (intercept/transform user input) |
| Context | `context` (transform context before LLM call) |
| Resources | `resources_discover` (add skill/prompt/theme paths) |
| Model | `model_select` |
| Bash | `user_bash` (intercept user bash commands) |

Event handlers can return results to modify behavior (e.g., `before_agent_start` can modify the system prompt, `session_before_compact` can provide custom compaction, `input` can transform text).

### Tool Extension Wrapping

`wrapToolsWithExtensions()` (`wrapper.ts`) wraps every tool to emit `tool_call` events before execution and `tool_result` events after. This allows extensions to intercept, modify, or block any tool call.

### Skills

**Key file:** `packages/coding-agent/src/core/skills.ts`

Skills are markdown files with YAML frontmatter discovered from:
- `~/.pi/agent/skills/` (user-global)
- `.pi/skills/` (project-local)
- Explicit `--skills` paths

```yaml
---
name: my-skill
description: Does something specific
disable-model-invocation: false
---
Skill content here...
```

Skills are included in the system prompt as XML, allowing the LLM to use the `read` tool to load them on demand. Users invoke skills explicitly via `/skill:name args`.

### Prompt Templates

File-based prompt templates in `~/.pi/agent/prompts/` and `.pi/prompts/`. Users invoke them as `/template-name args`.

### Hooks

The extension event system replaces traditional hooks. Extensions register handlers via `pi.on(event, handler)` and can:
- Intercept and transform input before it reaches the LLM
- Run custom logic before/after agent turns
- Intercept tool calls and results
- Handle session lifecycle events
- Provide custom compaction and branch summarization

---

## 10. Observability

### Agent Events

**Key file:** `packages/agent/src/types.ts:179-194`

The agent emits fine-grained lifecycle events:

```typescript
type AgentEvent =
  | { type: "agent_start" }
  | { type: "agent_end"; messages: AgentMessage[] }
  | { type: "turn_start" }
  | { type: "turn_end"; message: AgentMessage; toolResults: ToolResultMessage[] }
  | { type: "message_start"; message: AgentMessage }
  | { type: "message_update"; message: AgentMessage; assistantMessageEvent: AssistantMessageEvent }
  | { type: "message_end"; message: AgentMessage }
  | { type: "tool_execution_start"; toolCallId; toolName; args }
  | { type: "tool_execution_update"; toolCallId; toolName; args; partialResult }
  | { type: "tool_execution_end"; toolCallId; toolName; result; isError }
```

### AgentSession Events (Extended)

`AgentSessionEvent` extends `AgentEvent` with:
- `auto_compaction_start` / `auto_compaction_end`
- `auto_retry_start` / `auto_retry_end`

### EventBus

`packages/coding-agent/src/core/event-bus.ts` — Simple `EventEmitter`-based pub/sub with error-safe handlers:

```typescript
interface EventBus {
  emit(channel: string, data: unknown): void;
  on(channel: string, handler: (data: unknown) => void): () => void;
}
```

### EventStream (Async Iterable)

`packages/ai/src/stream.ts` uses `EventStream<E, R>` — a push-based async iterable:
- Events are pushed via `.push(event)`
- Consumers iterate via `for await (const event of stream)`
- The stream has a terminal condition (checked via `isDone` predicate)
- `.result()` returns the accumulated result after the stream ends

### Logging

`packages/mom/src/log.ts` — Logging for the mom supervisor package.

### OpenTelemetry

No explicit OpenTelemetry integration was found in the codebase. Observability is primarily through the event-based system described above. The `AssistantMessage` type includes detailed `usage` data:

```typescript
usage: {
  input: number;
  output: number;
  cacheRead: number;
  cacheWrite: number;
  totalTokens: number;
  cost: { input; output; cacheRead; cacheWrite; total };
}
```

### Diagnostics

`packages/coding-agent/src/core/diagnostics.ts` — Resource loading diagnostics (warnings about skills, prompts, extensions).

### Timings

`packages/coding-agent/src/core/timings.ts` — Simple timing instrumentation for startup performance.

---

## 11. Interrupt Mechanism

### AbortController Pattern

Every level of the system uses `AbortController`/`AbortSignal` for cooperative cancellation:

1. **Agent level** (`agent.ts:259-261`):
   ```typescript
   abort() { this.abortController?.abort(); }
   ```

2. **Agent loop** (`agent-loop.ts:141-148`): Checks `signal` after streaming:
   ```typescript
   if (message.stopReason === "error" || message.stopReason === "aborted") {
     stream.push({ type: "agent_end", ... });
     stream.end(newMessages);
     return;
   }
   ```

3. **Tool execution** (`agent-loop.ts:324`): Signal passed to each tool:
   ```typescript
   result = await tool.execute(toolCall.id, validatedArgs, signal, onUpdate);
   ```

4. **AgentSession level** — Multiple abort controllers:
   - `_compactionAbortController` — Cancel compaction
   - `_autoCompactionAbortController` — Cancel auto-compaction
   - `_branchSummaryAbortController` — Cancel branch summarization
   - `_retryAbortController` — Cancel retry delay
   - `_bashAbortController` — Cancel user bash execution

### Steering-Based Interruption

The steering mechanism provides **soft interruption** without aborting:

1. User calls `agent.steer(message)` during execution
2. After each tool completes, `getSteeringMessages()` is polled
3. If messages found, remaining tool calls are **skipped** (not aborted — they get error results with "Skipped due to queued user message")
4. Steering messages are injected into context before the next LLM call

### AgentSession.abort()

```typescript
async abort(): Promise<void> {
  this.abortRetry();    // Cancel any retry in progress
  this.agent.abort();   // Signal AbortController
  await this.agent.waitForIdle();  // Wait for loop to finish
}
```

### Proxy Abort

`streamProxy()` (`proxy.ts:110-114`) registers an abort handler on the signal to cancel the ReadableStream reader:
```typescript
const abortHandler = () => {
  reader?.cancel("Request aborted by user").catch(() => {});
};
signal.addEventListener("abort", abortHandler);
```

---

## 12. Step-by-Step Execution

### Interactive Mode

The TUI (`packages/tui/`) provides user-controlled execution:

- User types a prompt → `AgentSession.prompt()` runs the full loop
- During execution, the user can:
  - **Steer** (Ctrl+Enter or enter while streaming): Queue a steering message
  - **Abort** (Escape): Cancel current execution
  - **Follow-up**: Queue messages for after completion

There is no built-in single-step/pause mechanism at the agent loop level. The loop runs to completion (or interruption) for each prompt.

### Print Mode

`packages/coding-agent/src/modes/print-mode.ts` — Non-interactive execution that runs a single prompt and exits. Used for scripting and CI.

### RPC Mode

`packages/coding-agent/src/modes/rpc/` — JSON-RPC interface for programmatic control. Allows external processes to drive the agent step-by-step.

### Execution Control Points

While there's no pause/resume, the system offers fine-grained control through:

1. **Steering messages**: Redirect the agent mid-execution
2. **Follow-up messages**: Chain prompts after completion
3. **Extension events**: `before_agent_start` can modify context before each run; `turn_end` fires after each turn
4. **Tool interception**: Extensions can block/modify individual tool calls

---

## 13. Tool Handling

### Tool Definition

Tools implement the `AgentTool` interface (`types.ts:157-166`):

```typescript
interface AgentTool<TParams extends TSchema, TDetails> extends Tool<TParams> {
  name: string;
  description: string;
  label: string;           // Human-readable display name
  parameters: TSchema;     // TypeBox schema for validation
  execute: (toolCallId, params, signal?, onUpdate?) => Promise<AgentToolResult<TDetails>>;
}
```

### Execution Pipeline

1. **LLM returns tool calls** in assistant message content blocks
2. **Agent loop iterates** tool calls sequentially (`agent-loop.ts:305`)
3. **Tool lookup**: `tools?.find(t => t.name === toolCall.name)` — error if not found
4. **Argument validation**: `validateToolArguments(tool, toolCall)` using TypeBox schema
5. **Execution**: `tool.execute(id, validatedArgs, signal, onUpdate)`
6. **Progress updates**: `onUpdate` callback emits `tool_execution_update` events
7. **Result packaging**: Wrapped in `ToolResultMessage` with `content`, `details`, `isError`
8. **Steering check**: After each tool, poll for steering messages

### Error Handling in Tools

```typescript
try {
  result = await tool.execute(...);
} catch (e) {
  result = {
    content: [{ type: "text", text: e.message }],
    details: {},
  };
  isError = true;
}
```

Tool errors are caught and returned as error results to the LLM, allowing it to recover.

### Extension Wrapping

All tools pass through `wrapToolsWithExtensions()` which:
1. Emits `tool_call` event before execution (extension can modify args or skip)
2. Calls the original tool
3. Emits `tool_result` event after execution (extension can modify result)

---

## 14. Error Handling

### Layered Error Strategy

```
LLM Provider Errors (packages/ai)
  ↓ propagate as stopReason: "error" + errorMessage
Agent Loop (packages/agent)
  ↓ catches, creates error AgentMessage, emits agent_end
AgentSession (packages/coding-agent)
  ↓ auto-retry (transient) or auto-compaction (overflow)
UI Layer (packages/tui / web-ui)
  ↓ displays error to user
```

### Agent Loop Error Handling

In `Agent._runLoop()` (`agent.ts:467-498`):

```typescript
catch (err) {
  const errorMsg = {
    role: "assistant",
    stopReason: this.abortController?.signal.aborted ? "aborted" : "error",
    errorMessage: err.message,
    // ... zero usage
  };
  this.appendMessage(errorMsg);
  this._state.error = err.message;
  this.emit({ type: "agent_end", messages: [errorMsg] });
}
```

### Auto-Retry (Transient Errors)

`AgentSession._handleRetryableError()` (`agent-session.ts:2041-2111`):

- Matches: overloaded, rate limit, 429, 500-504, service unavailable, connection errors, fetch failed
- Uses **exponential backoff**: `baseDelayMs * 2^(attempt-1)`
- Configurable max retries via settings
- Removes error message from agent state, keeps in session history
- Retries via `agent.continue()` in a `setTimeout()`
- Reset counter on first successful response

### Auto-Compaction (Context Overflow)

`AgentSession._checkCompaction()` (`agent-session.ts:1500-1556`):

1. **Overflow case**: LLM returned context overflow error → remove error from state → compact → auto-retry
2. **Threshold case**: Context tokens exceed threshold → compact → no retry (user continues)

### Context Overflow Detection

Uses `isContextOverflow(message, contextWindow)` from `pi-ai` to distinguish overflow errors from other errors.

### Extension Error Handling

Extensions have isolated error handling:
- Each extension handler runs in a try/catch
- Errors are emitted via `ExtensionRunner.emitError()` → `ExtensionErrorListener`
- Extension failures don't crash the agent loop
- The `EventBus` wraps handlers with error catching (`event-bus.ts:19-23`)

### Dynamic API Key Resolution

`getApiKey` is called before each LLM request (`agent-loop.ts:230-231`), allowing recovery from expired OAuth tokens:

```typescript
const resolvedApiKey = config.getApiKey
  ? await config.getApiKey(config.model.provider)
  : config.apiKey;
```
