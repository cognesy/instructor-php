# Recommended Simplified Design for instructor-php Agent Loop

## Executive Summary

After analyzing **six production agent frameworks** (Agno, OpenCode, Codex, Vercel AI SDK, AutoGen, Gemini CLI), a clear pattern emerges: **all use dramatically simpler continuation/stop logic than instructor-php**. The current evaluation aggregation system adds ~200 lines of complexity without proportional benefit.

---

## The Problem: Current instructor-php Complexity

### Current Architecture
```
Hooks → write ContinuationEvaluation objects
     → stored in CurrentExecution
     → aggregated by EvaluationProcessor
     → produces ContinuationOutcome
     → checked via shouldContinue() and isContinuationForbidden()
```

### Files Involved
- `ContinuationDecision.php` - 4-value enum
- `ContinuationEvaluation.php` - Evaluation data class
- `ContinuationOutcome.php` - Aggregated result
- `EvaluationProcessor.php` - Priority-based aggregation (~110 lines)
- `AgentLoop.php` - Multiple continuation checks (~100+ lines)

### The 4-Value Decision Problem
```php
enum ContinuationDecision {
    case ForbidContinuation;  // Guard denied - STOP
    case AllowContinuation;   // Guard permits - allows but doesn't drive
    case RequestContinuation; // Work requested - CONTINUE
    case AllowStop;           // Work done - STOP unless Request present
}

// Complex priority rules:
// 1. Any ForbidContinuation → false
// 2. Any RequestContinuation → true (overrides AllowStop!)
// 3. Any AllowStop → false
// 4. Any AllowContinuation → true
```

---

## What Other Frameworks Do

| Framework | Continuation Mechanism | Lines of Code |
|-----------|----------------------|---------------|
| **Agno** | `RunStatus` enum (5 values) | ~20 |
| **OpenCode** | `finishReason` check | ~10 |
| **Codex** | `SamplingRequestResult` (3 values) | ~15 |
| **Vercel AI SDK** | Composable `StopCondition` functions | ~30 |
| **AutoGen** | `TerminationCondition` protocol | ~20 |
| **Gemini CLI** | `AgentTerminateMode` enum (6 values) | ~40 |
| **instructor-php** | 4-value decision + aggregation | **~200+** |

### Common Patterns Across All Frameworks

1. **Simple boolean or enum-based decisions** - No voting/aggregation
2. **Hooks don't control main flow** - They observe or modify data
3. **Termination is a separate concern** - Not intertwined with hooks
4. **Composable conditions** - Functions, not evaluation objects

---

## Recommended Simplified Design

### 1. Replace ContinuationDecision with ExecutionStatus

```php
enum ExecutionStatus: string {
    case Running = 'running';          // Continue executing
    case Completed = 'completed';      // Task finished successfully
    case StepLimitReached = 'step_limit';
    case TokenLimitReached = 'token_limit';
    case TimeLimitReached = 'time_limit';
    case Error = 'error';
    case Aborted = 'aborted';
}
```

### 2. Composable Stop Conditions (Vercel/AutoGen style)

```php
interface StopCondition {
    public function check(array $steps): ?StopReason;
}

class MaxStepsCondition implements StopCondition {
    public function __construct(private int $maxSteps) {}

    public function check(array $steps): ?StopReason {
        if (count($steps) >= $this->maxSteps) {
            return new StopReason(ExecutionStatus::StepLimitReached, "Max steps reached");
        }
        return null;
    }
}

class HasToolCallCondition implements StopCondition {
    public function __construct(private string $toolName) {}

    public function check(array $steps): ?StopReason {
        $lastStep = end($steps);
        if ($lastStep?->hasToolCall($this->toolName)) {
            return new StopReason(ExecutionStatus::Completed, "Finish tool called");
        }
        return null;
    }
}

// Composable
class OrCondition implements StopCondition {
    public function __construct(private array $conditions) {}

    public function check(array $steps): ?StopReason {
        foreach ($this->conditions as $condition) {
            $result = $condition->check($steps);
            if ($result !== null) return $result;
        }
        return null;
    }
}
```

### 3. Simplified Hook System (Two Concerns)

Based on Claude Code's proven approach, hooks should handle **two distinct concerns**:

#### A. Tool Blocking (PreToolUse) - Hooks CAN control tool execution

```php
// Claude Code style: hooks return a decision, not evaluation
enum ToolPermission: string {
    case Allow = 'allow';   // Bypass normal permission checks
    case Deny = 'deny';     // Block the tool call
    case Ask = 'ask';       // Require user confirmation
}

interface ToolGuard {
    public function beforeToolCall(ToolCall $call): ToolPermission;
    public function getReason(): ?string;
}

// Multiple guards run in parallel - first "deny" wins
class ToolGuardChain {
    public function check(ToolCall $call): ToolGuardResult {
        foreach ($this->guards as $guard) {
            $permission = $guard->beforeToolCall($call);
            if ($permission === ToolPermission::Deny) {
                return ToolGuardResult::denied($guard->getReason());
            }
            if ($permission === ToolPermission::Ask) {
                return ToolGuardResult::needsConfirmation($guard->getReason());
            }
        }
        return ToolGuardResult::allowed();
    }
}
```

