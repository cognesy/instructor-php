# Timestamp Convention (Agents)

## Short Answer
You could force everything into `createdAt` + `updatedAt`, but it would blur important semantics for execution timing. In this codebase we model both:
1. Entity lifecycle (state snapshots)
2. Execution lifecycle (timed runs)

Using one pair for both makes durations and intent harder to reason about.

## Why `createdAt` + `updatedAt` Alone Is Not Enough
`updatedAt` implies "the same entity changed." That is a poor fit for timed executions, where we need:
1. A clear start
2. A clear end
3. Accurate duration semantics

For executions, `updatedAt` is ambiguous:
1. Is it the last progress update?
2. Is it the end of execution?
3. Is the execution even finished yet?

With immutable snapshots, `updatedAt` is especially misleading because nothing is actually being "updated" after creation.

## Recommended Unified Model
Use exactly two conventions, each tied to a distinct semantic:

### Entity Lifecycle
Use for long-lived state and identity snapshots.
1. `createdAt`
2. `updatedAt`

Applies to:
1. `StateInfo`
2. Any durable state objects

### Execution Lifecycle
Use for timed work and duration calculations.
1. `startedAt`
2. `completedAt`

Applies to:
1. `StepResult`
2. `ToolExecution`
3. Execution/step/tool events that measure latency

## Practical Rule of Thumb
1. If you compute duration: use `startedAt` + `completedAt`.
2. If you track entity freshness: use `createdAt` + `updatedAt`.

## Optional Event Convention
Events are not entities or executions; they "occur."

If we want maximum clarity later, add:
1. `occurredAt`

to `AgentEvent` (while keeping any execution timing fields like `startedAt` + `completedAt` when relevant).

## Migration Guidance (Non-Breaking)
1. Add new names as aliases.
2. Switch internal code to the new names.
3. Serialize both during transition.
4. Remove legacy names later.
