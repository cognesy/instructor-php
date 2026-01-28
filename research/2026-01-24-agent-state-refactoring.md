# AgentState Refactoring: Separating Session from Execution Data

**Date**: 2026-01-24
**Updated**: 2026-01-27 (Implementation complete)
**Status**: ✅ IMPLEMENTED

---

## Implementation Summary

The refactoring has been completed with the following changes:

### New Classes Created
- `packages/agents/src/Core/Data/ExecutionState.php` - Holds all execution-specific data
- `packages/agents/src/Core/Data/SessionInfo.php` - Session identity and timing info

### Modified Files
- `packages/agents/src/Core/Data/AgentState.php` - Refactored to separate session vs execution
- `packages/agents/src/Core/Enums/AgentStatus.php` - Added `Pending` status for "between executions"
- `packages/agents/tests/Unit/Agent/AgentStateContinuationTest.php` - Updated test expectations

### Key Behavior Changes
1. `AgentState::empty()` now creates state with `execution: null` (Pending status)
2. `AgentState::forExecution()` creates state ready for execution (InProgress status)
3. `forContinuation()` clears execution state, resulting in `Pending` status
4. `hasActiveExecution()` returns true when execution is in progress
5. All execution-specific methods delegate to `ExecutionState` when present
6. Full backward compatibility maintained via constructor and method delegation

### Test Results
All 277 tests pass with the new architecture

---

## Current Architecture (Post-Refactoring)

### Package Structure
```
packages/agents/src/
├── Core/                    # AgentLoop, AgentState, data classes
├── AgentBuilder/            # Fluent builder & capabilities
├── AgentHooks/              # New unified hook system (replaces StateProcessors)
├── AgentTemplate/           # Template definitions
├── Broadcasting/            # Event broadcasting
├── Drivers/                 # Tool execution drivers
└── Serialization/           # State serialization
```

### Key Changes
1. **Agent → AgentLoop** - Core executor is now `AgentLoop`
2. **StateProcessors → Hooks** - New `HookStack` + `HookStackObserver` pattern
3. **StepResult → StepExecution** - Step + outcome bundling
4. **New `CurrentExecution`** - Transient step context (stepNumber, id, startedAt)
5. **New `transientStepCount()`** - Accounts for unrecorded current step

### Current AgentState Fields
```php
final readonly class AgentState
{
    // Identity
    public string $agentId;
    public ?string $parentAgentId;

    // Timing
    public DateTimeImmutable $createdAt;
    public DateTimeImmutable $updatedAt;

    // Session Data
    private MessageStore $store;          // Multi-section message storage
    private Metadata $metadata;           // User-defined variables
    private CachedContext $cache;         // Inference cache

    // Execution Data
    private AgentStatus $status;          // InProgress, Completed, Failed
    private StepExecutions $stepExecutions;  // Completed steps with outcomes
    private ?CurrentExecution $currentExecution;  // Transient step context
    private ?AgentStep $currentStep;      // Step being evaluated
}
```

---

## User Requirements

### Problem Statement
AgentState is designed with a single execution perspective, while the agent may be called repeatedly across a single agent session resulting in multiple agent executions:
- Execution info (startedAt, completedAt, id, status)
- Input AgentState
- Output AgentState
- Execution result (success/failure/response)

### Key Constraints
1. **Agent is stateless executor** - Agent class should remain stateless
2. **Tracking agent executions is outside Agent scope** - External code handles execution history
3. **Serialization support** - Need stop/resume capability
4. **Opportunity to slim down AgentState**

### User's Data Classification

**Session-persistent (keep across executions):**
- `MessageStore $store`
- `Metadata $variables`
- `agentId`
- `parentAgentId`
- `$cache`

**Execution-specific (transient):**
- `status`
- `currentStep`
- `usage`
- `currentStepStartedAt`
- `executionStartedAt`
- `stepResults`

### User's Design Goals
1. AgentState should support **idempotent re-execution**
2. Transient attributes only worth storing for **mid-execution persistence** (restart considering tool executions completed so far)
3. Separate **transient state (mid-execution)** from **accumulated session data (multi-turn)**
4. Allow both models: with and without mid-execution state persistence

---

## Proposed Design

### Structure Overview

```
AgentState
├── Session Data (always present, enables re-execution)
│   ├── agentId: string
│   ├── parentAgentId: ?string
│   ├── store: MessageStore
│   ├── metadata: Metadata
│   ├── cache: CachedContext
│   └── sessionInfo: SessionInfo (id, startedAt, updatedAt)
│
└── Execution Data (optional - null when between executions)
    └── execution: ?ExecutionState
        ├── executionId: string
        ├── status: AgentStatus
        ├── startedAt: DateTimeImmutable
        ├── currentStepStartedAt: ?DateTimeImmutable
        ├── stepResults: StepResults
        └── usage: Usage
```

