# Agents Package — Codebase Cheatsheet

Root namespace: `Cognesy\Agents`

---

## A. Core Agent Loop

| Class / Interface | Namespace | Role |
|---|---|---|
| `AgentLoop` | `Cognesy\Agents` | Main orchestrator. Iterates steps: infer → tool-use → evaluate stop. `readonly class`. Constructor: `(Tools, CanExecuteToolCalls, CanUseTools, CanHandleEvents, CanInterceptAgentLifecycle)`. Static `default()` factory. |
| `CanControlAgentLoop` | `Cognesy\Agents` | Interface with `execute(AgentState): AgentState` and `iterate(AgentState): iterable<AgentState>`. |

**AgentLoop lifecycle hooks** (called internally via `CanInterceptAgentLifecycle`):
`onBeforeExecution` → `onBeforeStep` → `handleToolUse` → `onAfterStep` → `shouldStop` → `onStop` → `onAfterExecution` → `onError`

**AgentLoop public API**: `execute()`, `iterate()`, `wiretap()`, `onEvent()`, `tools()`, `driver()`, `toolExecutor()`, `eventHandler()`, `interceptor()`, `withTool()`, `withTools()`, `withDriver()`, `withToolExecutor()`, `withInterceptor()`, `withEventHandler()`, `with()`

---

## B. State & Data (`\Data`)

| Class | Role |
|---|---|
| `AgentState` | Immutable session+execution state. Named constructor: `empty()`. Key setup mutators: `withUserMessage()`, `withSystemPrompt()`, `withLLMConfig()`, `withMetadata()`. Key output accessors: `finalResponse()`, `currentResponse()`, `usage()`, `stepCount()`, `errors()`, `shouldStop()`. `forNextExecution()` resets execution while preserving session — called automatically by `AgentLoop` for terminal states. Serializable via `toArray()`/`fromArray()`. |
| `ExecutionState` | Per-execution transient data: `executionId`, `status`, steps, continuation, timing. `fresh()` factory creates new UUID. |
| `AgentStep` | Single step result: `id`, `stepType: AgentStepType`, `outputMessages`, `toolExecutions`, `errors`, `usage`, `finishReason`. |
| `StepExecution` | Wraps `AgentStep` with timing (`startedAt`, `completedAt`, `duration`) and continuation state. |
| `ToolExecution` | Records one tool call result. Wraps `ToolCall` + `Result` + timing (`startedAt`, `completedAt`). `wasBlocked()`, `hasError()`, `error()`, `errorMessage()`, `value()` are computed from `Result`. `blocked()` static factory for hook-blocked calls. |
| `ExecutionBudget` | Per-execution resource limits declared on `AgentDefinition`: `?maxSteps`, `?maxTokens`, `?maxSeconds`, `?maxCost`, `?deadline`. `unlimited()`, `isEmpty()`, `isExhausted()`. Applied as `UseGuards` when the agent loop is built. |

---

## C. Collections (`\Collections`)

| Class | Wraps |
|---|---|
| `Tools` | `ToolInterface[]` — `withTool()`, `merge()`, `names()`, `toToolSchemas()` |
| `AgentSteps` | `AgentStep[]` |
| `StepExecutions` | `StepExecution[]` — `last()`, `steps()`, `count()` |
| `ToolExecutions` | `ToolExecution[]` — `none()`, `all()` |
| `ErrorList` | `Throwable[]` — `withAppendedExceptions()`, `hasError()`, `count()` |
| `NameList` | `string[]` — used for tool/skill/capability name lists |

---

## D. Enums (`\Enums`)

| Enum | Values |
|---|---|
| `AgentStepType` | `ToolExecution`, `FinalResponse`, `Error` |
| `ExecutionStatus` | `Pending`, `InProgress`, `Completed`, `Failed` |

---

## E. Context (`\Context`)

