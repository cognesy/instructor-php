# Pi-Mono vs Instructor PHP Agents: Detailed Comparison

## 1. Agent Loop Design

| Aspect | Pi-Mono | Instructor PHP |
|--------|---------|----------------|
| **File** | `packages/agent/src/agent-loop.ts` | `packages/agents/src/Core/AgentLoop.php` |
| **Architecture** | Dual-loop (outer: follow-ups, inner: tool calls + steering) | Single loop with hook intercepts |
| **Entry Points** | `agentLoop()` (fresh) / `agentLoopContinue()` (resume) | `execute()` (blocking) / `iterate()` (generator) |
| **Iteration Model** | Async with `AbortSignal` propagation | PHP Generator yielding `AgentState` per step |
| **Async Message Injection** | `agent.steer()` for mid-execution, `agent.followUp()` for post-turn | Hooks can inject messages by modifying `AgentState` at lifecycle points |
| **External Interruption** | Steering queue allows external code to inject messages while agent runs | No async queue - hooks are synchronous intercepts |

**Key Difference**: Pi-Mono's steering/follow-up queues are designed for **async external injection** (e.g., user typing while agent processes). Instructor's hooks are **synchronous lifecycle intercepts** that can fully modify state (including messages) but only at defined points. Both can inject - the patterns differ in timing and use case.

---

## 2. Tools

| Aspect | Pi-Mono | Instructor PHP |
|--------|---------|----------------|
| **Definition** | `AgentTool` interface with TypeBox schema | `ToolInterface` with `__invoke()` + reflection |
| **Schema** | Explicit TypeBox (`Type.Object({...})`) | Auto-generated from method signature via `StructureFactory` |
| **Streaming Updates** | `onUpdate` callback during execution | No streaming - result returned at end |
| **Built-in Tools** | `read`, `bash`, `edit`, `write`, `grep`, `find`, `ls` | `UseBash`, `UseFileTools`, `UseSkills`, `UseSubagents`, etc. (capabilities) |
| **Tool Result** | `AgentToolResult<TDetails>` with content + details | `Result` (Success/Failure monad) |

**Pi-Mono** tools support streaming progress updates. **Instructor** uses PHP reflection for zero-config schema generation.

---

## 3. Hooks / Lifecycle Intercepts

| Aspect | Pi-Mono | Instructor PHP |
|--------|---------|----------------|
| **System** | Extension-based event handlers | `HookStack` with priority ordering |
| **Hook Points** | 15+ events (session_*, agent_*, turn_*, tool_*, context, input, etc.) | 8 triggers (`BeforeExecution`, `BeforeStep`, `BeforeToolUse`, `AfterToolUse`, `AfterStep`, `OnStop`, `AfterExecution`, `OnError`) |
| **Registration** | Extension activates and returns `on: { event: handler }` | `HookStack->with(hook, triggers, priority)` |
| **State Mutation** | Handlers receive event object, some can modify | `HookContext->withState(AgentState)` returns new context with replaced state - **full state control** |
| **Message Injection** | Via `context` event or steering queue | Via `withState()` - hooks can add/modify/remove messages in `AgentContext` |
| **Tool Blocking** | Extensions can return custom tool result | `HookContext->withToolExecutionBlocked(message)` |
| **Priority** | Not explicit - extension load order | Numeric priority (lower numbers run later) |

**Pi-Mono** has more granular events (session lifecycle, model changes, tree navigation). **Instructor** hooks have **full state modification capability** via immutable `with*()` pattern - they can inject messages, modify metadata, change system prompt, or replace the entire context.

---

## 4. Skills and Extension Points

### 4.1 Capabilities (Instructor PHP only)

Instructor PHP has a **Capabilities** system with no direct equivalent in Pi-Mono:

```php
interface AgentCapability {
    public function install(AgentBuilder $builder): void;
}
```