### Key Design Decisions

#### 1. ExecutionState is Optional (nullable)
- When `null`: Agent is between executions, ready for fresh start
- When present: Agent is mid-execution or just completed
- Enables clean separation of two persistence models

#### 2. No Execution History in AgentState
- External concern - AgentState doesn't track past executions
- Keeps AgentState focused on current/resumable state
- Reduces memory footprint

#### 3. Cache Stays in Session Data
- User specified cache should persist across executions
- Makes sense for inference optimization across multi-turn

#### 4. SessionInfo Simplified
- Renamed from StateInfo
- Removed `cumulativeExecutionSeconds` (execution-level concern)
- Contains only: id, startedAt, updatedAt

### New Classes

#### ExecutionState
```php
final readonly class ExecutionState
{
    public function __construct(
        public string $executionId,
        public AgentStatus $status,
        public DateTimeImmutable $startedAt,
        public ?DateTimeImmutable $currentStepStartedAt,
        public StepResults $stepResults,
        public Usage $usage,
    ) {}

    public static function start(): self;
    public function currentStep(): ?AgentStep;
    public function stepCount(): int;
    public function continuationOutcome(): ?ContinuationOutcome;

    // Immutable mutators
    public function withStatus(AgentStatus $status): self;
    public function withStepResult(StepResult $result): self;
    public function markStepStarted(): self;
    public function withAccumulatedUsage(Usage $usage): self;

    public function toArray(): array;
    public static function fromArray(array $data): self;
}
```

#### SessionInfo
```php
final readonly class SessionInfo
{
    public function __construct(
        private string $id,
        private DateTimeImmutable $startedAt,
        private DateTimeImmutable $updatedAt,
    ) {}

    public static function new(): self;
    public function touch(): self;

    public function toArray(): array;
    public static function fromArray(array $data): self;
}
```

### Updated AgentState API

```php
final readonly class AgentState
{
    // Session data
    public string $agentId;
    public ?string $parentAgentId;
    private MessageStore $store;
    private Metadata $metadata;
    private CachedContext $cache;
    private SessionInfo $sessionInfo;

    // Execution data (nullable)
    private ?ExecutionState $execution;

    // === Backward-compatible accessors ===
    public function status(): AgentStatus;        // delegates to execution
    public function currentStep(): ?AgentStep;
    public function stepResults(): StepResults;
    public function stepCount(): int;
    public function usage(): Usage;
    public function executionStartedAt(): ?DateTimeImmutable;

    // === New execution access ===
    public function execution(): ?ExecutionState;
    public function hasActiveExecution(): bool;

    // === Lifecycle ===
    public function startExecution(): self;       // Creates fresh ExecutionState
    public function endExecution(): self;         // Sets execution to null
    public function forContinuation(): self;      // Alias for endExecution()
}
```

### Serialization Models

#### Model A: Session-only (multi-turn, fresh execution)
```php
[
    'agent_id' => ...,
    'parent_agent_id' => ...,
    'session_info' => [...],
    'metadata' => [...],
    'cache' => [...],
    'message_store' => [...],
    // execution: not included - starts fresh
]
```

#### Model B: Full state (mid-execution persistence)
```php
[
    'agent_id' => ...,
    'parent_agent_id' => ...,
    'session_info' => [...],
    'metadata' => [...],
    'cache' => [...],
    'message_store' => [...],
    'execution' => [              // Optional: include for resume
        'execution_id' => ...,
        'status' => ...,
        'started_at' => ...,
        'step_results' => [...],
        'usage' => [...],
    ],
]
```

---

---

## Final Design Direction

### Core Principles

1. **AgentState = Session + Current Execution**
   - Contains everything needed for the CURRENT execution
   - Prior executions are NOT part of AgentState

2. **External Execution Tracking**
   - Code external to core agent stores completed executions
   - Enables: logging, introspection, debugging, rewind

3. **Rewind Capability**
   - External system can restore agent to any historical state
   - Take prior execution data → reconstruct AgentState

4. **Introspection is Optional**
   - Niche capability, implemented as a tool
   - Most use cases don't need access to prior executions

---

## Proposed Model

### AgentState (Session + Current Execution)

```php
final readonly class AgentState
{
    // === SESSION IDENTITY ===
    public string $agentId;
    public ?string $parentAgentId;
    public DateTimeImmutable $createdAt;
    public DateTimeImmutable $updatedAt;

    // === SESSION DATA ===
    private MessageStore $store;      // Conversation history
    private Metadata $metadata;       // User variables

    // === CURRENT EXECUTION ===
    private Execution $execution;     // Always present during agent run
}
```

