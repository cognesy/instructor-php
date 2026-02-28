---
title: 'State Internals'
description: 'Deep dive into AgentState structure, execution tracking, and message metadata'
---

# Agent State Internals

## AgentState Structure

```
AgentState (readonly)
  |-- agentId: AgentId             # typed UUID value object, auto-generated
  |-- parentAgentId: ?AgentId      # set for subagents
  |-- createdAt: DateTimeImmutable
  |-- updatedAt: DateTimeImmutable  # bumped on every mutation
  |-- executionCount: int           # increments across executions
  |-- llmConfig: ?LLMConfig         # optional override for LLM config
  |-- context: AgentContext
  |   |-- store: MessageStore
  |   |-- metadata: Metadata
  |   |-- systemPrompt: string
  |   |-- responseFormat: ResponseFormat
  |   |-- deadline: ?DateTimeImmutable
  |-- execution: ?ExecutionState    # null between executions
      |-- executionId: ExecutionId
      |-- status: ExecutionStatus   # Pending|InProgress|Completed|Stopped|Failed
      |-- startedAt / completedAt
      |-- stepExecutions: StepExecutions  # completed steps
      |-- continuation: ExecutionContinuation
      |   |-- stopSignals: StopSignals
      |   |-- isContinuationRequested: bool
      |-- currentStepStartedAt
      |-- currentStep: ?AgentStep   # in-progress step
          |-- id: AgentStepId
          |-- inputMessages
          |-- outputMessages
          |-- inferenceResponse
          |-- toolExecutions: ToolExecutions (items have ToolExecutionId)
          |-- errors: ErrorList
```

## Key Accessors

```php
// Identity
$state->agentId()->toString();
$state->parentAgentId() !== null ? (string) $state->parentAgentId() : null;

// Timing
$state->createdAt();
$state->executionDuration();

// Context
$state->messages();
$state->metadata();
$state->context()->systemPrompt();

// Execution
$state->status();                    // ExecutionStatus enum
$state->execution()?->executionId()->toString();
$state->stepCount();
$state->steps();                     // AgentSteps collection
$state->lastStep();
$state->lastStepType();
$state->lastStopReason();
$state->usage();                     // total token usage
$state->errors();
$state->hasErrors();

// Final output
$state->hasFinalResponse();
$state->finalResponse()->toString();
```

## ExecutionBudget

`ExecutionBudget` declares per-execution resource limits on an `AgentDefinition`. It is applied as a `UseGuards` capability when the agent loop is built — not stored in `AgentState`.

```php
use Cognesy\Agents\Data\ExecutionBudget;

$definition = new AgentDefinition(
    name: 'my-agent',
    // ...
    budget: new ExecutionBudget(maxSteps: 20, maxTokens: 10000, maxSeconds: 60.0),
);
```

Each subagent receives its own declared budget. Recursion depth is controlled separately via `SubagentPolicy::maxDepth`.

## Serialization

All state objects support `toArray()` / `fromArray()` for persistence:

```php
$data = $state->toArray();
$restored = AgentState::fromArray($data);
```
