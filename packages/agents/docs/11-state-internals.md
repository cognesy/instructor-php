---
title: 'State Internals'
description: 'Deep dive into AgentState structure, execution tracking, message metadata, and serialization'
---

# Agent State Internals

Every agent execution revolves around a single, immutable data structure: `AgentState`. This object carries the full picture of an agent's identity, conversation context, and execution progress. Understanding its internal structure is essential for building custom guards, hooks, and persistence layers.

## Design Philosophy

`AgentState` follows two core principles:

1. **Immutability.** The class is declared `final readonly`. Every mutation method (`with*`, `forNextExecution`, etc.) returns a new instance, leaving the original untouched. This makes state transitions explicit and safe for concurrent inspection.

2. **Session vs. Execution separation.** Some data persists across executions (identity, context, message history), while other data is transient and scoped to a single execution (step results, timing, continuation signals). This split is represented by the nullable `ExecutionState` property.

## AgentState Structure

The following diagram shows the complete object graph:

```
AgentState (final readonly)
  |-- agentId: AgentId                 # typed UUID, auto-generated
  |-- parentAgentId: ?AgentId          # set when running as a subagent
  |-- createdAt: DateTimeImmutable     # when the state was first created
  |-- updatedAt: DateTimeImmutable     # bumped on every mutation
  |-- executionCount: int              # increments with each execution
  |-- llmConfig: ?LLMConfig            # optional per-agent LLM override
  |-- context: AgentContext
  |   |-- store: MessageStore          # underlying message storage
  |   |-- metadata: Metadata           # arbitrary key-value pairs
  |   |-- systemPrompt: string         # system-level instructions
  |   |-- responseFormat: ResponseFormat
  |-- execution: ?ExecutionState       # null between executions
      |-- executionId: ExecutionId     # unique ID for this execution
      |-- status: ExecutionStatus      # Pending|InProgress|Completed|Stopped|Failed
      |-- startedAt: DateTimeImmutable
      |-- completedAt: ?DateTimeImmutable
      |-- stepExecutions: StepExecutions  # completed steps
      |-- continuation: ExecutionContinuation
      |   |-- stopSignals: StopSignals    # signals requesting execution to stop
      |   |-- isContinuationRequested: bool
      |-- currentStepStartedAt: ?DateTimeImmutable
      |-- currentStep: ?AgentStep      # the in-progress step
          |-- id: AgentStepId
          |-- inputMessages: Messages
          |-- outputMessages: Messages
          |-- inferenceResponse: InferenceResponse
          |-- toolExecutions: ToolExecutions
          |-- errors: ErrorList
```

### Session Data (Persists Across Executions)

Session-level properties survive between executions. When you call `forNextExecution()`, these fields are preserved while `execution` is reset to `null`:

- **`agentId`** -- A typed UUID (`AgentId`) that uniquely identifies the agent instance. Generated automatically on construction.
- **`parentAgentId`** -- Set when the agent is spawned as a subagent. Enables parent-child correlation in event tracing.
- **`createdAt` / `updatedAt`** -- Timestamps for lifecycle tracking. `updatedAt` is bumped on every mutation via `with()`.
- **`executionCount`** -- Monotonically increasing counter. Incremented by `AgentLoop::onBeforeExecution()` at the start of each execution. Useful for guards that behave differently on the first execution.
- **`llmConfig`** -- Optional `LLMConfig` override. When set, the driver uses this configuration instead of its default provider settings.
- **`context`** -- The `AgentContext` containing the message history, system prompt, metadata, and response format.

### Execution Data (Transient Per Execution)

The `execution` property holds an `ExecutionState` that is created fresh at the start of each execution and discarded (set to `null`) when the execution completes:

- **`executionId`** -- A unique `ExecutionId` for correlation. Generated via `ExecutionState::fresh()`.
- **`status`** -- An `ExecutionStatus` enum tracking the execution lifecycle.
- **`stepExecutions`** -- A `StepExecutions` collection of completed `StepExecution` objects. Each wraps an `AgentStep` together with its timing and continuation state.
- **`continuation`** -- An `ExecutionContinuation` that holds stop signals and continuation requests. The agent loop consults this after each step to decide whether to continue or stop.
- **`currentStep`** -- The `AgentStep` currently being processed. Set by the driver via `withCurrentStep()`, then archived into `stepExecutions` when `withCurrentStepCompleted()` is called.

## ExecutionStatus Lifecycle

`ExecutionStatus` is a string-backed enum with five cases:

| Status | Description |
|---|---|
| `Pending` | Between executions, ready for a fresh start |
| `InProgress` | Execution is actively running |
| `Completed` | Execution finished successfully |
| `Stopped` | Execution was force-stopped by a guard, budget limit, or external request |
| `Failed` | Execution encountered an unrecoverable error |

