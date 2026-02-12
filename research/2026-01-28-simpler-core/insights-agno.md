# Comparison Analysis: Agent Loop & Hooks Implementation

## Executive Summary

The **instructor-php** implementation has a sophisticated but complex evaluation-based flow control system. **Agno** uses a much simpler status-based approach. There are significant opportunities to simplify instructor-php.

---

## Key Differences

### 1. Continuation Control

| Aspect | instructor-php | Agno |
|--------|---------------|------|
| **Mechanism** | 4-value `ContinuationDecision` enum with aggregation | Simple `RunStatus` enum |
| **Flow control** | Hooks write evaluations → aggregated into outcome | Direct status assignment |
| **Complexity** | ~200 lines across 4 files | ~20 lines in one enum |

**instructor-php** (4 decisions to aggregate):
```php
enum ContinuationDecision {
    case ForbidContinuation;  // Guard denial
    case AllowContinuation;   // Guard approval
    case RequestContinuation; // Work requested
    case AllowStop;           // Work complete
}
```

**Agno** (direct status):
```python
class RunStatus(Enum):
    pending = "pending"
    running = "running"
    completed = "completed"
    error = "error"
    cancelled = "cancelled"
    paused = "paused"
```

### 2. Hook System Complexity

| Aspect | instructor-php | Agno |
|--------|---------------|------|
| **Hook types** | 8 event types (ExecutionStart, BeforeStep, AfterStep, etc.) | 2 types (pre/post) |
| **Hook interface** | Interface with `appliesTo()` + `process()` | Simple callable |
| **Flow control** | Hooks write ContinuationEvaluation objects | Hooks can throw exceptions |
| **Aggregation** | EvaluationProcessor aggregates decisions | No aggregation needed |

**instructor-php hook**:
```php
class StepsLimitHook implements Hook {
    public function appliesTo(): array {
        return [HookType::BeforeStep];
    }

    public function process(AgentState $state, HookType $event): AgentState {
        if ($state->stepCount() >= $this->maxSteps) {
            return $state->withEvaluation(new ContinuationEvaluation(
                criterionClass: self::class,
                decision: ContinuationDecision::ForbidContinuation,
                reason: 'Step limit reached',
                stopReason: StopReason::StepsLimitReached,
            ));
        }
        return $state->withEvaluation(
            ContinuationEvaluation::fromDecision(
                self::class,
                ContinuationDecision::AllowStop,
            )
        );
    }
}
```

**Agno hook** (equivalent):
```python
@hook
def steps_limit_hook(run_input: RunInput, agent: Agent):
    if agent.session.step_count >= agent.max_steps:
        raise StepLimitExceeded("Step limit reached")
```

### 3. Loop Logic

**instructor-php** `iterate()` - Complex:
```php
while (true) {
    $state = $state->withNewStepExecution();
    if (!$this->shouldContinue($state)) {
        yield $this->onAfterExecution($state);
        return;
    }
    $state = $this->onBeforeStep($state);

    // Special forbidden check
    if ($this->isContinuationForbidden($state)) {
        yield $this->onAfterExecution($state);
        return;
    }

    $state = $this->performStep($state);
    $state = $this->onAfterStep($state);
    $state = $this->aggregateAndClearEvaluations($state);  // Complex
    $state = $this->recordStep($state);

    if ($this->shouldContinue($state)) {
        yield $state;
        continue;
    }
    yield $this->onAfterExecution($state);
    return;
}
```

**Agno** `_run()` - Simple:
```python
try:
    register_run(run_response.run_id)

    # Pre-hooks
    deque(self._execute_pre_hooks(...), maxlen=0)

    # Build context, call model
    model_response = self.model.response(...)

    # Check cancellation
    raise_if_cancelled(run_response.run_id)

    # Post-hooks
    deque(self._execute_post_hooks(...), maxlen=0)

    run_response.status = RunStatus.completed

except RunCancelledException:
    run_response.status = RunStatus.cancelled
```

---

## Simplification Opportunities

### 1. Replace 4-value decision with 2-value

Current:
- `ForbidContinuation` (guard denied)
- `AllowContinuation` (guard permits)
- `RequestContinuation` (work requested)
- `AllowStop` (work done)

Simplified:
- `Continue` - work to do
- `Stop` - done or forbidden

