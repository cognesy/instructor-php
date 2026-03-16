---
title: Agents
description: Agent loop, state model, tools, context management, hooks, and subagent orchestration
package: agents
---

# Agents Package Cheatsheet

Root namespace: `Cognesy\Agents`

This file is a quick, code-aligned map of the package surface.
For narrative guidance and examples, use `packages/agents/docs/*.md`.

## 1. Core Loop

- `AgentLoop` (readonly)
  - main orchestrator, implements `CanControlAgentLoop` and `CanAcceptEventHandler`
  - key API: `default()`, `execute()`, `iterate()`
  - accessors: `tools()`, `toolExecutor()`, `driver()`, `eventHandler()`, `interceptor()`
  - composition API: `withTool()`, `withTools()`, `withDriver()`, `withToolExecutor()`, `withInterceptor()`, `withEventHandler()`, `with()`
  - event API: `wiretap()`, `onEvent()`
  - note: terminal executions are auto-reset on entry to `execute()` / `iterate()`
- `CanControlAgentLoop`
  - contract: `execute(AgentState): AgentState`, `iterate(AgentState): iterable`

## 2. State Model

- `Data\AgentState`
  - immutable runtime state
  - factories: `empty()`, `fromArray()`
  - identity: `agentId()`, `parentAgentId()`, `llmConfig()`, `executionCount()`, `createdAt()`, `updatedAt()`
  - common mutators: `withUserMessage(string|\Stringable|Message)`, `withSystemPrompt(string|\Stringable)`, `withMetadata()`, `withMessages()`, `withMessageStore()`, `withLLMConfig()`, `with()`
  - step mutators: `withCurrentStep()`, `withCurrentStepCompleted()`, `withExecutionCompleted()`, `withExecutionContinued()`
  - stop/failure: `withStopSignal()`, `withFailure()`, `withExecutionStatus()`
  - context access: `context()`, `store()`, `messages()`, `metadata()`
  - result access: `finalResponse()`, `currentResponse()`, `hasFinalResponse()`
  - execution access: `execution()`, `status()`, `stepCount()`, `steps()`, `usage()`, `errors()`, `hasErrors()`
  - last-step accessors: `lastStep()`, `lastStepExecution()`, `lastStepToolExecutions()`, `lastToolExecution()`, `lastStepErrors()`, `lastStepType()`, `lastStepUsage()`, `lastStepDuration()`, `lastStopSignal()`, `lastStopReason()`, `lastStopSource()`
  - control: `shouldStop()`, `forNextExecution()`
  - serialization: `debug()`, `toArray()`, `fromArray()`
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
  - factories: `unlimited()`
  - queries: `isEmpty()`, `isExhausted()`
- `Data\AgentId`, `Data\ExecutionId`, `Data\AgentStepId`, `Data\ToolExecutionId`
  - typed ID value objects

## 3. Enums

- `Enums\ExecutionStatus` -- `Pending`, `InProgress`, `Completed`, `Stopped`, `Failed`
- `Enums\AgentStepType` -- `ToolExecution`, `FinalResponse`, `Error`

## 4. Collections

- `Collections\Tools`
  - immutable named tool collection
  - key API: `has()`, `get()`, `names()`, `all()`, `count()`, `isEmpty()`, `descriptions()`, `withTool()`, `withTools()`, `withToolRemoved()`, `merge()`, `toToolSchema(): ToolDefinitions`
- `Collections\AgentSteps`
- `Collections\StepExecutions`
- `Collections\ToolExecutions`
- `Collections\NameList`

## 5. Tools

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

## 6. Drivers

- `Drivers\CanUseTools`
- `Drivers\CanAcceptToolRuntime`
- `Drivers\ToolCalling\ToolCallingDriver` (default)
- `Drivers\ToolCalling\ToolExecutionFormatter`
- `Drivers\ReAct\ReActDriver`
- `Drivers\Testing\FakeAgentDriver`
- `Drivers\Testing\ScenarioStep`

## 7. Context

- `Context\AgentContext`
- `Context\CanCompileMessages`
- `Context\CanAcceptMessageCompiler`
- `Context\ContextSections`

Compilers:

- `Context\Compilers\ConversationWithCurrentToolTrace` (default)
- `Context\Compilers\AllSections`
- `Context\Compilers\SelectedSections`

## 8. Continuation / Stop

- `Continuation\StopReason`
- `Continuation\StopSignal`
- `Continuation\StopSignals`
- `Continuation\ExecutionContinuation`
- `Continuation\AgentStopException`

## 9. Hooks / Interception

- `Hook\Contracts\HookInterface`
- `Hook\Data\HookContext`
- `Hook\Data\RegisteredHook`
- `Hook\Collections\RegisteredHooks`
- `Hook\Enums\HookTrigger`
  - values: `BeforeExecution`, `BeforeStep`, `BeforeToolUse`, `AfterToolUse`, `AfterStep`, `OnStop`, `AfterExecution`, `OnError`
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

## 10. Builder / Capabilities

- `Builder\AgentBuilder`
- `Builder\AgentConfigurator`
- `Builder\Contracts\CanProvideAgentCapability`
- `Builder\Contracts\CanConfigureAgent`
- `Builder\Contracts\CanComposeAgentLoop`
- `Builder\Contracts\CanProvideDeferredTools`
- `Builder\Collections\DeferredToolProviders`
- `Builder\Data\DeferredToolContext`

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
- `Capability\Broadcasting\UseAgentBroadcasting`

## 11. Broadcasting

- `Broadcasting\AgentEventBroadcaster`
- `Broadcasting\AgentBroadcastObserver`
- `Broadcasting\BroadcastConfig`
- `Broadcasting\CanBroadcastAgentEvents`

## 12. Templates

- `Template\Data\AgentDefinition`
  - core fields: `name`, `description`, `systemPrompt`, `label`, `llmConfig`, `capabilities`, `tools`, `toolsDeny`, `skills`, `budget`, `metadata`
  - tool semantics: `tools === null` means inherit all available tools
- `Template\AgentDefinitionLoader`
- `Template\AgentDefinitionRegistry`
- `Template\Contracts\CanManageAgentDefinitions`
- `Template\Contracts\CanInstantiateAgentLoop`
- `Template\Contracts\CanInstantiateAgentState`
- `Template\Parsers\CanParseAgentDefinition`
- `Template\Factory\DefinitionStateFactory`
- `Template\Factory\DefinitionLoopFactory`
- parsers: `Template\Parsers\MarkdownDefinitionParser`, `JsonDefinitionParser`, `YamlDefinitionParser`

## 13. Sessions

Core:

- `Session\Data\SessionId`
- `Session\Data\AgentSessionInfo`
- `Session\Data\AgentSession`
  - access: `info()`, `definition()`, `state()`, `sessionId()`, `status()`, `version()`
- `Session\SessionRuntime` -- preferred API for creating new sessions and applying actions to persisted sessions
- `Session\SessionRepository` -- low-level persistence boundary over a store implementation
- `Session\SessionFactory` -- low-level helper for constructing `AgentSession` instances before manual persistence

Contracts:

- `Session\Contracts\CanManageAgentSessions`
- `Session\Contracts\CanExecuteSessionAction`
- `Session\Contracts\CanStoreSessions`
- `Session\Contracts\CanControlAgentSession`

Stores:

- `Session\Store\InMemorySessionStore`
- `Session\Store\FileSessionStore`

Actions:

- `Session\Actions\SendMessage` (accepts `string|\Stringable|Message`)
- `Session\Actions\ForkSession` (returns a new branch session object; persist that fork via repository `create()`; for brand-new root sessions prefer `SessionRuntime::create()`)
- `Session\Actions\ResumeSession`
- `Session\Actions\SuspendSession`
- `Session\Actions\ClearSession`
- `Session\Actions\ChangeModel`
- `Session\Actions\ChangeSystemPrompt` (accepts `string|\Stringable`)
- `Session\Actions\WriteMetadata`
- `Session\Actions\UpdateTask`

Enums:

- `Session\Enums\SessionStatus` -- `Active`, `Suspended`, `Completed`, `Failed`, `Deleted`
- `Session\Enums\AgentSessionStage` -- `AfterLoad`, `AfterAction`, `BeforeCreate`, `AfterCreate`, `BeforeSave`, `AfterSave`

Session hooks:

- `Session\SessionHookStack`
- `Session\RegisteredSessionHook`
- `Session\PassThroughSessionController`
- `Session\Collections\SessionInfoList`

Exceptions:

- `Session\Exceptions\SessionNotFoundException`
- `Session\Exceptions\SessionConflictException`
- `Session\Exceptions\InvalidSessionFileException`

## 14. Events

Agent events include:

- `AgentExecutionStarted`, `AgentStepStarted`, `AgentStepCompleted`
- `AgentExecutionStopped`, `AgentExecutionCompleted`, `AgentExecutionFailed`
- `AgentStateUpdated`
- `ContinuationEvaluated`, `StopSignalReceived`, `TokenUsageReported`
- `ToolCallStarted`, `ToolCallCompleted`, `ToolCallBlocked`
- `InferenceRequestStarted`, `InferenceResponseReceived`
- `SubagentSpawning`, `SubagentCompleted`
- `HookExecuted`, `DecisionExtractionFailed`, `ValidationFailed`
- `Events\AgentEvent` (base class)

Event support:

- `Events\Support\AgentEventConsoleFormatter`
- `Events\Support\AgentEventConsoleObserver`

Session events include:

- `SessionLoaded`, `SessionActionExecuted`, `SessionSaved`
- `SessionLoadFailed`, `SessionSaveFailed`

## 15. Exceptions

- `Exceptions\AgentException` (base)
- `Exceptions\AgentNotFoundException`
- `Exceptions\InvalidToolException`
- `Exceptions\InvalidToolArgumentsException`
- `Exceptions\ToolCallBlockedException`
- `Exceptions\ToolExecutionBlockedException`
- `Exceptions\ToolExecutionException`

## 16. Skills

- `Capability\Skills\Skill`
  - immutable skill value object
  - standard fields: `name`, `description`, `license`, `compatibility`, `metadata`, `allowedTools`, `body`, `path`, `resources`
  - extension fields: `disableModelInvocation`, `userInvocable`, `argumentHint`, `model`, `context`, `agent`
  - key API: `render(?string $arguments)`, `renderMetadata()`, `toArray()`
  - argument substitution: `$ARGUMENTS`, `$ARGUMENTS[N]`, `$N` placeholders
- `Capability\Skills\SkillLibrary`
  - discovers `SKILL.md` files in `<path>/<skill-name>/SKILL.md`
  - lazy-loads skill content on first access, caches result
  - key API: `listSkills(modelInvocable, userInvocable)`, `hasSkill()`, `getSkill()`, `renderSkillList()`
  - resource discovery: scans `scripts/`, `references/`, `assets/`, `examples/` subdirs
- `Capability\Skills\LoadSkillTool`
  - tool exposed to LLM: `load_skill(skill_name, list_skills, arguments)`
  - user-invocable filtering on list mode
- `Capability\Skills\AppendSkillMetadataHook`
  - injects skill names/descriptions as system message before first step
  - filters out `disable-model-invocation: true` skills
- `Capability\Skills\TrackActiveSkillHook`
  - tracks active skill metadata (allowed-tools, model) in state after `load_skill` completes
- `Capability\Skills\SkillToolFilterHook`
  - enforces `allowed-tools` restrictions; blocks non-allowed tools (except `load_skill` itself)
- `Capability\Skills\SkillModelOverrideHook`
  - overrides LLMConfig when a skill with a `model` field is active
- `Capability\Skills\SkillForkExecutor`
  - executes skills in a forked agent loop context
- `Capability\Skills\SkillPreprocessor`
  - executes `!`command`` patterns in skill body before argument substitution
  - configurable working directory and timeout
  - opt-in: pass to `UseSkills` or `LoadSkillTool` constructor
- `Capability\Skills\UseSkills`
  - capability that wires `LoadSkillTool` + hooks into agent
  - optional `?SkillPreprocessor` for shell preprocessing
- follows [Agent Skills Open Standard](https://agentskills.io) (30+ tools)

## 17. Testing

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
- `Cognesy\Sandbox\Testing\FakeSandbox` (from `packages/sandbox`, not agents)
  - deterministic process-execution seam for bash-backed tools

## 18. Docs Index

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
10. `packages/agents/docs/19-skills.md`