| Class / Interface | Role |
|---|---|
| `AgentContext` | Holds `MessageStore`, `Metadata`, `systemPrompt`, `responseFormat`. Messages live in `ContextSections::DEFAULT` section of the store. `withMessages()`, `withAppendedMessages()`, `withSystemPrompt()`, `toCachedContext()`. |
| `ContextSections` | Enum/constants defining message store section names (e.g. `DEFAULT`). |
| `CanCompileMessages` | Interface: `compile(AgentState): Messages` — transforms stored messages for LLM inference. |
| `CanAcceptMessageCompiler` | Interface: drivers implement to receive a compiler. |

### Compilers (`\Context\Compilers`)

| Compiler | Behavior |
|---|---|
| `ConversationWithCurrentToolTrace` | Default. Includes non-trace messages + current execution's trace messages (metadata-driven filtering). |
| `AllSections` | Returns all messages from all sections. |
| `SelectedSections` | Returns messages from specified sections only. |

---

## F. Continuation & Stop (`\Continuation`)

| Class / Enum | Role |
|---|---|
| `StopReason` | Enum: `Completed`, `StepsLimitReached`, `TokenLimitReached`, `TimeLimitReached`, `RetryLimitReached`, `ErrorForbade`, `StopRequested`, `FinishReasonReceived`, `UserRequested`, `Unknown`. Has `priority()` for comparison. |
| `StopSignal` | Value object: `reason: StopReason`, `message`, `context[]`, `?source`. Created from `AgentStopException`. |
| `StopSignals` | Collection of `StopSignal`. `first()` returns highest-priority. |
| `ExecutionContinuation` | Tracks whether execution should continue or stop. Holds `StopSignals`. `shouldStop()`, `explain()`. |
| `AgentStopException` | Thrown by tools/hooks to signal stop. Carries `context[]` and `?source`. |

---

## G. Tools (`\Tool`)

### Contracts (`\Tool\Contracts`)

| Interface | Role |
|---|---|
| `ToolInterface` | `use(mixed...): Result`, `toToolSchema(): array`, `descriptor(): CanDescribeTool` |
| `CanDescribeTool` | `name()`, `description()`, `metadata()`, `instructions()` |
| `CanExecuteToolCalls` | `execute(AgentState, ToolCall[]): AgentStep` |
| `CanAccessAgentState` | `withAgentState(AgentState): static` — tools receive state before execution |
| `CanAccessToolCall` | `withToolCall(ToolCall): static` |
| `CanManageTools` | Tool registry contract |

### Implementations (`\Tool\Tools`)

Class hierarchy:
```
SimpleTool               – base; implements ToolInterface + CanDescribeTool; manual schema; HasArgs ($this->arg())
├── ReflectiveSchemaTool – adds HasReflectiveSchema (auto-schema from typed __invoke())
│   └── FunctionTool     – wraps any callable; fromCallable() factory
└── StateAwareTool       – adds HasAgentState ($this->agentState)
    ├── BaseTool         – agent state + manual toToolSchema()  ← use for most custom tools
    └── ContextAwareTool – manual schema + agent state + HasToolCall ($this->toolCall)
```

| Class | Role |
|---|---|
| `SimpleTool` | Lowest-level abstract. Manual `toToolSchema()` required. Provides `$this->arg(args, name, pos, default)` via `HasArgs`. Built-in tools (`BashTool`, `ReadFileTool`, etc.) extend this. |
| `ReflectiveSchemaTool` | Adds `HasReflectiveSchema`: `toToolSchema()` auto-generated from typed `__invoke()` params via `StructureFactory`. |
| `StateAwareTool` | Extends `SimpleTool`. Adds `$this->agentState` via `HasAgentState`. Manual schema still required. |
| `BaseTool` | Extends `StateAwareTool` + `HasReflectiveSchema`. Agent state + `toToolSchema()` required (PHP prevents typed `__invoke()` override). Use `mixed ...$args` + `$this->arg()`. **Recommended starting point.** |
| `ContextAwareTool` | Extends `StateAwareTool` + `CanAccessToolCall`. Adds `$this->toolCall` (raw `ToolCall` with ID and unparsed args). Requires manual `toToolSchema()`. Use when you need the raw tool call context. |
| `FunctionTool` | Extends `ReflectiveSchemaTool`. Wraps any `callable`. `fromCallable(callable)` factory. Schema inferred from function signature. |
| `MockTool` | For testing. `MockTool::returning(name, desc, result)` or `new MockTool(name, desc, fn)`. |

