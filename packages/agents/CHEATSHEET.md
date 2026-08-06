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
  - last-step accessors: `lastStep()`, `lastStepExecution()`, `lastStepToolExecutions()`, `lastToolExecution()`, `lastStepErrors()`, `lastStepType()`, `lastStepUsage()`, `lastStepDuration()`
  - stop accessors: `stopSignal()`, `stopReason()`, `stopSource()`
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
- `Capability\Cancellation\UseCooperativeCancellation`
  - adds checkpoint-based cooperative cancellation to the loop
  - cancellation is **cooperative**: stops at `BeforeExecution` / `BeforeStep` checkpoints only — does **not** interrupt in-flight HTTP or tool calls
  - requires a `CanProvideCancellationSignal` implementation; built-in: `InMemoryCancellationSource`
  - stop reason reported as `StopReason::UserRequested`

  ```php
  use Cognesy\Agents\Capability\Cancellation\UseCooperativeCancellation;
  use Cognesy\Agents\Capability\Cancellation\InMemoryCancellationSource;

  $source = new InMemoryCancellationSource();

  $agent = AgentBuilder::base()
      ->withCapability(new UseCooperativeCancellation($source))
      ->build();

  // cancel from outside (e.g. signal handler, HTTP request, timer):
  $source->cancel('user pressed stop');

  // custom source (Redis key, DB flag, HTTP endpoint, …):
  $agent = AgentBuilder::base()
      ->withCapability(new UseCooperativeCancellation(
          new class implements CanProvideCancellationSignal {
              public function cancellationSignal(AgentState $state): ?StopSignal {
                  return redis_get("cancel:{$state->agentId()}")
                      ? StopSignal::userRequested('cancelled via redis')
                      : null;
              }
          }
      ))
      ->build();
  ```
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
11. `packages/agents/docs/21-evals.md`
12. `packages/agents/docs/22-eval-assertions.md`
13. `packages/agents/docs/23-eval-judges.md`
14. `packages/agents/docs/24-eval-traces-and-artifacts.md`
15. `packages/agents/docs/25-running-evals.md`

## 19. Evals

Behavioral evals that grade an agent target with deterministic assertions and semantic judges. Narrative docs: `docs/21-evals.md` through `docs/25-running-evals.md`.

Case definition:

- `Evals\AgentEval` (readonly)
  - immutable definition of one eval case
  - factories: `define(description, Closure(EvalContext): void $test, ?tags, ?judge)`
  - key API: `withId()`
  - accessors: `description()`, `test()`, `tags()`, `id()`, `judge()`
- `Evals\AgentEvals` (readonly, `Countable`, `IteratorAggregate`)
  - immutable collection of `AgentEval`
  - factories: `none()`
  - key API: `with()`, `filtered(?glob, ?required, ?excluded)`
  - accessors: `all()`, `count()`
- `Evals\AgentEvalSet` (readonly)
  - groups evals built from a dataset
  - factories: `fromDataset(EvalDataset, Closure(EvalDatasetRow): AgentEval $factory)`, `of(AgentEval ...$evals)`
  - accessors: `evals(): AgentEvals`
- `Evals\EvalTags` (readonly, `Countable`, `IteratorAggregate`)
  - normalized (trimmed, deduped, sorted) tag set
  - factories: `of()`, `none()`
  - key API: `has()`
  - accessors: `all()`, `count()`
- `Evals\EvalDataset` (readonly, `Countable`, `IteratorAggregate`)
  - list of `EvalDatasetRow`
  - factories: `fromJson()`, `fromYaml()`
- `Evals\EvalDatasetRow` (readonly)
  - one dataset row
  - key API: `value(key)`, `string(key)`
  - accessors: `toArray()`
- `Evals\EvalDiscovery` (readonly)
  - finds `*.eval.php` files under a root and assigns ids
  - factories: `in(root)`
  - key API: `discover(): AgentEvals`
  - note: an eval file must `return AgentEval|AgentEvalSet|array<AgentEval>`; ids are the file's path relative to the root (with a `/NNNN` suffix appended when one file yields more than one eval)