Capabilities are composable bundles that can install any combination of:
- **Tools** - via `$builder->withTools()`
- **Hooks** - via `$builder->addHook()` with priority
- **Configuration** - via builder mutators

**Built-in Capabilities:**
| Capability | What it installs |
|------------|------------------|
| `UseBash` | `BashTool` with execution policy |
| `UseFileTools` | File read/write/list tools |
| `UseSummarization` | `MoveMessagesToBufferHook` + `SummarizeBufferHook` (context compaction) |
| `UseSkills` | `LoadSkillTool` + `AppendSkillMetadataHook` |
| `UseSubagents` | Subagent spawning tools |
| `UseTaskPlanning` | Task/todo management tools |
| `UseMetadataTools` | Metadata persistence tools |
| `UseSelfCritique` | Self-review hooks |
| `UseStructuredOutputs` | JSON output validation |
| `UseToolRegistry` | Dynamic tool discovery via registry |

**Usage pattern:**
```php
AgentBuilder::base()
    ->withCapability(new UseBash($policy))
    ->withCapability(new UseSummarization($policy))
    ->withCapability(new UseSkills($library))
    ->build();
```

**Pi-Mono has no equivalent** - extensions register tools/handlers individually, not as composable bundles.

### 4.2 Extensions Comparison

| Aspect | Pi-Mono | Instructor PHP |
|--------|---------|----------------|
| **Extension Model** | ES modules with `ExtensionAPI` registration | `AgentCapability` interface with `install(builder)` |
| **Entry Point** | `export default (api: ExtensionAPI) => { ... }` | `public function install(AgentBuilder $builder): void` |
| **Provides** | tools, commands, shortcuts, flags, event handlers, UI widgets, custom editors, providers | tools, hooks, configuration |
| **Composability** | Extensions are loaded independently | Capabilities are composable, can be combined freely |
| **Runtime Actions** | `api.on()`, `api.registerTool()`, `api.registerCommand()`, etc. | Builder pattern at construction time |

### 4.3 Skills Comparison

| Aspect | Pi-Mono | Instructor PHP |
|--------|---------|----------------|
| **Skill Format** | Markdown + YAML frontmatter (`SKILL.md`) | Markdown + YAML frontmatter (`SKILL.md`) |
| **Skill Structure** | `name`, `description`, `disable-model-invocation` | `name`, `description`, `body`, `resources` |
| **Discovery** | Filesystem scan: `.md` in root, `SKILL.md` in subdirs | Filesystem scan: `SKILL.md` in subdirs |
| **Locations** | `~/.pi/agent/skills/`, `$CWD/.pi/skills/`, explicit paths | Configurable `skillsPath` directory |
| **Loading** | Injected into system prompt at session start | Dynamic via `LoadSkillTool` - agent requests skills at runtime |
| **Resources** | Referenced via relative paths in skill content | `scripts/`, `references/`, `assets/` folders auto-discovered |
| **Invocation Control** | `disable-model-invocation: true` hides from prompt (command-only) | Agent decides when to load via tool |

**Key Difference**: Pi-Mono skills are **prompt-injected** (all visible skills added to system prompt). Instructor skills are **tool-loaded** (agent explicitly requests skills via `LoadSkillTool`, keeping context leaner).

---

## 5. Error Handling

| Aspect | Pi-Mono | Instructor PHP |
|--------|---------|----------------|
| **LLM Errors** | `stopReason: "error"`, error in `AssistantMessage.errorMessage` | Wrapped in `AgentException`, passed to `OnError` hook |
| **Tool Errors** | Caught -> `ToolResultMessage` with `isError: true` -> sent to LLM | `ToolExecutionException` -> `Failure` result -> optionally thrown |
| **Abort** | `AbortSignal` propagates everywhere, `stopReason: "aborted"` | No equivalent signal propagation |
| **Retry** | `agent.continue()` resumes from last valid state | `forNextExecution()` clears execution, keeps context |
| **Error Collection** | Single error per message | `ErrorList` aggregated across steps and execution |
| **Blocking** | Extensions can intercept and skip remaining tools | `ToolExecutionBlockedException` creates blocked `ToolExecution` |