### Descriptors (`\Tool`)

| Class | Role |
|---|---|
| `ToolDescriptor` | Readonly data class implementing `CanDescribeTool`. Holds `name`, `description`, `metadata[]`, `instructions[]`. Used by `SimpleTool` constructor and externalized descriptors. |

### Execution

| Class | Role |
|---|---|
| `ToolExecutor` | Implements `CanExecuteToolCalls`. Executes tool calls, handles errors, dispatches events, respects `CanInterceptAgentLifecycle` for before/after tool use hooks. |
| `ToolRegistry` | Implements `CanManageTools`. Stores tools by name. |

---

## H. Drivers (`\Drivers`)

| Interface / Class | Role |
|---|---|
| `CanUseTools` | Interface: `useTools(AgentState $state): AgentState` |
| `ToolCallingDriver` | Default driver. Sends inference request with tool schemas, processes response, delegates tool execution. Uses `CanCompileMessages` to build context. |
| `ToolExecutionFormatter` | Formats tool execution results as messages. |
| `ReActDriver` | Alternative driver using ReAct (Reason+Act) prompting pattern. |
| `FakeAgentDriver` | Testing driver. Replays `ScenarioStep[]` sequences. |
| `ScenarioStep` | Test data for `FakeAgentDriver`. |

### ReAct sub-namespace (`\Drivers\ReAct`)
`ReActDriver`, `MakeReActPrompt`, `MakeToolCalls`, `ReActFormatter`, `ReActValidator`, `Decision` (interface), `ReActDecision`, `DecisionWithDetails`.

---

## I. Hook System (`\Hook`)

### Contracts & Data

| Class / Interface | Namespace | Role |
|---|---|---|
| `HookInterface` | `\Hook\Contracts` | `handle(HookContext): HookContext` |
| `HookTrigger` | `\Hook\Enums` | Enum: `BeforeExecution`, `BeforeStep`, `BeforeToolUse`, `AfterToolUse`, `AfterStep`, `OnStop`, `AfterExecution`, `OnError` |
| `HookContext` | `\Hook\Data` | Carries `triggerType`, `state`, `?toolCall`, `?toolExecution`, `errorList`, `metadata`. Factory methods: `beforeExecution()`, `beforeStep()`, `beforeToolUse()`, `afterToolUse()`, `afterStep()`, `onStop()`, `afterExecution()`, `onError()`. Mutation: `withState()`, `withToolExecutionBlocked()`, `withError()`. |
| `RegisteredHook` | `\Hook\Data` | Wraps `HookInterface` + `HookTriggers` + `priority` + `?name`. |
| `HookTriggers` | `\Hook\Collections` | Collection of `HookTrigger` values. |
| `RegisteredHooks` | `\Hook\Collections` | Sorted collection of `RegisteredHook` (by priority). |

### Execution

| Class | Role |
|---|---|
| `HookStack` | Implements `CanInterceptAgentLifecycle`. Runs registered hooks matching trigger type in priority order. Dispatches `HookExecuted` events. |

### Built-in Hooks (`\Hook\Hooks`)

| Hook | Trigger | Purpose |
|---|---|---|
| `CallableHook` | any | Wraps a `Closure(HookContext): HookContext`. |
| `StepsLimitHook` | `BeforeStep` | Throws `AgentStopException` when step count exceeds limit. |
| `TokenUsageLimitHook` | `AfterStep` | Throws `AgentStopException` when token usage exceeds limit. |
| `ExecutionTimeLimitHook` | `BeforeStep` | Throws `AgentStopException` when execution time exceeds limit. |
| `FinishReasonHook` | `AfterStep` | Throws `AgentStopException` when LLM finish reason is `stop` (no more tool calls). |
| `ApplyContextConfigHook` | `BeforeExecution` | Applies context config (system prompt, response format) to state. |