The `AgentLoop` manages these transitions automatically:

```
Pending/null --> InProgress  (onBeforeExecution)
InProgress   --> Completed   (all steps done, no errors)
InProgress   --> Stopped     (force-stopped by guard or stop signal)
InProgress   --> Failed      (exception caught or errors accumulated)
```

## AgentStep Internals

Each step in the execution is represented by an `AgentStep` -- an immutable snapshot of what happened during a single driver invocation:

```php
final readonly class AgentStep
{
    private AgentStepId $id;              // Unique step identifier
    private Messages $inputMessages;      // Messages sent to the LLM
    private Messages $outputMessages;     // Messages produced by the step
    private InferenceResponse $inferenceResponse;  // Raw LLM response
    private ToolExecutions $toolExecutions;         // Tool execution results
    private ErrorList $errors;            // Accumulated errors
}
```

The step type is **derived**, not stored. `AgentStep::stepType()` inspects the step's contents to determine its type:

1. If the step has errors (including tool execution errors), the type is `AgentStepType::Error`.
2. If the step has requested tool calls, the type is `AgentStepType::ToolExecution`.
3. Otherwise, the type is `AgentStepType::FinalResponse`.

This derivation means you never need to manually set the step type -- it is always consistent with the step's actual contents.

### StepExecution Wrapper

When a step is completed, it is wrapped in a `StepExecution` that bundles the step with timing and continuation data:

```php
final readonly class StepExecution
{
    private AgentStepId $id;                    // Follows AgentStep identity
    private AgentStep $step;                    // The completed step
    private ExecutionContinuation $continuation; // Stop signals at completion time
    private DateTimeImmutable $startedAt;
    private DateTimeImmutable $completedAt;
}
```

This separation keeps `AgentStep` focused on what happened (messages, tools, errors) while `StepExecution` owns when it happened and whether the loop should continue.

## Message Metadata Tagging

When a step's output messages are appended to the agent context, `AgentState::withCurrentStep()` automatically tags each message with metadata:

- **`step_id`** -- The `AgentStepId` of the step that produced the message.
- **`execution_id`** -- The `ExecutionId` of the current execution.
- **`agent_id`** -- The `AgentId` of the agent.
- **`is_trace`** -- Set to `true` for non-final steps (tool execution, error). Final response messages do not carry this flag.

This metadata enables downstream compilers (such as `ConversationWithCurrentToolTrace`) to filter messages at read-time based on their origin, without modifying the underlying message store.

## Key Accessors

`AgentState` provides a rich set of accessors for inspecting the current state at any point during or after execution:

### Identity and Timing

```php
$state->agentId()->toString();        // UUID string
$state->parentAgentId();              // ?AgentId -- null for root agents
$state->createdAt();                  // DateTimeImmutable
$state->updatedAt();                  // DateTimeImmutable -- bumped on every mutation
$state->executionCount();             // int -- how many times the agent has been executed
$state->executionDuration();          // ?float -- seconds elapsed in current execution
```

### Context

```php
$state->messages();                   // Messages -- compiled message list
$state->store();                      // MessageStore -- raw message storage
$state->metadata();                   // Metadata -- arbitrary key-value pairs
$state->context()->systemPrompt();    // string -- the system prompt
```

### Execution State

```php
$state->status();                     // ?ExecutionStatus -- null if between executions
$state->execution();                  // ?ExecutionState -- null if between executions
$state->execution()?->executionId()->toString();  // UUID of current execution
$state->stepCount();                  // int -- number of steps in current execution
$state->steps();                      // AgentSteps -- collection of completed steps
$state->lastStep();                   // ?AgentStep -- most recently completed step
$state->lastStepType();              // ?AgentStepType -- ToolExecution|FinalResponse|Error
$state->stopReason();                // ?StopReason -- why execution stopped
$state->usage();                      // InferenceUsage -- accumulated token usage
$state->hasErrors();                  // ?bool -- whether any errors occurred
$state->errors();                     // ErrorList -- all accumulated errors
```

### Final Output

```php
$state->hasFinalResponse();           // bool -- true if the last step is a FinalResponse
$state->finalResponse()->toString();  // string -- the final response text
$state->currentResponse();            // Messages -- final response or latest step output
```

## Continuation and Stop Signals

The agent loop uses `ExecutionContinuation` to decide whether to keep iterating. After each step, the loop calls `$state->shouldStop()`, which delegates to:

```php
// ExecutionState::shouldStop()
public function shouldStop(): bool {
    return match(true) {
        $this->continuation->shouldStop() => true,  // Stop signals present and no override
        $this->continuation->isContinuationRequested() => false,  // Hook requested continuation
        $this->hasToolCalls() => false,              // Tool calls need execution
        default => true,                             // No tool calls, no continuation -- stop
    };
}
```