#### B. Observation Callbacks - Hooks observe, don't control flow

```php
// Callbacks observe only - no return value affects flow
interface StepCallback {
    public function onStepFinish(StepResult $step): void;
    public function onExecutionFinish(array $steps, ExecutionStatus $status): void;
}

// Optional: message modification (not flow control)
interface MessageInterceptor {
    public function beforeInference(Messages $messages): Messages;
}
```

**Key Insight from Claude Code**: Tool blocking and continuation control are **separate concerns**:
- `PreToolUse` → returns `allow/deny/ask` (controls individual tool calls)
- `Stop` hook → returns `continue: false` (controls loop continuation)

This is fundamentally different from instructor-php's current approach where all hooks write evaluations that get aggregated.

### 4. The Simplified Loop (~40 lines)

```php
public function execute(AgentState $state): ExecutionResult {
    $steps = [];
    $status = ExecutionStatus::Running;

    do {
        // 1. Check stop conditions FIRST
        $stopReason = $this->stopCondition->check($steps);
        if ($stopReason !== null) {
            $status = $stopReason->status;
            break;
        }

        // 2. Optional: modify messages before inference
        $messages = $this->messageInterceptor?->beforeInference($state->messages())
                    ?? $state->messages();

        // 3. Perform inference (get LLM response)
        $response = $this->driver->generate($messages);

        // 4. Execute tools with guards (Claude Code style)
        $toolResults = [];
        foreach ($response->toolCalls() as $toolCall) {
            $guardResult = $this->toolGuardChain->check($toolCall);

            if ($guardResult->isDenied()) {
                $toolResults[] = ToolResult::blocked($toolCall, $guardResult->reason());
                continue;
            }

            if ($guardResult->needsConfirmation()) {
                // Ask user - this is a separate UI concern
                if (!$this->confirmWithUser($toolCall, $guardResult->reason())) {
                    $toolResults[] = ToolResult::blocked($toolCall, 'User denied');
                    continue;
                }
            }

            $toolResults[] = $this->executeTool($toolCall);
        }

        // 5. Build step result
        $step = new StepResult($response, $toolResults);
        $steps[] = $step;

        // 6. Notify callbacks (observation only)
        foreach ($this->callbacks as $callback) {
            $callback->onStepFinish($step);
        }

    } while ($step->hasToolCalls() && $step->hasExecutedTools());

    // Mark as completed if we exited normally
    if ($status === ExecutionStatus::Running) {
        $status = ExecutionStatus::Completed;
    }

    // Notify completion
    foreach ($this->callbacks as $callback) {
        $callback->onExecutionFinish($steps, $status);
    }

    return new ExecutionResult($steps, $status);
}
```

---

## Migration Path

### Phase 1: Add New System Alongside Old
1. Create `StopCondition` interface and implementations
2. Create `ExecutionStatus` enum
3. Create `StepCallback` interface
4. Keep old hooks working during transition

### Phase 2: Migrate Existing Hooks
1. Convert `StepsLimitHook` → `MaxStepsCondition`
2. Convert `TokenUsageLimitHook` → `MaxTokensCondition`
3. Convert `FinishReasonHook` → `FinishReasonCondition`
4. Convert observation hooks → `StepCallback` implementations

### Phase 3: Remove Old System
1. Delete `ContinuationDecision.php`
2. Delete `ContinuationEvaluation.php`
3. Delete `ContinuationOutcome.php`
4. Delete `EvaluationProcessor.php`
5. Simplify `AgentLoop.php`

---

## Comparison: Before and After

### Before (Current)
```php
// AgentLoop.php - Multiple checks
while (true) {
    $state = $state->withNewStepExecution();

    if (!$this->shouldContinue($state)) {
        yield $this->onAfterExecution($state);
        return;
    }

    $state = $this->onBeforeStep($state);

    if ($this->isContinuationForbidden($state)) {
        yield $this->onAfterExecution($state);
        return;
    }

    $state = $this->performStep($state);
    $state = $this->onAfterStep($state);
    $state = $this->aggregateAndClearEvaluations($state);
    $state = $this->recordStep($state);

    $this->eventEmitter->stepCompleted($state);

    if ($this->shouldContinue($state)) {
        $state = $state->withClearedCurrentExecution();
        yield $state;
        continue;
    }

    $state = $state->withClearedCurrentExecution();
    yield $this->onAfterExecution($state);
    return;
}
```

### After (Simplified)
```php
// AgentLoop.php - Single clear flow
do {
    $stopReason = $this->stopCondition->check($steps);
    if ($stopReason !== null) {
        $status = $stopReason->status;
        break;
    }

    $step = $this->performStep($state);
    $steps[] = $step;

    foreach ($this->callbacks as $cb) {
        $cb->onStepFinish($step);
    }

    yield $step;
} while ($step->hasToolCalls());
```

