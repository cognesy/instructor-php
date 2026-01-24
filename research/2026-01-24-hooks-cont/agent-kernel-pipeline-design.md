# Agent Kernel Pipeline Design (Hooks + Composites)

Date: 2026-01-24
Status: Draft for review

## Goals

- Single execution loop in one class (Agent or ExecutionKernel), easy to explain.
- Delegate nuance to small composite building blocks with trivial contracts.
- Make hooks first-class extension points without turning capabilities into afterthoughts.
- Integrate error handling (ErrorPolicy + ErrorContext) into the loop, not just continuation criteria.

## References (local)

- research/2026-01-24-hooks-cont/hooks-architecture-analysis.md
- research/2026-01-22-hooks/hooks-as-core.md
- research/2026-01-22-hooks/hooks-as-skeleton.md
- research/2026-01-22-hooks/hooks-ref.md
- research/2026-01-22-hooks/hooks-guide.md

## Lifecycle Coverage Check

Target lifecycle points (from request):
- OnExecutionStart
- OnBeforeStep
- OnBeforeInference
- OnAfterInference
- OnBeforeToolUse
- OnAfterToolUse
- OnAfterStep
- OnShouldContinue
- OnExecutionEnd
- OnError

Current coverage in codebase:

- OnExecutionStart: HookType::ExecutionStart exists, but Agent does not fire it.
- OnBeforeStep / OnAfterStep: HookType::BeforeStep/AfterStep exist, but Agent does not fire them.
- OnBeforeInference / OnAfterInference: no HookType or context; no extension point.
- OnBeforeToolUse / OnAfterToolUse: implemented in ToolExecutor via HookStack.
- OnShouldContinue: no hook event; continuation is via ContinuationCriteria only.
- OnExecutionEnd: HookType::ExecutionEnd exists, but Agent does not fire it.
- OnError: HookType::AgentFailed exists and Agent emits AgentFailed event, but no hook or policy integration for recoverable errors.

Conclusion: tool hooks exist and work, but the rest of the lifecycle is mostly unhooked. Before/after inference and should-continue are missing entirely.

## Claude Code Hooks Comparison (High-Level)

Claude Code events (from hooks-ref/guide) overlap with our core needs but include session-level events we likely do not need in the Agent core.

Mapping (desired in Agent):
- PreToolUse / PostToolUse: aligns directly with HookType::PreToolUse/PostToolUse.
- Stop / SubagentStop: aligns with HookType::Stop/SubagentStop, but should be wired into loop.
- ExecutionStart / ExecutionEnd: similar to SessionStart/SessionEnd in Claude Code, but narrower scope (per execution, not session).

Gaps vs Claude Code:
- PermissionRequest, UserPromptSubmit, Notification, PreCompact, Setup: not part of current agent loop. Likely out of scope for the core pipeline.
- Claude Code supports component-scoped hooks (skills/agents) and plugin hooks. This maps to our capabilities: hooks must be registerable by capabilities during build.

Takeaway: align core lifecycle events with tool/step/continuation, and let higher layers (capabilities, UI, runtime) add session-level or UI-driven hooks if needed.

## Proposed Single-Loop Kernel With Composite Building Blocks

Keep a single execution loop in one class, but push complexity into well-named composites with simple, testable contracts.

### Kernel Sketch (single loop)

```
state = lifecycle.start(state)

while (continuation.shouldContinue(state)) {
  try {
    step = stepRunner.run(state)
    state = stepApplier.apply(state, step)
  } catch (Throwable $error) {
    [state, action] = errorHandler.handle($error, state)
    if (action === retry) {
      continue
    }
    if (action === stop) {
      break
    }
  }
}

state = lifecycle.finish(state)
return state
```

### Building Blocks (trivial contracts)

1) ExecutionLifecycle
- Contract: `start(AgentState): AgentState`, `finish(AgentState): AgentState`, `failed(AgentState, Throwable): AgentState`
- Responsibilities: ExecutionStart/ExecutionEnd/AgentFailed hooks + events + status updates.

2) StepRunner
- Contract: `run(AgentState): AgentStep`
- Responsibilities: BeforeStep hook, BeforeInference hook, driver invocation, AfterInference hook.
- Delegates tool execution to ToolExecutor (unchanged signature: ToolCalls + AgentState -> AgentExecutions).

3) StepApplier
- Contract: `apply(AgentState, AgentStep): AgentState`
- Responsibilities: record step, wrap StepResult, apply state processors, AfterStep hook, events.

4) ContinuationEvaluator
- Contract: `evaluate(AgentState): ContinuationOutcome` and `shouldContinue(AgentState): bool`
- Responsibilities: criteria evaluation, ShouldContinue hook, produce ContinuationOutcome with reason.

5) StopResolver
- Contract: `resolve(AgentState, ContinuationOutcome): bool`
- Responsibilities: Stop/SubagentStop hooks that can block stopping (force continuation).

