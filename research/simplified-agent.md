# Agent Loop Simplification Proposal

**Date:** 2026-01-18  
**Status:** Draft  
**Effort Estimate:** L (1-2 days)

## Executive Summary

The current Agent/StepByStep architecture is over-engineered with scattered control flow, excessive trait composition, and complex continuation logic. This proposal outlines a refactoring to make the agent loop **radically simpler, cleaner, easier to debug, more solid and robust** while preserving the external API.

## Current Architecture Problems

### 1. Template Method Indirection (StepByStep)
The `StepByStep` base class uses template methods (`canContinue`, `makeNextStep`, `applyStep`, `onNoNextStep`, `onStepCompleted`, `onFailure`) that:
- Hide control flow across multiple methods
- Duplicate try/catch layering (StepByStep::performStep + Agent::performStep)
- Use `method_exists` checks for optional behavior
- Make debugging require jumping between 6+ methods

### 2. Trait/Interface Explosion
**AgentState** implements 5+ interfaces via traits:
- `HasSteps`, `HasMessageStore`, `HasMetadata`, `HasUsage`, `HasStateInfo`

**AgentStep** implements 6+ interfaces via traits:
- `HasStepErrors`, `HasStepInfo`, `HasStepMessages`, `HasStepToolCalls`, `HasStepToolExecutions`, `HasStepUsage`

This creates:
- Debugging complexity (which trait owns what?)
- No real substitution benefit (nobody swaps implementations)
- Verbose `withX()` ceremony

### 3. Complex Continuation Logic
The 4-decision lattice (`ForbidContinuation`, `RequestContinuation`, `AllowStop`, `AllowContinuation`) with priority resolution:
- Conceptually simple, but implementation is hard to trace
- `ContinuationCriteria::evaluate()` has complex resolution logic
- Stop reasons inferred from class names via `str_contains`

### 4. Middleware Processors
`StateProcessors` wraps step execution in middleware chain:
- Hides execution order
- Forces generic typing hacks
- Complicates error handling (where did exception happen?)

### 5. Scattered Event Emission
Events emitted across:
- `canContinue()` → ContinuationEvaluated
- `makeNextStep()` → AgentStepStarted
- `applyStep()` → AgentStateUpdated
- `onStepCompleted()` → AgentStepCompleted + TokenUsageReported
- `onNoNextStep()` → AgentFinished
- `onFailure()` → AgentFailed

---

## Proposed Redesign

### A. Replace StepByStep with Direct AgentEngine Loop

Create a single-method loop that's readable and debuggable in one place:

```php
final class AgentEngine
{
    public function run(
        AgentState $state,
        ContinuationPolicy $policy,
        CanUseTools $driver,
        Tools $tools,
        CanExecuteToolCalls $executor,
        ProcessorPipeline $processors,
        CanHandleEvents $events,
    ): AgentState {
        $state = $state->markExecutionStarted();

        while (true) {
            // 1. DECIDE: Should we continue?
            $decision = $policy->decide($state);
            $events->dispatch(new ContinuationEvaluated($state, $decision));

            if ($decision->shouldStop()) {
                $status = $decision->isFailed() ? AgentStatus::Failed : AgentStatus::Completed;
                $state = $state->withStatus($status);
                $events->dispatch(new AgentFinished($state));
                return $state;
            }

            // 2. EXECUTE: Perform inference + tool calls
            $events->dispatch(new AgentStepStarted($state));
            $started = microtime(true);

            try {
                $step = $driver->useTools($state, $tools, $executor);
                $state = $state->recordStep($step);
                $state = $processors->apply($state);
                $state = $state->addExecutionTime(microtime(true) - $started);
                $events->dispatch(new AgentStepCompleted($state));
            } catch (Throwable $e) {
                $state = $state->failWith($e);
                $events->dispatch(new AgentFailed($state, $e));
                return $state;
            }
        }
    }

    public function iterate(/* same params */): Generator
    {
        // Same logic but yield $state after each step
    }
}
```

**Benefits:**
- One method to understand the entire loop
- Single try/catch block
- Explicit event emission points
- Easy to set breakpoint and trace

### B. Simplify Continuation to Guards + Work

Replace the 4-decision system with two explicit categories:

```php
final class ContinuationPolicy
{
    /** @var list<callable(AgentState): ?StopReason> Guards that can force stop */
    private array $guards;

    /** @var list<callable(AgentState): bool> Signals that request continuation */
    private array $workSignals;

    public function decide(AgentState $state): ContinuationDecision
    {
        // Guards first: any guard can force stop
        foreach ($this->guards as $guard) {
            $reason = $guard($state);
            if ($reason !== null) {
                return ContinuationDecision::stop($reason);
            }
        }

        // Work signals: any signal can request continuation
        foreach ($this->workSignals as $signal) {
            if ($signal($state)) {
                return ContinuationDecision::continue();
            }
        }

        // No work requested
        return ContinuationDecision::stop(StopReason::Completed);
    }
}

final readonly class ContinuationDecision
{
    private function __construct(
        public bool $shouldStop,
        public ?StopReason $stopReason,
        public bool $isFailed,
    ) {}

    public static function stop(StopReason $reason): self
    {
        return new self(
            shouldStop: true,
            stopReason: $reason,
            isFailed: $reason === StopReason::ErrorForbade,
        );
    }

    public static function continue(): self
    {
        return new self(shouldStop: false, stopReason: null, isFailed: false);
    }
}
```

**Migration:**
- `ForbidContinuation` criteria → guards returning `StopReason`
- `RequestContinuation` criteria → work signals returning `true`
- `AllowStop`/`AllowContinuation` → no-op (don't register)

### C. Consolidate State/Step into Cohesive Sub-Objects

Reduce trait explosion while keeping external API:

```php
final readonly class AgentState
{
    public function __construct(
        public string $agentId,
        public ?string $parentAgentId,
        private Conversation $conversation,  // messages + metadata + cache
        private RunLog $runLog,              // steps + currentStep
        private Telemetry $telemetry,        // usage + timing + status
    ) {}

    // High-level state transitions (instead of 10+ withX methods)
    public function recordStep(AgentStep $step): self { ... }
    public function failWith(Throwable $e): self { ... }
    public function addExecutionTime(float $seconds): self { ... }
    public function markExecutionStarted(): self { ... }

    // Delegating accessors (preserve external API)
    public function messages(): Messages { return $this->conversation->messages(); }
    public function usage(): Usage { return $this->telemetry->usage(); }
    public function stepCount(): int { return $this->runLog->count(); }
    public function currentStep(): ?AgentStep { return $this->runLog->current(); }
    public function status(): AgentStatus { return $this->telemetry->status(); }
}
```

Similarly for `AgentStep`:

```php
final readonly class AgentStep
{
    public function __construct(
        private StepIO $io,           // input + output messages
        private StepTools $tools,     // tool calls + executions
        private StepTelemetry $telemetry,  // usage + errors + finish reason
    ) {}

    // Delegating accessors
    public function inputMessages(): Messages { return $this->io->input(); }
    public function outputMessages(): Messages { return $this->io->output(); }
    public function hasToolCalls(): bool { return $this->tools->hasToolCalls(); }
    public function toolCalls(): ToolCalls { return $this->tools->toolCalls(); }
    public function hasErrors(): bool { return $this->telemetry->hasErrors(); }
}
```

### D. Simplify Processors to Post-Step Pipeline

Remove middleware complexity - processors run **after** each step:

```php
interface StateProcessor
{
    public function __invoke(AgentState $state): AgentState;
}

final class ProcessorPipeline
{
    /** @param list<StateProcessor> $processors */
    public function __construct(private array $processors) {}

    public function apply(AgentState $state): AgentState
    {
        foreach ($this->processors as $processor) {
            $state = $processor($state);
        }
        return $state;
    }
}
```

**Migration:**
- Existing processors that only need `process($state, $next)` with `$next($state)` → just `__invoke($state)`
- Rare "around" behavior → create single `AroundHook` if needed

### E. Event Lifecycle is Now Explicit

With the engine loop, events become predictable:

```
┌─────────────────────────────────────────────────────────────┐
│ Per Iteration:                                              │
│   1. ContinuationEvaluated (always)                        │
│   2. if stop → AgentFinished/AgentFailed → return          │
│   3. AgentStepStarted                                       │
│   4. [driver.useTools() + processors.apply()]              │
│   5. AgentStepCompleted + TokenUsageReported               │
│   → loop                                                    │
└─────────────────────────────────────────────────────────────┘
```

---

## Implementation Plan

### Phase 1: Core Engine (0.5 day)
1. Create `AgentEngine` with `run()` and `iterate()` methods
2. Create simplified `ContinuationPolicy` with guards + work signals
3. Create simplified `ContinuationDecision` value object

### Phase 2: State Consolidation (0.5 day)
1. Create `Conversation`, `RunLog`, `Telemetry` sub-objects
2. Add `recordStep()`, `failWith()`, `addExecutionTime()` to AgentState
3. Keep all existing accessors as delegating methods

### Phase 3: Processor Simplification (0.25 day)
1. Create `ProcessorPipeline` with simple sequential apply
2. Migrate existing processors to `StateProcessor` interface

### Phase 4: Integration (0.5 day)
1. Make `Agent::finalStep()` and `Agent::iterator()` delegate to engine
2. Create adapter from existing `ContinuationCriteria` → `ContinuationPolicy`
3. Keep `Agent extends StepByStep` for backward compatibility (deprecate later)

### Phase 5: Cleanup (0.25 day)
1. Add "golden trace" test for event ordering
2. Update AGENT.md documentation
3. Deprecate old continuation criteria classes

---

## Backward Compatibility

| Component | Strategy |
|-----------|----------|
| `Agent` public API | Preserved - delegates to engine |
| `AgentState` accessors | Preserved - delegating methods |
| `AgentBuilder` | Preserved - builds engine internally |
| `ContinuationCriteria` | Adapter to `ContinuationPolicy` |
| `StateProcessors` | Adapter to `ProcessorPipeline` |
| Events | Same events, just emitted from single location |

---

## Testing Strategy

1. **Golden Trace Test**: Assert exact event sequence for simple scenarios
2. **State Transition Test**: Verify `recordStep`, `failWith` produce correct state
3. **Continuation Test**: Verify guards and work signals evaluated correctly
4. **Serialization Test**: Ensure `toArray()`/`fromArray()` unchanged

---

## Files to Create/Modify

### New Files
- `Agent/Core/Engine/AgentEngine.php`
- `Agent/Core/Engine/ContinuationPolicy.php`
- `Agent/Core/Engine/ProcessorPipeline.php`
- `Agent/Core/Data/Conversation.php`
- `Agent/Core/Data/RunLog.php`
- `Agent/Core/Data/Telemetry.php`

### Modified Files
- `Agent/Agent.php` - delegate to engine
- `Agent/AgentBuilder.php` - build engine components
- `Agent/Core/Data/AgentState.php` - use sub-objects
- `Agent/Core/Data/AgentStep.php` - simplify structure

### Deprecated (later removal)
- `StepByStep/StepByStep.php` - keep for now, remove later
- Individual continuation criteria classes - adapt to guards/signals

---

## Risks and Mitigations

| Risk | Mitigation |
|------|------------|
| Event ordering changes | Golden trace test, gradual rollout |
| Processor behavior changes | Review all processors for "around" behavior |
| Serialization breaks | Keep `toArray()`/`fromArray()` keys stable |
| Performance regression | Benchmark before/after |

---

## Success Metrics

1. **Debuggability**: Single breakpoint in `AgentEngine::run()` shows full loop
2. **Code Reduction**: ~30% fewer lines in core loop logic
3. **Test Coverage**: 100% of event lifecycle covered by golden trace
4. **Comprehensibility**: New developer can understand loop in 5 minutes

---

## Appendix: Current vs Proposed Flow

### Current (scattered across 6+ methods)
```
Agent::finalStep()
  → StepByStep::finalStep()
    → markExecutionStartedIfSupported() [method_exists check]
    → while hasNextStep()
      → StepByStep::nextStep()
        → if hasProcessors() → performThroughProcessors()
          → processors.apply(state, terminal: performStep)
            → Agent::performStep() [override]
              → try
                → markStepStartedIfSupported()
                → makeNextStep() → emit AgentStepStarted
                → applyStep() → emit AgentStateUpdated
                → addExecutionTimeIfSupported()
                → onStepCompleted() → emit AgentStepCompleted
              → catch → onFailure() → emit AgentFailed
        → else → performStep() [same as above]
      → hasNextStep()
        → Agent::canContinue() → emit ContinuationEvaluated
    → onNoNextStep() → emit AgentFinished
```

### Proposed (single method)
```
AgentEngine::run()
  → state.markExecutionStarted()
  → while true
    → policy.decide() → emit ContinuationEvaluated
    → if stop → emit AgentFinished → return
    → emit AgentStepStarted
    → try
      → driver.useTools()
      → state.recordStep()
      → processors.apply()
      → state.addExecutionTime()
      → emit AgentStepCompleted
    → catch → emit AgentFailed → return
```

---

## Architecture Diagrams

### Current vs Proposed Architecture

```
┌─────────────────────────────────────────────────────────────────────────────┐
│ CURRENT: Template Method Pattern                                            │
│                                                                             │
│   StepByStep (abstract)                                                     │
│     ├── canContinue() ──────────────────┐                                  │
│     ├── makeNextStep() ─────────────────┼── 6 template methods             │
│     ├── applyStep() ────────────────────┤   to trace/debug                 │
│     ├── onNoNextStep() ─────────────────┤                                  │
│     ├── onStepCompleted() ──────────────┤                                  │
│     └── onFailure() ────────────────────┘                                  │
│           ▲                                                                 │
│           │ extends                                                         │
│     Agent (implements all 6)                                               │
│     ├── ContinuationCriteria (4 decisions, priority resolver)              │
│     └── StateProcessors (middleware chain)                                 │
└─────────────────────────────────────────────────────────────────────────────┘

                              ▼ refactor to ▼

┌─────────────────────────────────────────────────────────────────────────────┐
│ PROPOSED: Direct Engine Loop                                                │
│                                                                             │
│   AgentEngine::run()                                                        │
│     while (true)                                                            │
│       ├── policy.decide() ─────── guards + work signals                    │
│       ├── if stop → return                                                 │
│       ├── driver.useTools()                                                │
│       ├── state.recordStep()                                               │
│       ├── processors.apply() ─── sequential pipeline                      │
│       └── emit events                                                       │
│                                                                             │
│   Agent ──delegates to──▶ AgentEngine                                      │
└─────────────────────────────────────────────────────────────────────────────┘
```

### State Object Consolidation

```
┌─────────────────────────────────────────────────────────────────────────────┐
│ CURRENT: AgentState with 5+ traits                                         │
│                                                                             │
│   AgentState                                                                │
│     ├── HasSteps                                                           │
│     ├── HasMessageStore                                                    │
│     ├── HasMetadata          ──▶  10+ withX() methods                      │
│     ├── HasUsage                                                           │
│     └── HasStateInfo                                                       │
└─────────────────────────────────────────────────────────────────────────────┘

                              ▼ simplify to ▼

┌─────────────────────────────────────────────────────────────────────────────┐
│ PROPOSED: AgentState with 3 sub-objects                                    │
│                                                                             │
│   AgentState                                                                │
│     ├── Conversation (messages + metadata + cache)                         │
│     ├── RunLog (steps + currentStep)          ──▶  3 transition methods    │
│     └── Telemetry (usage + timing + status)        recordStep()            │
│                                                    failWith()              │
│                                                    addExecutionTime()      │
└─────────────────────────────────────────────────────────────────────────────┘
```

## Appendix: Team Response

This assessment compares the proposal against the current StepByStep and Agent code.
The goal is pragmatic improvement without breaking working behavior.

Critical assessment:
- The loop is split across StepByStep template methods and Agent overrides, which makes tracing harder, but the layering is not entirely redundant. StepByStep catches processor exceptions while Agent adds timing and step-level handling.
- Continuation logic is a 4-decision lattice. It is functional, but stop reasons are inferred via class-name matching, which is brittle and should be made explicit.
- The "trait explosion" claim is partially overstated. Traits and interfaces enable generic processors (HasSteps/HasUsage/etc.) and reuse across Chat/ToolUse/Agent. There is real substitution value here.
- The middleware pipeline is not purely post-step; it supports around behavior and can wrap execution. A pure post-step pipeline would change behavior.
- Simplifying continuation into guards/work signals risks losing current bootstrap and retry semantics that rely on AllowContinuation and RequestContinuation.

Ideas worth incorporating:
- Replace stop-reason inference with explicit StopReason metadata from criteria or evaluations.
- Introduce explicit interfaces for optional behaviors (e.g., CanMarkExecutionStarted, CanMarkStepStarted, CanTrackExecutionTime) to remove method_exists checks and improve static analysis.
- Add high-level state transition helpers (e.g., AgentState::recordStep(), failWith()) to reduce repetitive withX chaining while keeping immutability.
- If a single-loop entry point is desired, add a thin AgentEngine that preserves existing event order and semantics rather than a full rewrite.
- Add a "golden trace" test that locks down event ordering, especially around processors and failure paths.