---

## J. Interception (`\Interception`)

| Interface / Class | Role |
|---|---|
| `CanInterceptAgentLifecycle` | `intercept(HookContext): HookContext` |
| `PassThroughInterceptor` | No-op implementation (returns context unchanged). |

---

## K. Events (`\Events`)

| Event | When |
|---|---|
| `AgentExecutionStarted` | Before first step |
| `AgentStepStarted` | Before each step |
| `AgentStepCompleted` | After each step (includes usage, duration) |
| `AgentExecutionStopped` | When stop signal triggers |
| `AgentExecutionCompleted` | After execution finishes (success or failure) |
| `AgentExecutionFailed` | On error |
| `AgentStateUpdated` | State changed |
| `ContinuationEvaluated` | After shouldStop() check |
| `StopSignalReceived` | When stop signal captured |
| `TokenUsageReported` | After step with token usage > 0 |
| `ToolCallStarted` | Before tool execution |
| `ToolCallCompleted` | After tool execution |
| `ToolCallBlocked` | Tool blocked by hook |
| `InferenceRequestStarted` | Before LLM call |
| `InferenceResponseReceived` | After LLM response |
| `SubagentSpawning` | Subagent about to start |
| `SubagentCompleted` | Subagent finished |
| `HookExecuted` | After each hook runs |
| `DecisionExtractionFailed` | ReAct decision parsing failed |
| `ValidationFailed` | Validation error |

`AgentEvent` — base class. `AgentEventConsoleObserver` (`\Events\Support`) — convenience listener for console output.

---

## L. Exceptions (`\Exceptions`)

`AgentException`, `AgentNotFoundException`, `InvalidToolException`, `InvalidToolArgumentsException`, `ToolCallBlockedException`, `ToolExecutionBlockedException`, `ToolExecutionException`.

---

## M. Builder (`\Builder`)

| Class / Interface | Role |
|---|---|
| `AgentBuilder` | Entry point. `base(?events)` factory. `withCapability(CanProvideAgentCapability): self`. `build(): AgentLoop`. |
| `AgentConfigurator` | Internal. Accumulates tools, compiler, driver, hooks, deferred tools. `install(capability)`, `toAgentLoop()`. |
| `CanComposeAgentLoop` | Interface: `withCapability()`, `build()` |
| `CanProvideAgentCapability` | Interface: `capabilityName(): string`, `configure(CanConfigureAgent): CanConfigureAgent` |
| `CanConfigureAgent` | Interface: access/mutate tools, contextCompiler, toolUseDriver, hooks, deferredTools |
| `CanProvideDeferredTools` | Interface for tools resolved at build time |
| `DeferredToolProviders` | Collection of deferred tool providers (`\Builder\Collections`) |
| `DeferredToolContext` | Context passed to deferred tool resolution (`\Builder\Data`) |

---

## N. Capabilities (`\Capability`)

Capabilities implement `CanProvideAgentCapability` and configure the agent via `configure(CanConfigureAgent)`.

### Core (`\Capability\Core`)

| Capability | Purpose |
|---|---|
| `UseTools` | Adds `ToolInterface[]` to agent |
| `UseToolFactory` | Adds tools via `CanProvideDeferredTools` |
| `UseHook` | Registers a single hook with triggers and priority |
| `UseGuards` | Registers guard hooks (steps limit, token limit, time limit, finish reason) from `AgentBudget` |
| `UseDriver` | Sets the `CanUseTools` driver |
| `UseDriverDecorator` | Wraps existing driver with decorator |
| `UseContextCompiler` | Sets the `CanCompileMessages` compiler |
| `UseContextCompilerDecorator` | Wraps existing compiler |
| `UseContextConfig` | Sets system prompt and/or response format via `ApplyContextConfigHook` |
| `UseLLMConfig` | Configures LLM provider settings |