- `Evals\EvalCount` (readonly)
  - count predicate for `calledTool()`/`calledSubagent()`/`event()` assertions
  - factories: `atLeast()`, `atMost()`, `between()`, `satisfies(Closure(int): bool)`
  - key API: `matches(int)`
- `Evals\EvalMatch` (readonly)
  - value matcher for `outputMatches()` and `ValueExpectation::matches()`
  - factories: `partial(array)`, `regex(pattern)`, `satisfies(Closure)`
  - key API: `matches(mixed)`
- `Evals\EvalMatcher` (readonly)
  - static matching helpers used throughout: `matches()` (exact equality unless given an `EvalMatch` or array), `partial()` (recursive partial-array match; lists require equal length, maps require only the listed keys)

Execution context & assertions:

- `Evals\EvalContext`
  - passed into every eval's test closure; owns the session, assertion collector, and log collector for one eval run
  - key API: `send()`, `run(): AgentRun`, `expect(mixed): ValueExpectation`, `judge(): AgentJudgeAssertions`, `check()`, `require()` (throws `EvalRequirementFailed` on failure), `skip()` (throws `EvalSkipped`), `log()`, `newSession()`
  - built-in assertions, each returning `AssertionHandle`: `succeeded()`, `stopped()`, `messageIncludes()`, `outputEquals()`, `outputMatches()`, `calledTool()`, `notCalledTool()`, `toolOrder()`, `usedNoTools()`, `maxToolCalls()`, `stepCount()`, `maxSteps()`, `totalTokensAtMost()`, `noFailedActions()`, `calledSubagent()`, `event()`, `notEvent()`, `eventOrder()`, `eventsSatisfy()`
  - accessors: `assertions()`, `logs()`
  - note: `newSession()` shares this context's collectors with the new session -- `EvalRunner`'s repeated trials deliberately construct a brand-new `EvalContext` instead, so trials never share collectors
- `Evals\AssertionCollector`
  - records and defer-resolves assertion results for one eval run
  - key API: `record()`, `recordLazy(placeholder, Closure(): AssertionResult $resolve)`, `replace()`, `at(index)`, `results(): AssertionResults`
  - note: `recordLazy()`'s resolver runs at most once, at first read via `at()` or `results()` -- this is the mechanism that makes a judge run at most once
- `Evals\AssertionHandle` (readonly)
  - fluent handle returned by every `EvalContext`/`ValueExpectation` assertion
  - key API: `gate()`, `soft()`, `atLeast(threshold)`, `label()`, `result(): AssertionResult`, `replace()`
- `Evals\AssertionResult` (readonly)
  - one assertion's outcome
  - factories: `pass()`, `fail()`
  - key API: `withSeverity()`, `withScore()`, `withThreshold()`, `withLabel()`, `withJudgeScore()`, `passed(): bool` (`score >= threshold ?? 1.0`)
  - accessors: `name()`, `score()`, `severity()`, `threshold()`, `message()`, `label()`, `judgeScore()`, `judgeClass()`, `toArray()`
- `Evals\AssertionResults` (readonly, `Countable`, `IteratorAggregate`)
  - immutable collection of `AssertionResult`
  - key API: `with()`, `hasFailedGate()`, `hasFailedSoft()`
  - accessors: `all()`, `count()`
- `Evals\AssertionSeverity` -- `Gate`, `Soft`
- `Evals\ValueExpectation`
  - fluent value assertion returned by `EvalContext::expect()`
  - key API: `includes()`, `equals()`, `matches(string|EvalMatch)`, `similarity()` (Levenshtein-based, always `Soft`), `satisfies(Closure)`; chain modifiers `gate()`, `soft()`, `atLeast()`, `label()`
  - note: the chain modifiers apply only to the LAST assertion this expectation recorded, not to every assertion the expectation has made
- `Evals\EvalRequirementFailed` (extends `RuntimeException`) -- thrown by `EvalContext::require()` on failure; caught internally by `EvalRunner`, not user-visible
- `Evals\EvalSkipped` (extends `RuntimeException`) -- thrown by `EvalContext::skip()`; caught internally by `EvalRunner` and turned into `EvalVerdict::Skipped`

Target & sessions:

- `Evals\CanRunAgentEvalTarget` -- contract: `open(?EvalSessionRequest): CanUseAgentEvalSession`
- `Evals\LocalAgentTarget` (readonly)
  - runs eval sessions in-process
  - factories: `fromFactory(Closure(): CanControlAgentLoop $factory, ?EvalTracePolicy)`
- `Evals\HttpAgentTarget` (readonly)
  - runs eval sessions against a remote agent server over HTTP
  - key API: `open()`, `attach(sessionId)`, `sendTurn()`, `policy()`
  - note: applies its `EvalTracePolicy` (default `safe()`) to whatever the remote server sends, so the HTTP path is safe by default rather than degrading to verbatim serialization
- `Evals\CanUseAgentEvalSession` -- contract: `send(message): EvalTurn`, `run(): AgentRun`
- `Evals\LocalEvalSession` -- `CanUseAgentEvalSession` over an in-process `CanControlAgentLoop`
- `Evals\HttpEvalSession` -- `CanUseAgentEvalSession` over `HttpAgentTarget`; accessor: `sessionId()`
- `Evals\HttpTargetException` (extends `RuntimeException`) -- thrown on a non-2xx response, malformed JSON, or a missing `sessionId`
- `Evals\EvalSessionRequest` (readonly) -- optional `caseId`/`description` passed to `CanRunAgentEvalTarget::open()`
- `Evals\EvalTurn` (readonly)
  - one turn of an eval session
  - accessors: `index()`, `message()`, `run(): AgentRun`, `reply()`

Run trace:

- `Evals\AgentRun` (readonly)
  - immutable accumulated projection of an eval session, across turns
  - factories: `fromState()`, `empty()`, `fromArray()`
  - accessors: `reply()`, `status()`, `succeeded()`, `tools()`, `events()`, `turns()`, `errors()`, `steps()`, `usage()`, `duration()`, `stepCount()`, `stopSignal()`, `llmProfile()`
  - note: `stopSignal()` is the LAST turn's resolved signal and does NOT aggregate across turns -- per-turn signals live on `EvalStep::stopSignal()`
- `Evals\EvalStep` (readonly)
  - immutable safe projection of one `StepExecution`
  - factories: `fromStepExecution()`, `fromArray()`
  - accessors: `id()`, `turn()`, `index()`, `type()`, `outputMessages()`, `requestedToolCalls()`, `toolExecutions()`, `finishReason()`, `usage()`, `duration()`, `stopSignal()`, `errors()`, `hasErrors()`, `toArray()`
  - note: carries no input messages and never serializes the raw `InferenceResponse`
- `Evals\EvalSteps` (readonly, `Countable`, `IteratorAggregate`) -- ordered `EvalStep` collection; key API: `with()`, `last()`, `usage()`, `duration()`, `toArray()`/`fromArray()`
- `Evals\EvalToolExecutions` (readonly, `Countable`, `IteratorAggregate`) -- collection of `Data\ToolExecution`
- `Evals\EvalEvents` (readonly, `Countable`, `IteratorAggregate`) -- collection of arbitrary agent event objects captured during a run
- `Evals\EvalTracePolicy` (readonly)
  - controls how much of a tool payload lands in a serialized trace
  - factories: `safe()` (default everywhere), `full()` (explicit opt-in, never a default)
  - key API: `digest(mixed): array{hash, bytes, preview}`, `isDigest()`, `withPreviewBytes()`, `toArray()`/`fromArray()`
  - accessors: `isFull()`, `previewBytes()` (`DEFAULT_PREVIEW_BYTES = 120`)
  - note: `safe()` digests tool call arguments, tool results, AND error messages -- there is no size threshold, short values are digested too; `preview` renders the value's SHAPE (`<string:N>`, `<int>`, `<array:N>`, `<object:N>` past `MAX_PREVIEW_DEPTH = 6`), never the payload itself

Judging:

- `Evals\CanJudgeAgentEval` -- contract: `judge(JudgeRequest): JudgeScore`
- `Evals\AgentLoopJudge`
  - agentic judge: runs a bounded `AgentLoop` that inspects the target's `AgentRun` and submits a verdict via the `submit_judgment` terminal tool
  - factories: `fromBuilder(callable(): CanComposeAgentLoop $builderFactory)` -- the factory must return a FRESH, not-yet-built builder on every call
  - accessors: `llmProfile()`, `guardProfile(): array{configured, hooks}`
  - note: every `judge()` call gets a fresh builder/loop/state/event-list/`JudgeSubmissionInbox` -- nothing leaks between calls, even repeated calls on the same instance
  - note: installs NO guards of its own -- `warnIfGuardsMissing()` only inspects the built loop's profile for `UseGuards` and, if absent, dispatches `Events\JudgeGuardsNotConfigured` at most once per instance; it never substitutes a limit. Install `Capability\Core\UseGuards` explicitly
- `Evals\PolyglotAgentJudge` (readonly) -- lightweight judge backed by a raw LLM call expected to return `{"score":..,"reason":..}` JSON; factories: `fromInference(Inference)`, `fromInvoker(Closure(string): string)`
- `Evals\FakeAgentJudge` (readonly) -- deterministic judge double; factories: `fromScore()`, `fromClosure(Closure(JudgeRequest): JudgeScore)`
- `Evals\JudgeRequest` (readonly) -- `criterion`, `output`, `run: AgentRun` (required, not optional), `input`, `reference`
- `Evals\JudgeScore` (readonly) -- `score` (validated `[0,1]`), `reason` (non-empty), `evidence: JudgeEvidence`, `?run: AgentRun`
- `Evals\JudgeEvidence` (readonly, `Countable`, `IteratorAggregate`) -- ordered evidence strings backing a `JudgeScore`; factories: `none()`, `of()`; note: developer-visible support for the score, never hidden model reasoning
- `Evals\JudgeCriterion` -- `Factuality`, `Summarizes`, `ClosedQa`, `Sql`
- `Evals\AgentJudgeAssertions` (readonly)
  - returned by `EvalContext::judge()`; built-in criteria
  - key API: `factuality(reference)`, `summarizes(source)`, `closedQa(question)`, `sql(reference)` -- each returns `JudgeExpectation`
- `Evals\JudgeExpectation`
  - fluent judge assertion chain
  - key API: `on(output)` (replaces only the graded output; retains the run), `gate()`, `soft()`, `atLeast()`, `label()`
  - note: the chain only accumulates state -- the judge runs AT MOST ONCE, on first read of the recorded result (`AssertionCollector::results()`/`at()`); `.on()` never re-runs or re-judges. Severity defaults to `Gate` with no judge configured, `Soft` with one; a judge exception always forces `Gate` regardless of prior `gate()`/`soft()` calls
- `Evals\SubmitJudgmentTool` (extends `Tool\Tools\SimpleTool`)
  - the judge's terminal tool (`submit_judgment`); validates `score`/`reason`/`evidence` and records a `JudgeSubmission` into its `JudgeSubmissionInbox`
  - constant: `TOOL_NAME`
- `Evals\JudgeSubmission` (readonly) -- one validated `submit_judgment` call: `score`, `reason`, `evidence`
- `Evals\JudgeSubmissionInbox`
  - mailbox shared between `SubmitJudgmentTool` and `JudgeProtocolHook` for one `judge()` call
  - key API: `submit()`, `has()`, `get()`, `attempts()`
  - note: holds at most one submission -- `submit()` never overwrites; `attempts()` counts only tool-body invocations, so a call blocked by `JudgeProtocolHook` does NOT increment it
- `Evals\JudgeProtocolHook` (readonly, implements `Hook\Contracts\HookInterface`)
  - enforces the terminal-submission protocol on `BeforeToolUse`/`AfterStep`: blocks a second `submit_judgment` call, skips (does not block) any other tool call once a submission is recorded, and adds a `StopReason::Completed` stop signal after the submission step
- `Evals\JudgeProtocolException` (extends `RuntimeException`) -- thrown by `AgentLoopJudge::judge()` when the protocol was violated (no submission, a blocked second submission, or a failed run); always converted to a `Gate` failure by `JudgeExpectation::resolve()`
- `Evals\JudgePromptRenderer` (readonly) -- renders the judge's fixed system prompt and per-request user prompt; wraps the target trace in `<untrusted-target-trace>` markers (a labeling reduction, not a security boundary)
- `Evals\UseJudgeInference` (readonly, implements `Builder\Contracts\CanProvideAgentCapability`)
  - recommended driver capability for judge builders passed to `AgentLoopJudge::fromBuilder()`: installs `ToolCallingDriver` with `temperature: 0.0` by default (caller-supplied `options` win)
  - note: `AgentLoopJudge` never installs this on the developer's behalf -- it is documented as the recommended judge driver, not injected