**Pi-Mono** has `AbortSignal` propagation throughout. **Instructor** has richer error aggregation with `ErrorList`.

---

## 6. Agent State Data Model

| Aspect | Pi-Mono | Instructor PHP |
|--------|---------|----------------|
| **Core State** | `AgentState` interface | `AgentState` class (readonly) |
| **Fields** | `systemPrompt`, `model`, `thinkingLevel`, `tools`, `messages`, `isStreaming`, `streamMessage`, `pendingToolCalls` | `agentId`, `parentAgentId`, `createdAt`, `updatedAt`, `context`, `execution` |
| **Streaming State** | `isStreaming`, `streamMessage` (partial during stream) | No streaming state - steps are atomic |
| **Execution State** | Embedded in same object | Separate `ExecutionState` (nullable between runs) |
| **Immutability** | Mutable via setter methods | Fully immutable (readonly + `with*()` methods) |

**Pi-Mono** state is mutable with streaming support. **Instructor** state is immutable with clear session/execution separation.

---

## 7. Agent Context Data Model

| Aspect | Pi-Mono | Instructor PHP |
|--------|---------|----------------|
| **Message Types** | `UserMessage`, `AssistantMessage`, `ToolResultMessage` + custom via declaration merging | Universal `Message` class with `role`, `name`, `Content`, `Metadata` |
| **Message Content** | Arrays of `TextContent`, `ImageContent`, `ToolCall`, `ThinkingContent` | `Content` containing `ContentParts` - supports text, image_url, file, input_audio |
| **Custom Messages** | TypeScript declaration merging for extension types | Same `Message` type for all roles - differentiated by `role` field and routing to sections |
| **Message Storage** | Single flat `messages: AgentMessage[]` array | `MessageStore` with named `Sections`, each section contains `Messages` collection |
| **Sections** | N/A - single array | Named sections: `messages`, `buffer`, `summary`, `execution_buffer` - ordered for inference |
| **Metadata** | Not in core - extensions handle | Two levels: `AgentContext->metadata()` (context-level) + `Message->metadata()` (per-message) |
| **System Prompt** | `AgentState.systemPrompt` string | `AgentContext->systemPrompt` string |

**Pi-Mono** uses TypeScript union types and declaration merging for message extensibility. **Instructor** uses a unified `Message` class with rich `Content`/`ContentPart` hierarchy supporting multimodal content (text, images, audio, files), plus `MessageStore` with named sections for sophisticated context management and compaction.

---

## 8. Context Compaction Mechanism

| Aspect | Pi-Mono | Instructor PHP |
|--------|---------|----------------|
| **Trigger** | Context overflow detection (`isContextOverflow()`) or manual | Token threshold hooks (`maxMessageTokens`, `maxBufferTokens`) |
| **Process** | Extract messages -> LLM summarization -> `CompactionEntry` | Two-phase: move-to-buffer -> summarize-buffer |
| **Hooks** | `session_before_compact` (customizable) | Separate `MoveMessagesToBufferHook` + `SummarizeBufferHook` |
| **Output** | `CompactionResult` with summary + file operation tracking | Summary stored in `SUMMARY_SECTION` |
| **File Tracking** | Tracks `readFiles`, `modifiedFiles` in `CompactionDetails` | Not tracked - pure message summarization |
| **Configuration** | Extension-based customization | `SummarizationPolicy` with thresholds |

**Pi-Mono** tracks file operations through compaction for context recovery. **Instructor** uses a cleaner two-phase buffer system with proactive token management.

---

## 9. Tool Discovery Mechanism