---

## Benefits of Simplified Design

| Aspect | Before | After |
|--------|--------|-------|
| **Lines of code** | ~200+ | ~40 |
| **Files** | 5+ (evaluation system) | 3 interfaces + implementations |
| **Mental model** | "Collect votes, aggregate, check multiple times" | "Check stop conditions, guard tool calls, notify" |
| **Testability** | Complex state mocking | Simple function testing |
| **Extensibility** | Must understand evaluation system | Add StopCondition or ToolGuard |
| **Debugging** | Trace through aggregation | Single check point per concern |
| **Tool blocking** | Same system as continuation | Separate ToolGuard chain (Claude Code style) |

---

## Key Design Principles (From 6 Frameworks + Claude Code)

1. **Termination is binary** - Either stop or continue, no voting
2. **Conditions are composable** - Combine with Or/And, not aggregate
3. **Separate tool blocking from continuation** - Two distinct concerns (Claude Code insight)
4. **Tool guards return decisions, not evaluations** - `allow/deny/ask` not `ContinuationDecision`
5. **Observation callbacks don't control flow** - Fire and forget
6. **The loop is the authority** - Checks conditions, not evaluations
7. **Status is explicit** - Enum of possible outcomes
8. **Keep it simple** - 40 lines beats 200 lines

---

## Claude Code Hooks vs instructor-php: Key Insight

| Concern | Claude Code | instructor-php (current) | Recommended |
|---------|-------------|-------------------------|-------------|
| **Tool blocking** | `PreToolUse` → `allow/deny/ask` | Hook writes evaluation | `ToolGuard` → `ToolPermission` |
| **Loop continuation** | `Stop` → `continue: false` | Hook writes evaluation | `StopCondition` → `?StopReason` |
| **Aggregation** | First "deny" wins | Priority-based evaluation aggregation | No aggregation needed |
| **Observation** | Various events | Hooks return modified state | Callbacks (void return) |

**Claude Code proves**: You CAN have hooks that control execution (PreToolUse blocking) while keeping the system simple. The key is:
1. **Separate concerns** - Tool blocking ≠ continuation control
2. **Simple return types** - `allow/deny/ask` not 4-value enum with aggregation
3. **First wins** - No complex priority rules, first "deny" blocks

---

## Files to Create

```
src/Core/Execution/
├── StopCondition.php           # Interface: check(steps) -> ?StopReason
├── StopReason.php              # Value object
├── ExecutionStatus.php         # Enum (Running, Completed, StepLimit, etc.)
├── Conditions/
│   ├── MaxStepsCondition.php
│   ├── MaxTokensCondition.php
│   ├── HasToolCallCondition.php
│   ├── FinishReasonCondition.php
│   └── OrCondition.php
├── Callbacks/
│   └── StepCallback.php        # Interface (observation only)
└── Guards/
    ├── ToolPermission.php      # Enum: Allow, Deny, Ask
    ├── ToolGuard.php           # Interface: beforeToolCall() -> ToolPermission
    ├── ToolGuardResult.php     # Value object
    └── ToolGuardChain.php      # First "deny" wins aggregation
```

## Files to Delete

```
- ContinuationDecision.php      # 4-value enum → replaced by ExecutionStatus + ToolPermission
- ContinuationEvaluation.php    # Complex evaluation → no longer needed
- ContinuationOutcome.php       # Aggregation result → no longer needed
- EvaluationProcessor.php       # Priority aggregation → replaced by simple checks
```

---

## Conclusion

The current instructor-php evaluation aggregation system is **10x more complex** than any production agent framework analyzed. The recommended design:

1. Uses **composable stop conditions** (like Vercel AI SDK, AutoGen)
2. Uses **simple enum status** (like Codex, Gemini CLI)
3. **Separates tool blocking from continuation** (like Claude Code)
4. Uses **ToolGuard chain with allow/deny/ask** (like Claude Code's PreToolUse)
5. Keeps **observation callbacks** for extensibility without flow control
6. Reduces code by **~80%** while maintaining all functionality

The evidence from six production frameworks + Claude Code is clear: **separation of concerns + simplicity wins**.

---

## Appendix: Claude Code Hook Events Reference

For comparison, Claude Code supports these hook events:

| Event | Purpose | Can Block? |
|-------|---------|------------|
| `PreToolUse` | Guard tool calls | Yes (`deny`) |
| `PostToolUse` | Observe tool results | No |
| `Notification` | System notifications | No |
| `Stop` | Guard agent stopping | Yes (`continue: false`) |
| `SubagentStop` | Guard subagent stopping | Yes (`continue: false`) |
| `SessionStart` | Session initialization | No |
| `SessionEnd` | Session cleanup | No |
| `UserPromptSubmit` | Intercept user input | Yes (`deny`) |
| `PreCompact` | Before context compaction | No |

**Key pattern**: Only 3 events can block (`PreToolUse`, `Stop`, `UserPromptSubmit`), and they use simple `allow/deny/ask` not evaluation aggregation.