The distinction between "AllowContinuation" and "AllowStop" is subtle and requires complex aggregation. Guards can simply set `Stop` when limits are reached.

### 2. Remove evaluation aggregation

Current flow:
```
Hook → writes ContinuationEvaluation → collected in state
     → EvaluationProcessor::shouldContinue() aggregates all
     → ContinuationOutcome produced
     → Loop checks outcome
```

Simplified flow (like Agno):
```
Hook → returns modified state with status
     → Loop checks status directly
```

### 3. Simplify shouldContinue()

Current (`AgentLoop.php:176-194`):
```php
protected function shouldContinue(AgentState $state): bool {
    if ($state->status() === AgentStatus::Failed) {
        return false;
    }

    $precomputedOutcome = $state->currentExecution()?->continuationOutcome();
    if ($precomputedOutcome !== null) {
        return $precomputedOutcome->shouldContinue();
    }

    $lastOutcome = $state->stepExecutions()->lastOutcome();
    if ($lastOutcome !== null) {
        return $lastOutcome->shouldContinue();
    }

    return $state->stepCount() === 0;
}
```

Could become:
```php
protected function shouldContinue(AgentState $state): bool {
    return $state->status()->isRunning()
        && !$state->stopRequested();
}
```

### 4. Reduce hook event types

Current 8 types could reduce to 3:
- `BeforeStep` (covers ExecutionStart + BeforeStep + BeforeInference)
- `AfterStep` (covers AfterStep + AfterInference)
- `OnError`

Or follow Agno's model: just `pre_hooks` and `post_hooks` lists.

### 5. Use exceptions for hard stops

Instead of `ForbidContinuation` evaluation, use exceptions:
```php
// Current
$state->withEvaluation(new ContinuationEvaluation(
    decision: ContinuationDecision::ForbidContinuation,
    ...
));

// Simplified (like Agno)
throw new StopRequestedException(StopReason::StepsLimitReached);
```

### 6. Remove files that could be eliminated

With simplification:
- `ContinuationEvaluation.php` → remove
- `ContinuationOutcome.php` → simplify to just `StopReason`
- `EvaluationProcessor.php` → remove entirely
- `ContinuationDecision.php` → reduce to 2 values or remove

---

## Recommended Simplified Design

### New AgentStatus
```php
enum AgentStatus: string {
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
    case Stopped = 'stopped';  // Clean stop by guard/limit
}
```

### New Hook Interface
```php
interface Hook {
    public function process(AgentState $state): AgentState;
}

// Optional: hooks can throw to force stop
throw new StopAgentException(StopReason::StepsLimitReached);
```

### Simplified StepsLimitHook
```php
final class StepsLimitHook implements Hook {
    public function __construct(private int $maxSteps) {}

    public function process(AgentState $state): AgentState {
        if ($state->stepCount() >= $this->maxSteps) {
            throw new StopAgentException(
                StopReason::StepsLimitReached,
                "Step limit {$this->maxSteps} reached"
            );
        }
        return $state;
    }
}
```

### Simplified Loop
```php
public function iterate(AgentState $state): iterable {
    $state = $this->runHooks($state, 'before_execution');

    while ($state->status()->isRunning()) {
        try {
            $state = $this->runHooks($state, 'before_step');
            $state = $this->performStep($state);
            $state = $this->runHooks($state, 'after_step');

            // Simple check: did step produce tool calls?
            if (!$state->hasToolCallsToExecute()) {
                $state = $state->withStatus(AgentStatus::Completed);
            }

            yield $state;
        } catch (StopAgentException $e) {
            $state = $state
                ->withStatus(AgentStatus::Stopped)
                ->withStopReason($e->reason);
            break;
        }
    }

    yield $this->runHooks($state, 'after_execution');
}
```

---

## Summary

| Aspect | Current | Simplified | Reduction |
|--------|---------|------------|-----------|
| Decision types | 4 | 2 (or exception) | 50-75% |
| Evaluation files | 3 | 0-1 | 67-100% |
| Hook event types | 8 | 3-4 | 50-62% |
| shouldContinue() lines | 18 | 3-4 | ~80% |
| Aggregation logic | ~110 lines | 0 | 100% |

The Agno approach proves a simpler model works well for production agent frameworks. The instructor-php evaluation aggregation system adds complexity without proportional benefit. A simpler status-based or exception-based approach would be easier to understand, maintain, and debug.