### Domain Capabilities

| Capability | Namespace | What it adds |
|---|---|---|
| `UseBash` | `\Capability\Bash` | `BashTool` + `BashPolicy` |
| `UseFileTools` | `\Capability\File` | `ReadFileTool`, `WriteFileTool`, `EditFileTool`, `ListDirTool`, `SearchFilesTool` |
| `UseMetadataTools` | `\Capability\Metadata` | `MetadataReadTool`, `MetadataWriteTool`, `MetadataListTool` + `PersistMetadataHook` |
| `UseSelfCritique` | `\Capability\SelfCritique` | `SelfCriticSubagentTool` + `SelfCriticHook` |
| `UseSkills` | `\Capability\Skills` | `LoadSkillTool` + `AppendSkillMetadataHook` + `SkillLibrary` |
| `UseSubagents` | `\Capability\Subagent` | `SpawnSubagentTool`, `ResearchSubagentTool` + `SubagentPolicy` |
| `UseStructuredOutputs` | `\Capability\StructuredOutput` | `StructuredOutputTool` + `PersistStructuredOutputHook` + `SchemaRegistry` |
| `UseSummarization` | `\Capability\Summarization` | `MoveMessagesToBufferHook` + `SummarizeBufferHook` + `SummarizationPolicy` |
| `UseTaskPlanning` | `\Capability\Tasks` | `TodoWriteTool` + `PersistTasksHook` + `TodoReminderHook` + `TodoRenderHook` |
| `UseToolRegistry` | `\Capability\Tools` | `ToolsTool` (tool discovery/listing for LLM) |

### Capability Registry

| Class | Namespace | Role |
|---|---|---|
| `AgentCapabilityRegistry` | `\Capability` | Maps capability names → `CanProvideAgentCapability` instances. Used by templates. |
| `CanManageAgentCapabilities` | `\Capability` | Interface for capability registry. |

---

## O. Templates (`\Template`)

| Class / Interface | Role |
|---|---|
| `AgentDefinition` | Data class: `name`, `description`, `systemPrompt`, `?label`, `?llmConfig`, `capabilities: NameList`, `?tools`, `?toolsDeny`, `?skills`, `?budget`, `?metadata`. Serializable. |
| `AgentDefinitionLoader` | Loads definitions from files. Supports `.md`, `.json`, `.yaml`/`.yml` extensions. |
| `AgentDefinitionRegistry` | Stores definitions by name. `register()`, `get()`, `loadFromFile()`, `loadFromDirectory()`, `autoDiscover()`. |
| `CanManageAgentDefinitions` | Interface: `get()`, `all()`, `names()`, `count()` |
| `CanInstantiateAgentLoop` | Interface: creates `AgentLoop` from definition |
| `CanInstantiateAgentState` | Interface: creates `AgentState` from definition |
| `DefinitionLoopFactory` | Builds `AgentLoop` from `AgentDefinition` using `AgentBuilder` + capability registry |
| `DefinitionStateFactory` | Builds `AgentState` from `AgentDefinition` |

### Parsers (`\Template\Parsers`)

| Parser | Format |
|---|---|
| `MarkdownDefinitionParser` | Markdown with YAML frontmatter |
| `JsonDefinitionParser` | JSON |
| `YamlDefinitionParser` | YAML |
| `CanParseAgentDefinition` | Interface: `parse(string): AgentDefinition` |

---

## Q. Sessions (`\Session`)

Persisted, multi-turn agent sessions with optimistic concurrency.

### Core Types

