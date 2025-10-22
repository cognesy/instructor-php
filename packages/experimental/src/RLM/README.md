RLM Contracts for StepByStep

This directory introduces minimal contracts and value objects to align the StepByStep addon with the Recursive Language Model (RLM) pattern:

- Contracts: `RecursiveLanguageModel`, `ExecutionRuntime`, `ReplEnvironment`, `Toolset`, `Aggregator`
- Data: `Policy`, `RlmInvocation`, `RlmResult`, `RlmStatus`, `Trace`
- Handles: `Handle`, `ContextHandle`, `ResultHandle`, `VarHandle`, `ArtifactHandle`
- REPL: `ReplInventory`, `CodeResultHandle`

Mapping to existing StepByStep concepts

- Budgets → ContinuationCriteria
  - `Policy.maxSteps` → `StepsLimit`
  - `Policy.maxWallClockSec` → `ExecutionTimeLimit`
  - Token budgets → `TokenUsageLimit` + `SummarizeBuffer` for truncation
- Truncation → `MoveMessagesToBuffer` + `SummarizeBuffer`
- Working memory (REPL variables/artifacts) → `State` variables and message store sections
- Aggregation → Implement domain reducers operating on `ResultHandle[]`

Notes

- These contracts are intentionally minimal to avoid over-engineering. They provide a seam to wire a strict loop and a runtime tree-builder on top of StepByStep’s processors and criteria.
- Next steps: add a strict output parser and a thin adapter that feeds StepByStep state transitions from `{plan|tool|write|final|await}` model outputs.

