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

**AgentLoop public API**: `execute()`, `iterate()`, `wiretap()`, `onEvent()`, `tools()`, `driver()`, `withTool()`, `withTools()`, `withDriver()`, `withInterceptor()`, `with()`

---

## B. State & Data (`\Data`)

| Class | Role |
|---|---|
| `AgentState` | Immutable session+execution state. Session: `agentId`, `parentAgentId`, `createdAt`, `updatedAt`, `context: AgentContext`, `budget: AgentBudget`. Execution: `?ExecutionState`. Key methods: `withCurrentStep()`, `withStopSignal()`, `withFailure()`, `finalResponse()`, `currentResponse()`, `debug()`. `forNextExecution()` resets execution while preserving session — called automatically by `AgentLoop` for terminal states. Serializable via `toArray()`/`fromArray()`. |
| `ExecutionState` | Per-execution transient data: `executionId`, `status`, steps, continuation, timing. `fresh()` factory creates new UUID. |
| `AgentStep` | Single step result: `id`, `stepType: AgentStepType`, `outputMessages`, `toolExecutions`, `errors`, `usage`, `finishReason`. |
| `StepExecution` | Wraps `AgentStep` with timing (`startedAt`, `completedAt`, `duration`) and continuation state. |
| `ToolExecution` | Records one tool call result: `toolCall`, `result`, `wasBlocked`, `error`. |
| `AgentBudget` | Resource limits: `?maxSteps`, `?maxTokens`, `?maxSeconds`, `?maxCost`, `?deadline`. `unlimited()`, `remaining()`, `cappedBy()`, `isExhausted()`. |

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

| Class | Role |
|---|---|
| `BaseTool` | Abstract base. Implements `ToolInterface + CanDescribeTool + CanAccessAgentState`. Schema auto-generated from `__invoke()` signature via `StructureFactory`. |
| `FunctionTool` | Wraps a `Closure`. `fromCallable(callable)` factory. |
| `MockTool` | For testing. |

### Execution

| Class | Role |
|---|---|
| `ToolExecutor` | Implements `CanExecuteToolCalls`. Executes tool calls, handles errors, dispatches events, respects `CanInterceptAgentLifecycle` for before/after tool use hooks. |
| `ToolRegistry` | Implements `CanManageTools`. Stores tools by name. |

---

## H. Drivers (`\Drivers`)

| Interface / Class | Role |
|---|---|
| `CanUseTools` | Interface: `useTools(AgentState, Tools, CanExecuteToolCalls): AgentState` |
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

`AgentEvent` — base class. `AgentConsoleLogger` (`\Events\Support`) — convenience listener for console output.

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

## P. Broadcasting (`\Broadcasting`)

| Class / Interface | Role |
|---|---|
| `AgentEventBroadcaster` | Bridges agent events to external event systems (e.g., Laravel). |
| `BroadcastConfig` | Configuration for broadcasting. |
| `CanBroadcastAgentEvents` | Interface. |
