# Agents Package Cheatsheet

Root namespace: `Cognesy\Agents`

This file is a quick, code-aligned map of the package surface.
For narrative guidance and examples, use `packages/agents/docs/*.md`.

## 1. Core Loop

- `AgentLoop`
  - main orchestrator
  - key API: `default()`, `execute()`, `iterate()`
  - composition API: `withTool()`, `withTools()`, `withDriver()`, `withToolExecutor()`, `withInterceptor()`, `withEventHandler()`
  - event API: `wiretap()`, `onEvent()`
  - note: terminal executions are auto-reset on entry to `execute()` / `iterate()`
- `CanControlAgentLoop`
  - contract: `execute(AgentState): AgentState`, `iterate(AgentState): iterable`

## 2. State Model

- `Data\AgentState`
  - immutable runtime state
  - common mutators: `empty()`, `withUserMessage()`, `withSystemPrompt()`, `withMetadata()`, `withMessages()`, `withMessageStore()`, `withLLMConfig()`
  - context access: `context()`, `store()`, `messages()`, `metadata()`
  - result access: `finalResponse()`, `currentResponse()`, `hasFinalResponse()`
  - execution access: `execution()`, `status()`, `stepCount()`, `steps()`, `usage()`, `errors()`
  - control: `shouldStop()`, `forNextExecution()`
- `Data\ExecutionState`
  - per-execution transient state (`executionId`, status, steps, continuation)
- `Data\AgentStep`
  - one loop step snapshot (`inputMessages`, `outputMessages`, `inferenceResponse`, `toolExecutions`, `errors`)
- `Data\StepExecution`
  - completed step wrapper with timing
- `Data\ToolExecution`
  - one executed tool call (`value()`, `hasError()`, `errorAsString()`, `wasBlocked()`)
- `Data\ExecutionBudget`
  - optional limits: `maxSteps`, `maxTokens`, `maxSeconds`, `maxCost`, `deadline`

## 3. Collections

- `Collections\Tools`
  - immutable named tool collection
  - key API: `has()`, `get()`, `names()`, `descriptions()`, `withTool()`, `withTools()`, `withToolRemoved()`, `merge()`, `toToolSchema(): ToolDefinitions`
- `Collections\AgentSteps`
- `Collections\StepExecutions`
- `Collections\ToolExecutions`
- `Collections\NameList`

## 4. Tools

### Contracts

- `Tool\Contracts\ToolInterface`
  - `use(mixed ...$args): Result`
  - `toToolSchema(): ToolDefinition`
  - `descriptor(): CanDescribeTool`
- `Tool\Contracts\CanDescribeTool`
  - `name()`, `description()`, `metadata()`, `instructions()`
- `Tool\Contracts\CanExecuteToolCalls`
  - `executeTools(ToolCalls, AgentState): ToolExecutions`
- `Tool\Contracts\CanAccessAgentState`
- `Tool\Contracts\CanAccessToolCall`
- `Tool\Contracts\CanManageTools`

### Base classes

- `Tool\Tools\SimpleTool`
- `Tool\Tools\ReflectiveSchemaTool`
- `Tool\Tools\FunctionTool`
- `Tool\Tools\StateAwareTool`
- `Tool\Tools\BaseTool`
- `Tool\Tools\ContextAwareTool`
- `Tool\Tools\FakeTool`

### Runtime

- `Tool\ToolExecutor`
- `Tool\ToolRegistry`
- `Tool\ToolDescriptor`

## 5. Drivers

- `Drivers\CanUseTools`
- `Drivers\ToolCalling\ToolCallingDriver` (default)
- `Drivers\ReAct\ReActDriver`
- `Drivers\Testing\FakeAgentDriver`
- `Drivers\Testing\ScenarioStep`

## 6. Context

- `Context\AgentContext`
- `Context\CanCompileMessages`
- `Context\CanAcceptMessageCompiler`
- `Context\ContextSections`

Compilers:

- `Context\Compilers\ConversationWithCurrentToolTrace` (default)
- `Context\Compilers\AllSections`
- `Context\Compilers\SelectedSections`

## 7. Continuation / Stop

- `Continuation\StopReason`
- `Continuation\StopSignal`
- `Continuation\StopSignals`
- `Continuation\ExecutionContinuation`
- `Continuation\AgentStopException`

## 8. Hooks / Interception

- `Hook\Contracts\HookInterface`
- `Hook\Data\HookContext`
- `Hook\Enums\HookTrigger`
- `Hook\Collections\HookTriggers`
- `Hook\HookStack`

Built-in hooks:

- `Hook\Hooks\CallableHook`
- `Hook\Hooks\StepsLimitHook`
- `Hook\Hooks\TokenUsageLimitHook`
- `Hook\Hooks\ExecutionTimeLimitHook`
- `Hook\Hooks\FinishReasonHook`
- `Hook\Hooks\ApplyContextConfigHook`

Interception:

- `Interception\CanInterceptAgentLifecycle`
- `Interception\PassThroughInterceptor`

## 9. Builder / Capabilities

- `Builder\AgentBuilder`
- `Builder\Contracts\CanProvideAgentCapability`
- `Builder\Contracts\CanConfigureAgent`

Capability registry:

- `Capability\AgentCapabilityRegistry`
- `Capability\CanManageAgentCapabilities`

Core capabilities:

- `Capability\Core\UseLLMConfig`
- `Capability\Core\UseGuards`
- `Capability\Core\UseTools`
- `Capability\Core\UseToolFactory`
- `Capability\Core\UseHook`
- `Capability\Core\UseDriver`
- `Capability\Core\UseDriverDecorator`
- `Capability\Core\UseContextCompiler`
- `Capability\Core\UseContextCompilerDecorator`
- `Capability\Core\UseContextConfig`
- `Capability\Core\UseReActConfig`

Domain capabilities:

- `Capability\Bash\UseBash`
- `Capability\File\UseFileTools`
  - installs: `read_file`, `write_file`, `edit_file`
  - standalone file tools also available: `SearchFilesTool`, `ListDirTool`
- `Capability\Metadata\UseMetadataTools`
- `Capability\Subagent\UseSubagents`
- `Capability\PlanningSubagent\UsePlanningSubagent`
- `Capability\StructuredOutput\UseStructuredOutputs`
- `Capability\Summarization\UseSummarization`
- `Capability\SelfCritique\UseSelfCritique`
- `Capability\Skills\UseSkills`
- `Capability\Tasks\UseTaskPlanning`
- `Capability\Tools\UseToolRegistry`
- `Capability\ExecutionHistory\UseExecutionHistory`
- `Capability\Retrospective\UseExecutionRetrospective`

## 10. Templates

- `Template\Data\AgentDefinition`
  - core fields: `name`, `description`, `systemPrompt`, `label`, `llmConfig`, `capabilities`, `tools`, `toolsDeny`, `skills`, `budget`, `metadata`
  - tool semantics: `tools === null` means inherit all available tools
- `Template\AgentDefinitionLoader`
- `Template\AgentDefinitionRegistry`
- `Template\Contracts\CanManageAgentDefinitions`
- `Template\Factory\DefinitionStateFactory`
- `Template\Factory\DefinitionLoopFactory`
- parsers: `Template\Parsers\MarkdownDefinitionParser`, `JsonDefinitionParser`, `YamlDefinitionParser`

## 11. Sessions

Core:

- `Session\Data\SessionId`
- `Session\Data\AgentSessionInfo`
- `Session\Data\AgentSession`
  - access: `info()`, `definition()`, `state()`, `sessionId()`, `status()`, `version()`
- `Session\SessionFactory`
- `Session\SessionRepository`
- `Session\SessionRuntime`

Contracts:

- `Session\Contracts\CanManageAgentSessions`
- `Session\Contracts\CanExecuteSessionAction`
- `Session\Contracts\CanStoreSessions`
- `Session\Contracts\CanControlAgentSession`

Stores:

- `Session\Store\InMemorySessionStore`
- `Session\Store\FileSessionStore`

Actions:

- `Session\Actions\SendMessage`
- `Session\Actions\ForkSession` (returns a new branch session object; persist via repository `create()`)
- `Session\Actions\ResumeSession`
- `Session\Actions\SuspendSession`
- `Session\Actions\ClearSession`
- `Session\Actions\ChangeModel`
- `Session\Actions\ChangeSystemPrompt`
- `Session\Actions\WriteMetadata`
- `Session\Actions\UpdateTask`

Session hooks:

- `Session\SessionHookStack`
- `Session\RegisteredSessionHook`

## 12. Events

Agent events include:

- `AgentExecutionStarted`, `AgentStepStarted`, `AgentStepCompleted`
- `AgentExecutionStopped`, `AgentExecutionCompleted`, `AgentExecutionFailed`
- `AgentStateUpdated`
- `ContinuationEvaluated`, `StopSignalReceived`, `TokenUsageReported`
- `ToolCallStarted`, `ToolCallCompleted`, `ToolCallBlocked`
- `InferenceRequestStarted`, `InferenceResponseReceived`
- `SubagentSpawning`, `SubagentCompleted`
- `HookExecuted`, `DecisionExtractionFailed`, `ValidationFailed`

Session events include:

- `SessionLoaded`, `SessionActionExecuted`, `SessionSaved`
- `SessionLoadFailed`, `SessionSaveFailed`

## 13. Testing

- `Drivers\Testing\FakeAgentDriver`
  - scripted loop steps via `ScenarioStep`
  - best for most deterministic agent-loop tests
- `Tests\Support\FakeInferenceDriver`
  - queued raw `InferenceResponse` or streaming `PartialInferenceDelta` fixtures
  - use when the test sits closer to the inference boundary
- `Tool\Tools\FakeTool`
  - deterministic tool double with fixed or callable-backed results
- `Tests\Support\FakeSubagentProvider`
  - in-memory subagent definition registry for capability tests
- `Tests\Support\TestAgentLoop`
  - small loop harness with explicit max-iteration stop behavior
- `Cognesy\Sandbox\Testing\FakeSandbox`
  - deterministic process-execution seam for bash-backed tools

## 13. Docs Index

Read in this order:

1. `packages/agents/docs/01-introduction.md`
2. `packages/agents/docs/testing-doubles.md`
3. `packages/agents/docs/02-basic-agent.md`
4. `packages/agents/docs/05-tools.md`
5. `packages/agents/docs/06-building-tools.md`
6. `packages/agents/docs/13-agent-builder.md`
7. `packages/agents/docs/14-agent-templates.md`
8. `packages/agents/docs/15-subagents.md`
9. `packages/agents/docs/16-session-runtime.md`