### Execution (Current Execution State)

```php
final readonly class Execution
{
    public string $executionId;
    public DateTimeImmutable $startedAt;
    public ?DateTimeImmutable $completedAt;
    public AgentStatus $status;

    // Steps completed so far in this execution
    public StepExecutions $stepExecutions;

    // Current step being processed (transient)
    public ?AgentStep $currentStep;

    // Inference cache for this execution
    public CachedContext $cache;
}
```

### External: ExecutionRecord (Stored by External System)

```php
// Not part of core agent - managed externally
final readonly class ExecutionRecord
{
    public string $executionId;
    public string $agentId;
    public DateTimeImmutable $startedAt;
    public DateTimeImmutable $completedAt;
    public AgentStatus $finalStatus;
    public StopReason $stopReason;

    // For rewind capability
    public AgentState $finalState;      // State at execution end
    public StepExecutions $steps;       // All steps in this execution
    public Usage $totalUsage;
}
```

---

## Lifecycle Flow

```
┌─────────────────────────────────────────────────────────┐
│  1. Agent Session Starts                                │
│     AgentState created with fresh Execution             │
└─────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────┐
│  2. Execution Runs                                      │
│     - Steps added to execution.stepExecutions           │
│     - currentStep updated during processing             │
│     - Messages accumulated in store                     │
└─────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────┐
│  3. Execution Completes                                 │
│     - execution.status = Completed/Failed               │
│     - execution.completedAt set                         │
│     - EXTERNAL: ExecutionRecord created & stored        │
└─────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────┐
│  4. Next User Message (Multi-turn)                      │
│     - AgentState.forNextExecution() called              │
│     - Fresh Execution created                           │
│     - Session data (store, metadata) preserved          │
└─────────────────────────────────────────────────────────┘
                          ↓
                    (repeat 2-4)
```

---

## Key Methods

### AgentState

```php
// Start fresh execution (called at beginning)
public function beginExecution(): self;

// Reset for next execution (preserves session, clears execution)
public function forNextExecution(): self;

// Restore from historical state (rewind)
public static function fromExecutionRecord(ExecutionRecord $record): self;
```

### External Tracking (not in core)

```php
interface ExecutionStore
{
    public function record(AgentState $finalState): ExecutionRecord;
    public function getByAgent(string $agentId): array;
    public function getById(string $executionId): ?ExecutionRecord;
}
```

### Optional Introspection Capability

```php
// Implemented as a tool, not core functionality
class ExecutionHistoryTool implements ToolInterface
{
    public function __construct(private ExecutionStore $store) {}

    public function execute(array $args): string {
        // Agent can query its own execution history
        $records = $this->store->getByAgent($args['agentId']);
        return json_encode($records);
    }
}
```

---

## Trade-offs

### Pros
- Clean separation of session vs execution concerns
- Supports both multi-turn and mid-execution persistence
- Nullable execution enables clear "between executions" state
- Backward compatible via delegation
- Slimmer session-only serialization

### Cons
- Additional class (Execution)
- Migration complexity with existing serialized data
- Two-level structure adds some cognitive overhead

---

## Resolved Questions

1. **AgentStatus.Pending**: ✅ Added
   - New enum value: `case Pending = 'pending';`
   - Returned when `execution` is null (between executions)

2. **Cache placement**: ✅ Kept in session data
   - Cache is part of session, cleared by `forContinuation()`
   - This allows reuse across executions within same session

3. **StateInfo transition**: ✅ Created new SessionInfo
   - New `SessionInfo` class created (not used directly in AgentState yet)
   - AgentState continues using direct fields for createdAt/updatedAt

4. **cumulativeExecutionSeconds**: ✅ Removed
   - Not part of the new structure
   - Can be computed from ExecutionState durations if needed externally

---

## Files Affected

**New:**
- `packages/agents/src/Core/Data/ExecutionState.php` ✅
- `packages/agents/src/Core/Data/SessionInfo.php` ✅

**Modified:**
- `packages/agents/src/Core/Data/AgentState.php` ✅
- `packages/agents/src/Core/Enums/AgentStatus.php` ✅
- `packages/agents/tests/Unit/Agent/AgentStateContinuationTest.php` ✅

**Future Work (not yet needed):**
- `src/Serialization/SlimAgentStateSerializer.php` - May need updates for new format
- `src/Serialization/ContinuationAgentStateSerializer.php` - May need updates for new format
- External `ExecutionStore` interface and implementation