Stop signals carry a `StopReason` enum with prioritized cases:

| Priority | StopReason | Description |
|---|---|---|
| 0 (highest) | `ErrorForbade` | An error prevented continuation |
| 1 | `StopRequested` | Explicit stop via `AgentStopException` |
| 2 | `StepsLimitReached` | Step budget exhausted |
| 3 | `TokenLimitReached` | Token budget exhausted |
| 4 | `TimeLimitReached` | Time budget exhausted |
| 5 | `RetryLimitReached` | Maximum retries exceeded |
| 6 | `FinishReasonReceived` | LLM signaled completion |
| 7 | `UserRequested` | External user request |
| 8 | `Completed` | Normal completion |
| 9 (lowest) | `Unknown` | Unspecified reason |

Multiple stop signals can coexist. The `wasForceStopped()` method on `StopReason` returns `true` for all reasons except `Completed` and `FinishReasonReceived`, which represent natural completion.

## ExecutionBudget

`ExecutionBudget` declares per-execution resource limits. It is defined on an `AgentDefinition` and applied as a `UseGuards` capability when the agent loop is built -- it is **not** stored inside `AgentState`.

```php
use Cognesy\Agents\Data\ExecutionBudget;

$budget = new ExecutionBudget(
    maxSteps: 20,          // Maximum number of loop iterations
    maxTokens: 10000,      // Maximum total tokens (input + output)
    maxSeconds: 60.0,      // Maximum wall-clock seconds
    maxCost: 0.50,         // Maximum cost in dollars
    deadline: new DateTimeImmutable('2025-12-31 23:59:59'),  // Absolute deadline
);
```

All limits are optional -- pass `null` (or omit) for unlimited. You can check whether a budget has any limits set with `isEmpty()`, or whether all limits have been exhausted with `isExhausted()`.

The `ExecutionBudget::unlimited()` factory returns a budget with all limits set to `null`:

```php
$unlimited = ExecutionBudget::unlimited();
assert($unlimited->isEmpty() === true);
```

Each subagent receives its own declared budget. Recursion depth is controlled separately via `SubagentPolicy` (`maxDepth`), not through the budget.

## Debugging

`AgentState::debug()` returns an associative array summarizing the current state -- useful for logging or test assertions:

```php
$info = $state->debug();
// [
//     'status' => ExecutionStatus::Completed,
//     'executionCount' => 1,
//     'hasExecution' => true,
//     'executionId' => 'a1b2c3d4-...',
//     'steps' => 3,
//     'continuation' => 'No Stop Signals; Continuation Requested: No',
//     'hasErrors' => false,
//     'errors' => ErrorList::empty(),
//     'usage' => ['inputTokens' => 150, 'outputTokens' => 42, ...],
// ]
```

## Serialization

All state objects implement `toArray()` and `fromArray()` for persistence and hydration. This covers the full object graph -- `AgentState`, `ExecutionState`, `AgentStep`, `StepExecution`, `ToolExecution`, and `ExecutionContinuation`:

```php
// Serialize the entire state to a plain array
$data = $state->toArray();

// Restore the state from a plain array
$restored = AgentState::fromArray($data);

// Everything round-trips correctly
expect($restored->agentId()->toString())->toBe($state->agentId()->toString());
expect($restored->stepCount())->toBe($state->stepCount());
expect($restored->status())->toBe($state->status());
```

This is the foundation for session persistence. The `SessionStore` implementations use `toArray()` / `fromArray()` to save and restore agent state between requests or across process boundaries.

### Serialization Scope

| Object | `toArray()` | `fromArray()` |
|---|---|---|
| `AgentState` | Full state including context and execution | Restores all fields |
| `ExecutionState` | Execution ID, status, timing, steps, continuation | Restores all fields |
| `AgentStep` | Step ID, messages, inference response, tool executions, errors | Restores all fields |
| `StepExecution` | Step data, continuation, timing | Restores all fields |
| `ToolExecution` | Tool call, result/error, timing | Restores all fields |
| `ExecutionBudget` | All limit values | Restores all limits |
| `ExecutionContinuation` | Stop signals, continuation flag | Restores all fields |

## Key Gotcha: `ensureExecution()` Creates Fresh State

The private `ensureExecution()` method returns `ExecutionState::fresh()` with a **new UUID** when `execution` is `null`. This means calling it twice produces different execution IDs. The `AgentLoop` handles this correctly, but if you are building custom orchestration, be aware that you must capture and reuse the returned state:

```php
// WRONG -- two different execution IDs
$state->withStopSignal($signal);  // internally calls ensureExecution()
$state->withCurrentStep($step);   // internally calls ensureExecution() again -- different ID!

// CORRECT -- chain mutations on the same state
$state = $state->withCurrentStep($step)->withStopSignal($signal);
```
