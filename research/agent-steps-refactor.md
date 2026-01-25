# Agent Steps Refactor Plan

## Goal
Create a coherent, minimal, mostly immutable domain model for tracking a single agent step, removing redundancy and fixing step timing semantics.

## Key Problems Observed
1. Redundant timestamps across `StepInfo`, `StepResult`, and `ToolExecution`.
2. Redundant tool-call sources: `AgentStep::$toolCalls` and `ToolExecution::$toolCall`.
3. `AgentStep::createdAt()` is not the actual step start time.
4. `AgentStep` mixes lifecycle concerns with step snapshot concerns.

## Target Domain Model
### Aggregate Root
`StepResult` should be the aggregate root for step history and timing.

It owns:
1. `step: AgentStep`
2. `outcome: ContinuationOutcome`
3. `startedAt: DateTimeImmutable`
4. `completedAt: DateTimeImmutable`

### Step Snapshot
`AgentStep` should represent the step snapshot only.

It should own:
1. `inputMessages: Messages`
2. `inferenceResponse: InferenceResponse`
3. `toolExecutions: ToolExecutions`
4. `outputMessages: Messages`
5. `errors: list<Throwable>` for model/driver-level errors

It should not own:
1. `StepInfo`
2. `toolCalls` as a separate field
3. `usage` as a separate field
4. `stepType` as stored state

## Single Sources of Truth
1. Requested tool calls: `AgentStep::requestedToolCalls()` -> `inferenceResponse->toolCalls()`
2. Executed tool calls: derived from `toolExecutions`
3. Usage: `AgentStep::usage()` -> `inferenceResponse->usage()`
4. Finish reason: `AgentStep::finishReason()` -> `inferenceResponse->finishReason()`
5. Step timing: `StepResult::{startedAt, completedAt}`
6. Tool timing: `ToolExecution::{startedAt, endedAt}`

## Proposed API Shape
### AgentStep
Keep compatibility where practical, but reframe the meaning.

Add:
1. `requestedToolCalls(): ToolCalls`
2. `executedToolCalls(): ToolCalls`

Keep (but redefine as derived values):
1. `toolCalls(): ToolCalls` -> alias of `requestedToolCalls()`
2. `usage(): Usage` -> from `inferenceResponse`
3. `finishReason(): ?InferenceFinishReason` -> from `inferenceResponse`
4. `stepType(): AgentStepType` -> derived, not stored

### ToolExecutions
Add:
1. `toolCalls(): ToolCalls` -> mapped from executions

## Step Type Derivation Rules
`AgentStepType` should be derived via early returns:
1. If step has model/driver errors: `Error`
2. If tool executions have errors: `Error`
3. If requested tool calls exist: `ToolExecution`
4. Otherwise: `FinalResponse`

## Critical Timing Fix
Current step timing is incorrect because it uses step creation time as start time.

Fix:
1. In `Agent::onBeforeStep()`, set `currentStepStartedAt = new DateTimeImmutable()`.
2. In `Agent::onAfterToolUse()`, use `state->currentStepStartedAt` as `StepResult::startedAt`.
3. In `AgentErrorHandler::handleError()`, also use `state->currentStepStartedAt` as `StepResult::startedAt`.
4. Add a small helper on `AgentState`, e.g. `currentStepStartedAtOrNow(): DateTimeImmutable`.

## Refactor Strategy (Staged)
### Stage 1: Additive Changes
Goal: enable migration without breaking existing consumers.

1. Add `ToolExecutions::toolCalls(): ToolCalls`.
2. Add `AgentStep::requestedToolCalls()` and `AgentStep::executedToolCalls()`.
3. Keep `AgentStep::$toolCalls`, `AgentStep::$usage`, and `StepInfo` for now.

### Stage 2: Fix Timing Semantics
Goal: correct step duration and event timing.

1. Update `Agent::onBeforeStep()` to record actual step start time.
2. Update both `onAfterToolUse()` and `AgentErrorHandler::handleError()` to use that recorded time.
3. Add tests asserting that `StepResult::startedAt <= StepResult::completedAt` and reflects loop start.

### Stage 3: Migrate Consumers
Update call sites to use the new API, prioritizing:
1. `SlimAgentStateSerializer`
2. `SelfCriticProcessor`
3. Any tests referencing `step->toolCalls()` semantics

Recommended direction:
1. Requested tool calls -> `requestedToolCalls()`
2. Executed tool calls -> `executedToolCalls()`

### Stage 4: Remove Redundancy
Once consumers are migrated:
1. Remove `StepInfo` entirely.
2. Remove `AgentStep::$toolCalls`.
3. Remove `AgentStep::$usage`.
4. Make `AgentStep::stepType()` purely derived.

## Compatibility Notes
1. Keep `toolCalls()` as an alias to requested tool calls to preserve behavior.
2. In `AgentStep::fromArray()`, if `inferenceResponse.toolCalls` is empty but legacy `toolCalls` exists, hydrate it into the response.
3. Preserve existing serialized fields during the transition, but treat them as legacy inputs.

## Files Most Impacted
1. `packages/agents/src/Agent/Data/AgentStep.php`
2. `packages/agents/src/Agent/Data/StepInfo.php`
3. `packages/agents/src/Agent/Data/StepResult.php`
4. `packages/agents/src/Agent/Agent.php`
5. `packages/agents/src/Agent/ErrorHandling/AgentErrorHandler.php`
6. `packages/agents/src/Agent/Collections/ToolExecutions.php`
7. `packages/agents/src/Serialization/SlimAgentStateSerializer.php`
8. `packages/agents/src/AgentBuilder/Capabilities/SelfCritique/SelfCriticProcessor.php`

## Success Criteria
1. One source of truth per concern (tool calls, usage, timing).
2. Step timing reflects actual loop start.
3. `AgentStep` reads as a deliberate snapshot model.
4. All `packages/agents` tests pass after migration.
