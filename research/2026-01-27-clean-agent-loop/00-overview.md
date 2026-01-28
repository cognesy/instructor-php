# Clean AgentLoop Design

## Problem Statement

`AgentLoop` currently contains implementation-specific code mixed with orchestration logic:

1. **Step recording** - Creating `StepExecution`, evaluating continuation criteria, recording to state
2. **Error handling** - Creating failure steps, status transitions
3. **Non-uniform observer interface** - Some methods take extra params and return decisions instead of state

## Goals

1. **Unify `CanObserveAgentLifecycle`** - All methods become `onXxx(AgentState): AgentState`
2. **Extract step recording** - Move to a dedicated observer that handles core lifecycle operations
3. **Simplify AgentLoop** - Reduce to pure orchestration: loop structure + observer delegation

## Design Documents

- [01-unified-lifecycle-interface.md](./01-unified-lifecycle-interface.md) - Unified observer contract
- [02-extract-step-recording.md](./02-extract-step-recording.md) - CoreLifecycleObserver design
- [03-simplified-agent-loop.md](./03-simplified-agent-loop.md) - Minimal AgentLoop after refactoring

## Key Insight

`AgentState` already carries transient execution data via `CurrentExecution`. We extend this pattern to carry:
- Pending tool call
- Pending tool execution
- Stop reason
- Decisions (block/proceed)

This eliminates the need for special return types - everything flows through state.

## Migration Path

1. Extend `CurrentExecution` (or create `TransientExecution`) to hold tool-level transient data
2. Add decision encoding methods to `AgentState`
3. Create `CoreLifecycleObserver` that handles step recording
4. Update `CanObserveAgentLifecycle` to unified interface
5. Update `HookStackObserver` to new interface
6. Simplify `AgentLoop` lifecycle methods to pure delegation
7. Compose observers: `CoreLifecycleObserver` + `HookStackObserver`