| Class / Interface | Role |
|---|---|
| `AgentSession` | Immutable aggregate: `AgentSessionInfo` + `AgentDefinition` + `AgentState` + `version`. Key accessors: `sessionId(): SessionId`, `status()`, `version()`, `info()`, `definition()`, `state()`. Mutators: `withState()`, `suspended()`, `resumed()`, `completed()`, `failed()`, `deleted()`, `withParentId()`. Serializable. |
| `AgentSessionInfo` | Session metadata. Key accessors: `sessionId(): SessionId`, `parentId(): ?SessionId`, `status(): SessionStatus`, `version(): int`, `agentName()`, `agentLabel()`, `createdAt()`, `updatedAt()`. |
| `SessionId` | Typed identifier. `SessionId::from(string)`, `SessionId::generate()`. Public `$value` property. `__toString()` supported. |
| `SessionStatus` | Enum: `Active`, `Suspended`, `Completed`, `Failed`, `Deleted`. |
| `SessionInfoList` | Collection of `AgentSessionInfo`. |
| `SessionFactory` | Creates new `AgentSession` from `AgentDefinition`. Constructor: `(DefinitionStateFactory)`. `create(AgentDefinition): AgentSession`. |
| `SessionRepository` | Load/create/save sessions. `load(SessionId)`, `create(AgentSession)`, `save(AgentSession)`, `exists(SessionId): bool`, `delete(SessionId): void`, `listHeaders(): SessionInfoList`. Optimistic versioning: `save()` requires version to match stored; returns session with incremented version. |
| `SessionRuntime` | Application service. `execute(SessionId, CanExecuteSessionAction): AgentSession`, `getSession()`, `getSessionInfo()`, `listSessions()`. Emits session events. |

### Contracts

| Interface | Role |
|---|---|
| `CanRunSessionRuntime` | `execute()`, `getSession()`, `getSessionInfo()`, `listSessions()` |
| `CanStoreSessions` | Persistence contract: `load(SessionId)`, `create(AgentSession)`, `save(AgentSession)`, `exists(SessionId)`, `delete(SessionId)`, `listHeaders()` |
| `CanExecuteSessionAction` | `executeOn(AgentSession): AgentSession` |

### Stores (`\Session\Store`)

| Store | Notes |
|---|---|
| `InMemorySessionStore` | For testing / single-process use |
| `FileSessionStore` | JSON files on disk; throws `InvalidSessionFileException` on corrupt data |

### Built-in Actions (`\Session\Actions`)

| Action | Purpose |
|---|---|
| `SendMessage(string, DefinitionLoopFactory)` | Run one agent turn with a new user message |
| `ForkSession(SessionId)` | Clone session into a new branch |
| `ResumeSession` | Set status to `Active` |
| `SuspendSession` | Set status to `Suspended` |
| `ClearSession` | Reset state, keep definition |
| `ChangeModel(string)` | Swap LLM config |
| `ChangeSystemPrompt(string)` | Replace system prompt |
| `WriteMetadata(array)` | Merge metadata into session state |
| `UpdateTask(...)` | Update task in session state |

### Session Events (`\Session\Events`)

`SessionLoaded`, `SessionActionExecuted`, `SessionSaved`, `SessionLoadFailed`, `SessionSaveFailed`

### Exceptions (`\Session\Exceptions`)

`SessionNotFoundException`, `SessionConflictException`, `InvalidSessionFileException`

### Minimal setup

```php
$factory = new SessionFactory(new DefinitionStateFactory());
$repo    = new SessionRepository(new InMemorySessionStore());
$runtime = new SessionRuntime($repo, new EventDispatcher('session-runtime'));

// create
$session = $repo->create($factory->create($definition));

// execute
$updated = $runtime->execute($session->sessionId(), new SendMessage('Hello', $loopFactory));
```

---

## P. Broadcasting (`\Broadcasting`)

| Class / Interface | Role |
|---|---|
| `AgentEventBroadcaster` | Bridges agent events to external event systems (e.g., Laravel). |
| `BroadcastConfig` | Configuration for broadcasting. |
| `CanBroadcastAgentEvents` | Interface. |