| Aspect | Pi-Mono | Instructor PHP |
|--------|---------|----------------|
| **Registration** | `createAllTools(cwd)` + extension tools via `getTools()` | `ToolRegistry` with instances + factories |
| **Lazy Loading** | Tools created at session start | Factory pattern - instantiated on first `resolve()` |
| **Metadata Levels** | Single level (tool has schema + description) | Three levels: metadata (browse) -> fullSpec (docs) -> instance (execute) |
| **Search** | Skills via frontmatter metadata | `ToolRegistry->search(query)` full-text across name/description/namespace/tags |
| **Dynamic Discovery** | Extensions via `resources_discover` event | `ToolsTool` exposes registry to agent, `LoadSkillTool` for dynamic loading |
| **Wrapping** | `wrapToolsWithExtensions()` applies extension behavior | Hooks intercept tool execution (no wrapping) |

**Instructor** has more sophisticated discovery with three metadata levels and full-text search. **Pi-Mono** relies on extensions for discovery.

---

## 10. Serialization and Suspend/Resume

| Aspect | Pi-Mono | Instructor PHP |
|--------|---------|----------------|
| **Format** | JSONL with typed entries | JSON via `toArray()` / `fromArray()` + JSONL via `JsonlStorage` |
| **Session File** | Header + entries (messages, model changes, compaction, labels, etc.) | `CanStoreMessages` with `InMemoryStorage` and `JsonlStorage` |
| **Entry Types** | `SessionMessageEntry`, `ModelChangeEntry`, `CompactionEntry`, `BranchSummaryEntry`, `LabelEntry`, `CustomEntry` | `session`, `message`, `label` entry types in JSONL |
| **Message Identity** | 8-char hex ID + parentId chain | UUID `Message::$id` + `Message::$parentId` chain |
| **Tree Structure** | Parent/leaf session branching with `fork()` / `navigateTree()` | `CanStoreMessages::fork()` / `navigateTo()` / `getPath()` |
| **Resume** | `agentLoopContinue()` validates last message role | `forNextExecution()` clears execution, `iterate()` continues |
| **Branch Summaries** | Preserved when switching branches | Not yet implemented |
| **Checkpoints** | Labels as user-defined bookmarks | `addLabel()` / `getLabels()` on storage |

**Both** now support session trees with branching via parentId chains. **Pi-Mono** has more entry types (model changes, compaction entries). **Instructor** has `StoreMessagesResult` for save operation feedback.

---

## Summary Matrix

| Dimension | Pi-Mono Advantage | Instructor PHP Advantage |
|-----------|-------------------|-------------------------|
| **Loop** | Async steering + follow-up queues for external injection | Generator-based streaming for step observation |
| **Tools** | Streaming progress updates via `onUpdate` callback | Reflection-based schema generation (zero config) |
| **Hooks** | More granular events (15+), session lifecycle events | Full state mutation, explicit priority, immutable pattern |
| **Capabilities** | No equivalent - extensions register individually | Composable bundles (tools + hooks + config) via `AgentCapability` |
| **Skills** | Markdown+frontmatter, prompt-injected at session start | Markdown+frontmatter, tool-loaded on demand via `LoadSkillTool` |
| **Errors** | `AbortSignal` propagation throughout | `ErrorList` aggregation across steps |
| **State** | Streaming state tracking (`isStreaming`, `streamMessage`) | Immutable, clear session/execution separation |
| **Context** | Declaration merging for custom message types | `MessageStore` with named sections + rich `Message`/`Content`/`ContentPart` hierarchy |
| **Compaction** | File operation tracking for context recovery | Two-phase buffer system with proactive token management |
| **Discovery** | Extension-driven with `resources_discover` | Three-level metadata + full-text search |
| **Suspend** | Session tree with branching and forking | Session tree via `CanStoreMessages` + `JsonlStorage` with branching |

---

## Key Architectural Differences