6) ErrorHandler
- Contract: `handle(Throwable, AgentState): ErrorAction`
- Responsibilities: error classification (ErrorContext), ErrorPolicy decision, OnError hooks, translate to retry/stop/ignore + state updates.

Notes:
- Each composite can internally use HookStack or specialized hook collections; the kernel stays small and stable.
- Keep ToolExecutor as-is; it is already a good example of a composite with a trivial contract.

## Error Handling Integration (Explicit in Loop)

Current state:
- ErrorPolicyCriterion is evaluated inside ContinuationCriteria, which runs after a step is created.
- Agent handles exceptions via onFailure(), but error handling decisions (retry/stop) are not a first-class stage.

Proposed:
- Add try/catch around step execution in the kernel.
- ErrorHandler uses ErrorContext (AgentErrorContextResolver) + ErrorPolicy to decide:
  - Retry: continue loop without stopping, possibly with failure step recorded.
  - Stop: produce failure step, mark status Failed.
  - Ignore: record failure but allow continuation.
- Fire OnError hook (new HookType, or reuse AgentFailed for fatal only).

Rationale: ErrorPolicy is part of the contract of the kernel, not just a continuation criterion. This makes retries and error flows explicit and hookable.

## Hook Events to Add/Confirm

Add missing lifecycle events to HookType and contexts:
- BeforeInference / AfterInference (new context for inference/driver boundary)
- ShouldContinue (or EvaluateContinuation) with a context that includes evaluations and outcome
- OnError (distinct from AgentFailed; for recoverable errors)

Existing HookType events to wire into the loop:
- ExecutionStart, ExecutionEnd
- BeforeStep, AfterStep
- Stop, SubagentStop
- AgentFailed
- PreToolUse, PostToolUse (already in ToolExecutor)

## Capabilities Are First-Class (Not Afterthoughts)

Capabilities today use:
- withTools()
- addProcessor()/addPreProcessor()
- addContinuationCriteria()
- onBuild()

Mapping to the new pipeline (no functional regressions):
- addProcessor() -> AfterStep hook registered in StepApplier (or StepHooks composite)
- addPreProcessor() -> BeforeStep hook registered in StepRunner (or StepHooks composite)
- addContinuationCriteria() -> ShouldContinue/EvaluateContinuation hook in ContinuationEvaluator
- onBuild() -> keep for compatibility, but prefer explicit builder methods (hooks/tools) over ad-hoc mutation

Capability examples:
- UseSummarization: registers BeforeStep hooks (SummarizeBuffer, MoveMessagesToBuffer).
- UseTaskPlanning: registers AfterStep hooks (TodoReminder/Render/Persist).
- UseSelfCritique: registers AfterStep hook + ShouldContinue hook.
- UseSkills/StructuredOutput/Metadata: AfterStep hooks for persistence.
- UseSubagents: should migrate from onBuild() to an explicit tool registration hook or builder method.

Design requirement: capabilities must be able to register hooks during install with priority and matcher support, similar to Claude Code plugin hooks.

## Hook Registration Strategy

Current HookStack is global, but event filtering is inconsistent for CallableHook. Options:

A) HookRegistry by event
- Registry: map HookType -> HookStack
- Builder registers hooks into the correct stack automatically.
- Cleaner mental model, avoids accidental cross-event execution.

B) Single HookStack + EventTypeMatcher
- Keep HookStack but wrap all callables with EventTypeMatcher when registering.
- Minimal change, but more easy to misuse if not enforced.

Recommendation: move to HookRegistry keyed by HookType for predictable behavior and easier debugging.

## Proposed Lifecycle Diagram

ExecutionStart
  -> BeforeStep
  -> BeforeInference
  -> Driver (Inference)
  -> AfterInference
  -> Tool calls (PreToolUse/PostToolUse) via ToolExecutor
  -> AfterStep
  -> ShouldContinue (criteria + hooks)
     -> if stop: Stop/SubagentStop hooks can block stop
  -> loop or exit
ExecutionEnd
OnError invoked on any exception; AgentFailed reserved for fatal failures.

## Incremental Migration Plan

1) Add missing HookType events + contexts (BeforeInference, AfterInference, ShouldContinue, OnError).
2) Introduce composites (StepRunner, StepApplier, ContinuationEvaluator, ErrorHandler, ExecutionLifecycle).
3) Keep Agent as kernel, delegate to composites. ToolExecutor stays intact.
4) Wire AgentBuilder to build and inject composites and to register hooks into the right composite.
5) Map processors/criteria to hook registration internally (keep public API stable).
6) Remove unused adapters once coverage is complete.

## Open Questions

- Should Agent remain the kernel or should we introduce ExecutionKernel as an internal class with Agent as facade?
- Do we keep ContinuationOutcome/StepResult as-is or expose a new result type through the composites?
- OnError semantics: should it fire for every error (including recoverable) or only for terminal failures?