Repetition:

- `Evals\EvalRepetition` (readonly)
  - the N trials of one repeated case; present only when a case ran more than once
  - factories: `fromTrials(list<EvalResult>, passRate)`
  - accessors: `trials()`, `trialCount()`, `passCount()`, `requiredPasses()`, `satisfied()`, `allSkipped()`, `judgeScoreMean()` (null when nothing was judged), `judgeScoreStdDev()` (POPULATION deviation -- divided by N, not N-1; 0.0 for a single score, never a division by zero), `representative()` (first non-`Passed` trial, else the first trial), `toArray()`

Verdict, running & config:

- `Evals\EvalVerdict` -- `Passed`, `Failed`, `Scored`, `Skipped`
- `Evals\EvalVerdictResolver` (readonly)
  - key API: `resolve(AssertionResults, skipped, ?error): EvalVerdict` (error or failed gate -> `Failed`; skipped -> `Skipped`; failed soft -> `Scored`; else `Passed`), `resolveRepeated(EvalRepetition): EvalVerdict` (all-skipped -> `Skipped`; satisfied k-of-N -> `Passed`; else `Failed`)
  - static: `requiredPasses(trials, passRate): int` -- `ceil(passRate * trials - 1e-9)` clamped to `[1, trials]`, guarding against IEEE-754 near-integer error
- `Evals\EvalExitCode` -- `Success = 0`, `EvalFailure = 1`, `ConfigurationError = 2`
- `Evals\EvalRunner` (readonly)
  - key API: `run(AgentEvals, ?EvalRunOptions): EvalRunResult`
  - note: each repeated trial opens a FRESH `EvalContext`/session (never `EvalContext::newSession()`, which shares collectors); `repeat=1` returns the trial's `EvalResult` unchanged, not wrapped; the cooperative timeout is a per-trial budget, not per-case
- `Evals\EvalRunOptions` (readonly)
  - factories: `default()`
  - key API: `withFilter()`, `withTags()`, `withExcludedTags()`, `withStrict()`, `withSkipReport()`, `withVerbose()`, `withTimeout()`, `withRepeat()`, `withPassRate()`
  - accessors: `filter()`, `tags()`, `excludedTags()`, `strict()`, `skipReport()`, `verbose()`, `timeout()`, `repeat()`, `passRate()`
  - note: constructor validates `repeat >= 1` and `passRate` in `(0, 1]`; repetition only measures TARGET variance when the judge is separately pinned to a fixed temperature (e.g. via `UseJudgeInference`) -- `AgentLoopJudge` never installs that for you
- `Evals\EvalConfig` (readonly)
  - factories: `default()`
  - key API: `withTarget()`, `withJudge()`, `withReporters()`, `withReporter()`
  - accessors: `target()`, `judge()`, `reporters()`
- `Evals\EvalApplication` (readonly)
  - CLI entry point
  - key API: `run(argv, ?callable $stdout, ?callable $stderr): int`
  - flags: `--filter=<glob>`, `--tag=<tag>` (repeatable), `--exclude-tag=<tag>` (repeatable), `--strict`, `--timeout=<seconds>`, `--repeat=<n>`, `--pass-rate=<r>`, `--junit=<path>`, `--list`, `--verbose`, `--json`, `--skip-report`, `-h`/`--help`
  - note: loads `<root>/evals.config.php` when present (must `return EvalConfig`); `--repeat` rejects a non-whole-number or fractional value outright rather than silently truncating or casting it

Logging:

- `Evals\EvalLog` (readonly) -- one log entry: `message()`, `context()`, `toArray()`
- `Evals\EvalLogs` (readonly, `Countable`, `IteratorAggregate`) -- immutable `EvalLog` collection; factories: `none()`; key API: `with()`
- `Evals\EvalLogCollector` -- mutable collector behind `EvalContext::log()`; key API: `record()`, `logs(): EvalLogs`