1. **Language paradigm**: Pi-Mono uses TypeScript's type system (declaration merging, generics) vs Instructor's PHP readonly classes and interfaces
2. **Mutability**: Pi-Mono is mutable with streaming state; Instructor is fully immutable with `with*()` pattern
3. **Session model**: Both now support tree-structured sessions with branching via `parentId` chains; Pi-Mono stores more entry types (model changes, compaction)
4. **Extension model**: Pi-Mono uses ES module registration pattern; Instructor uses **Capabilities** - composable bundles that install tools + hooks + config
5. **Tool execution**: Pi-Mono supports streaming tool updates; Instructor is atomic per-tool
6. **Message injection**: Pi-Mono uses async queues (steer/followUp); Instructor uses synchronous hooks with full state replacement
7. **Skill loading**: Pi-Mono injects skills into system prompt at session start; Instructor loads skills on-demand via `LoadSkillTool`
8. **Message identity**: Both assign IDs to messages; Pi-Mono uses 8-char hex, Instructor uses UUID

---

## Key File References

### Pi-Mono
- Agent loop: `packages/agent/src/agent-loop.ts`
- Agent class: `packages/agent/src/agent.ts`
- Types: `packages/agent/src/types.ts`
- Tools: `packages/coding-agent/src/core/tools/`
- Extensions: `packages/coding-agent/src/core/extensions/types.ts`
- Session manager: `packages/coding-agent/src/core/session-manager.ts`
- Compaction: `packages/coding-agent/src/core/compaction/compaction.ts`
- Skills: `packages/coding-agent/src/core/skills.ts`

### Instructor PHP
- Agent loop: `packages/agents/src/Core/AgentLoop.php`
- Agent state: `packages/agents/src/Core/Data/AgentState.php`
- Agent context: `packages/agents/src/Core/Context/AgentContext.php`
- Hook context: `packages/agents/src/Hooks/Data/HookContext.php`
- Hook interface: `packages/agents/src/Hooks/Contracts/HookInterface.php`
- Hook stack: `packages/agents/src/Hooks/Interceptors/HookStack.php`
- Tools: `packages/agents/src/Core/Tools/`
- Tool registry: `packages/agents/src/AgentBuilder/Capabilities/Tools/ToolRegistry.php`
- Summarization: `packages/agents/src/AgentBuilder/Capabilities/Summarization/`
- Capability interface: `packages/agents/src/AgentBuilder/Contracts/AgentCapability.php`
- Capabilities: `packages/agents/src/AgentBuilder/Capabilities/`
- UseBash: `packages/agents/src/AgentBuilder/Capabilities/Bash/UseBash.php`
- UseSummarization: `packages/agents/src/AgentBuilder/Capabilities/Summarization/UseSummarization.php`
- UseSkills: `packages/agents/src/AgentBuilder/Capabilities/Skills/UseSkills.php`
- SkillLibrary: `packages/agents/src/AgentBuilder/Capabilities/Skills/SkillLibrary.php`
- Skill: `packages/agents/src/AgentBuilder/Capabilities/Skills/Skill.php`
- LoadSkillTool: `packages/agents/src/AgentBuilder/Capabilities/Skills/LoadSkillTool.php`
- Message: `packages/messages/src/Message.php`
- Messages collection: `packages/messages/src/Messages.php`
- Content/ContentPart: `packages/messages/src/Content.php`, `packages/messages/src/ContentPart.php`
- MessageStore: `packages/messages/src/MessageStore/MessageStore.php`
- Storage contract: `packages/messages/src/MessageStore/Contracts/CanStoreMessages.php`
- InMemoryStorage: `packages/messages/src/MessageStore/Storage/InMemoryStorage.php`
- JsonlStorage: `packages/messages/src/MessageStore/Storage/JsonlStorage.php`
- StoreMessagesResult: `packages/messages/src/MessageStore/Data/StoreMessagesResult.php`
- Messages cheatsheet: `packages/messages/CHEATSHEET.md`
