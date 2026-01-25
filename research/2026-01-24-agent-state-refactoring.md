# AgentState Refactoring: Separating Session from Execution Data

**Date**: 2026-01-24

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

## Trade-offs

### Pros
- Clean separation of session vs execution concerns
- Supports both multi-turn and mid-execution persistence
- Nullable execution enables clear "between executions" state
- Backward compatible via delegation
- Slimmer session-only serialization

### Cons
- Additional class (ExecutionState)
- Migration complexity with existing serialized data
- Two-level structure adds some cognitive overhead

---

## Open Questions

1. **AgentStatus.Pending**: Should we add a new status for "between executions"?
   - Currently: InProgress, Completed, Failed
   - Proposed: Add Pending for when execution is null

2. **Cache placement**: User said keep in session, but is this optimal?
   - Pro: Reuse cached context across executions
   - Con: Cache may become stale between executions

3. **StateInfo transition**: Keep StateInfo as deprecated alias, or break?
   - Recommendation: Keep alias for backward compat

4. **cumulativeExecutionSeconds**: Where should this live?
   - Currently in StateInfo
   - Options: Remove entirely, move to metadata, external tracking

---

## Files Affected

**New:**
- `src/Agent/Data/ExecutionState.php`
- `src/Agent/Data/SessionInfo.php`

**Modify:**
- `src/Agent/Data/AgentState.php`
- `src/Agent/Agent.php`
- `src/Agent/Data/StateInfo.php` (deprecate → SessionInfo)
- `src/Serialization/SlimAgentStateSerializer.php`
- `src/Serialization/ContinuationAgentStateSerializer.php`

**Tests:**
- All tests in `tests/Unit/Agent/`
- Feature tests accessing execution state