Reporting:

- `Evals\CanReportAgentEvals` -- contract: `id()`, `onRunStarted(caseCount)`, `onEvalCompleted(EvalResult)`, `onRunCompleted(EvalRunResult)`
- `Evals\CanFailAgentEvalTestSuite` (extends `CanReportAgentEvals`) -- marker for reporters that propagate the final assertion into the host test runner
- `Evals\ConsoleEvalReporter` (readonly)
  - factories: `fromWriter(Closure(string): void, verbose = false)`
  - key API: `withVerbose()`
  - note: a repeated case prints a rate line (`PASS 4/5 ... judge=0.88+/-0.06`) instead of a single verdict; the `judge=` field is omitted -- not printed as a fabricated `0.00` -- when nothing in the case was judged
- `Evals\ArtifactEvalReporter`
  - writes a full run's artifacts to disk under `.instructor/evals/<run>/`: per-eval `details.json`, `events.ndjson`, `target-trace.json`, `target-steps.jsonl`, per-judged-assertion `judges/NNN.json` (+ `-steps.jsonl`), per-trial `trials/NNN/` for repeated cases, and run-level `summary.json`/`results.jsonl`
  - constructor: `root`, `?ClockInterface $clock`, `?Closure(): ?string $gitShaResolver`, `?Closure(): ?string $packageVersionResolver`
  - accessors: `runDirectory()`
  - note: never writes a raw `target-messages.json` conversation snapshot -- that would bypass `EvalTracePolicy::safe()`'s digesting and reintroduce the exact leak class the trace hardening closed
- `Evals\JUnitEvalReporter` -- writes JUnit XML to the `path` given at construction
- `Evals\PHPUnitEvalReporter` (implements `CanFailAgentEvalTestSuite`) -- factories: `default()`; asserts `EvalExitCode::Success` via `PHPUnit\Framework\Assert`
- `Evals\PestEvalReporter` (implements `CanFailAgentEvalTestSuite`) -- factories: `default()`; asserts `EvalExitCode::Success` via a Pest `Expectation`
- `Evals\EvalReporters` (readonly, `IteratorAggregate`)
  - immutable, id-deduplicated reporter collection
  - factories: `none()`
  - key API: `with()`, `withVerboseConsole()` (upgrades any `ConsoleEvalReporter` in the collection in place)
- `Evals\EvalTestFailureMessage` -- static `fromResult(EvalRunResult): string`; renders a CI-friendly multi-line failure summary (counts, per-eval failures, repetition rate, judge evidence)

Result:

- `Evals\EvalResult` (readonly)
  - outcome of one eval case, possibly a repeated case's aggregate
  - accessors: `id()`, `description()`, `verdict()`, `assertions()`, `run()`, `duration()`, `error()`, `skipReason()`, `logs()`, `repetition(): ?EvalRepetition`, `trials()`, `trialCount()`, `passCount()`, `judgeScoreMean()`, `judgeScoreStdDev()`, `provenance()`, `tokens(): array{target, judge, total}`, `toArray(?envelope)`
  - note: `provenance()['judge']['temperature']` is always null -- `AgentLoopJudge`'s built loop exposes no temperature accessor, and reporting an assumed default would fabricate a value the judge may not have used; `guardsWarningObserved` is derived only from the presence of a `JudgeGuardsNotConfigured` event on the judge's own run, never from its absence
- `Evals\EvalRunResult` (readonly, `Countable`, `IteratorAggregate`)
  - full result of one `EvalRunner::run()` call
  - key API: `exitCode(?bool $strict = null): EvalExitCode` (`EvalFailure` if any result `Failed`, or effective-strict with any result `Scored`; else `EvalFailure` if `reporterErrors() !== []`; else `Success`), `provenance()`, `tokens()`, `toArray(?envelope)`
  - accessors: `all()`, `reporterErrors()`, `strict()`

Events:

- `Evals\Events\JudgeGuardsNotConfigured` (extends `Events\AgentEvent`) -- dispatched at most once per `AgentLoopJudge` instance when its built judge loop has no `UseGuards` capability; `capability`, `suggestedFix`
