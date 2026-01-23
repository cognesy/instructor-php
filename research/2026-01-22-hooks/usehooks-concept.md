# UseHooks capability concept

This note describes how to add a hook system to the agent runtime as a
capability (`UseHooks`) that aligns with the existing AgentBuilder and its
middleware-style processors.

## Current execution pipeline (what we can plug into)

High level flow for a single agent execution:

1. `AgentBuilder` assembles:
   - tools
   - state processors (preProcessors + base + processors)
   - continuation criteria
   - driver (ToolCallingDriver)
   - tool executor (ToolExecutor)
2. `Agent::finalStep()` runs the StepByStep loop:
   - processors wrap `Agent::performStep()`
   - `performStep()` calls the driver to get tool calls
   - `ToolExecutor` executes tool calls and emits ToolCall events
   - continuation criteria are evaluated
   - the step result is recorded

Extension points already used by capabilities:

- `AgentBuilder::addPreProcessor()` and `addProcessor()`
- `AgentBuilder::addContinuationCriteria()`
- `AgentBuilder::onBuild()` to wrap the agent or tool executor
- Event bus (`ToolCallStarted`, `ToolCallCompleted`, `AgentStepStarted`,
  `AgentStepCompleted`, `AgentFinished`, `AgentFailed`, etc.)

## Hook events mapped to this runtime

Suggested hook events that match existing pipeline points:

- ExecutionStart (before first step, once per `finalStep()` run)
- StepStart (right before driver inference for a step)
- StepEnd (after `performStep()` finishes and state updated)
- PreToolUse (before tool execution, can block or modify args)
- PostToolUse (after tool execution, can add metadata or messages)
- Stop (after continuation evaluation, can block or force stop)
- AgentFailed (on failure, for logging or cleanup)

Notes:
- "UserPromptSubmit" does not exist in core runtime today. That would need a
  wrapper around `AgentState::withUserMessage()` or a higher-level agent
  facade to expose an input hook.
- "PermissionRequest" is not a concept in the current runtime. PreToolUse can
  approximate allow/deny decisions.
- "SessionStart/End" is a CLI-level concept, not present in the agent core.
  We can scope hooks to execution start/end inside `finalStep()`.

## Proposed UseHooks design

### Core concepts

1. HookEvent (enum)
   - ExecutionStart, StepStart, StepEnd, PreToolUse, PostToolUse, Stop, AgentFailed

2. Hook (interface)
   - `event(): HookEvent`
   - `matches(HookContext $context): bool`
   - `handle(HookContext $context): Result<HookOutcome>`

3. HookContext (value objects)
   - ExecutionContext: agent descriptor, state, timestamps
   - StepContext: state, current step, step index
   - ToolContext: state, tool call, tool name, args
   - FailureContext: state, exception

4. HookOutcome (value object)
   - `decision`: allow | deny | stop | continue
   - `updatedToolArgs`: array or null
   - `updatedState`: AgentState or null
   - `reason`: string or null

5. HookCollection / HookRegistry
   - Holds hooks per event in a dedicated collection class
   - Provides `forEvent(HookEvent): HookCollection`

6. HookRunner
   - Runs hooks sequentially
   - Aggregates HookOutcome (first deny/stop wins, allow can modify args)
   - Uses Result for error handling (no exceptions for control flow)

### Integration points

1. ToolExecutor wrapper
   - Add `HookedToolExecutor` that wraps ToolExecutor.
   - On `useTool()`:
     - Build ToolContext
     - Run PreToolUse hooks
     - If deny -> return Failure
     - If updated args -> execute tool with modified args
     - Run PostToolUse hooks after execution
   - Emits Hook events (optional) for observability.

2. Processor for step-level hooks
   - `HookProcessor` implements `CanProcessAnyState<AgentState>`.
   - Before `$next`:
     - If stepCount == 0 -> ExecutionStart hooks
     - StepStart hooks
     - Allow hooks to update state (messages, metadata)
   - After `$next`:
     - StepEnd hooks
     - Store HookOutcome in metadata for continuation criteria (see below)

3. Continuation criterion for Stop hooks
   - `HookContinuationCriterion` reads metadata produced by HookProcessor or
     runs Stop hooks directly during evaluation.
   - Returns `AllowStop` when a hook signals stop, `AllowContinuation` when a
     hook signals continue, otherwise falls through.

4. AgentBuilder integration
   - `UseHooks` capability installs:
     - HookProcessor as preProcessor (so it wraps core processors)
     - HookContinuationCriterion
     - onBuild callback to swap tool executor with HookedToolExecutor

### Hook matching

Hook matching should be explicit and type-safe:

- Tool name matchers for PreToolUse/PostToolUse (exact or regex)
- Step type matchers (FinalResponse vs ToolExecution)
- Optional metadata selectors (ex: only when metadata key exists)
- Keep matchers in their own value objects to avoid ad-hoc arrays

### Error handling and safety

- Hook failures return `Result::failure()`; the runner uses policy for:
  - fail-open (ignore failed hook)
  - fail-closed (deny tool / stop execution)
- No try/catch for control flow; rely on Result.
- Do not mutate state in-place; always return new state.

## Minimal implementation plan (phased)

Phase 1: Observability only
- HookRunner listens to event bus events and logs or records data.
- No blocking or mutation.

Phase 2: PreToolUse/PostToolUse
- HookedToolExecutor with allow/deny and arg updates.
- HookProcessor for step-level hooks.

Phase 3: Stop hooks
- HookContinuationCriterion to make hook decisions participate in continuation.

Phase 4: Prompt and session hooks
- Add a small facade around AgentState to emit InputSubmitted events.
- Map to UserPromptSubmit-like hooks.

## Example capability usage (conceptual)

- UseHooks with:
  - PreToolUse hook to block `bash` commands outside a base dir.
  - PostToolUse hook to copy tool output into metadata for downstream steps.
  - StepEnd hook to emit a telemetry event with step duration.

This keeps hooks consistent with existing capabilities: they are installed via
AgentBuilder, participate in the middleware chain, and can be composed with
other capabilities without altering core agent logic.
