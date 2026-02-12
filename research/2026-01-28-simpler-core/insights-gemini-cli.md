# Comparison Analysis: Google Gemini CLI vs instructor-php Agent Loop & Hooks

## Executive Summary

**Gemini CLI** uses a clean turn-based loop with an enum-based `AgentTerminateMode` for termination. The hook system (11 events) can block or stop execution via simple boolean flags (`continue: false`, `decision: 'block'`). No evaluation aggregation - hooks return simple JSON objects with clear intent.

---

## Key Differences

### 1. Termination Modes

| Aspect | Gemini CLI | instructor-php |
|--------|------------|---------------|
| **Mechanism** | `AgentTerminateMode` enum | 4-value `ContinuationDecision` aggregation |
| **Values** | 6 clear states | 4 decisions + aggregation rules |
| **Check** | Single enum comparison | Multiple evaluation checks |
| **Mental model** | "What terminated us?" | "Collect votes, aggregate" |

**Gemini CLI** - Clear termination enum:
```typescript
enum AgentTerminateMode {
  ERROR = 'ERROR',
  TIMEOUT = 'TIMEOUT',
  GOAL = 'GOAL',              // Successfully completed
  MAX_TURNS = 'MAX_TURNS',    // Hit turn limit
  ABORTED = 'ABORTED',        // User cancelled
  ERROR_NO_COMPLETE_TASK_CALL = 'ERROR_NO_COMPLETE_TASK_CALL',
}
```

**instructor-php** - Complex aggregation:
```php
enum ContinuationDecision {
    case ForbidContinuation;  // Must aggregate
    case AllowContinuation;   // Must aggregate
    case RequestContinuation; // Must aggregate
    case AllowStop;           // Must aggregate
}
// Priority rules: Forbid > Request > Stop > Allow
```

### 2. Loop Structure

**Gemini CLI** - Simple turn loop with single exit:
```typescript
while (true) {
    // Check termination conditions
    if (turnCounter >= maxTurns) {
        terminateMode = AgentTerminateMode.MAX_TURNS;
        break;
    }

    if (signal.aborted) {
        terminateMode = AgentTerminateMode.ABORTED;
        break;
    }

    // Execute turn
    const result = await this.executeTurn(chat, currentMessage, turnCounter, signal);

    // Check if task completed
    if (result.completed) {
        terminateMode = AgentTerminateMode.GOAL;
        return extractResult(result);
    }

    // Prepare for next turn
    currentMessage = result.nextMessage;
    turnCounter++;
}

// Recovery attempt for non-success terminations
if (terminateMode !== AgentTerminateMode.GOAL) {
    const recovered = await attemptRecovery(chat, terminateMode);
    if (recovered) return recovered;
}
```

**instructor-php** - Multiple continuation checks:
```php
while (true) {
    $state = $state->withNewStepExecution();
    if (!$this->shouldContinue($state)) { yield ...; return; }
    $state = $this->onBeforeStep($state);
    if ($this->isContinuationForbidden($state)) { yield ...; return; }
    $state = $this->performStep($state);
    $state = $this->onAfterStep($state);
    $state = $this->aggregateAndClearEvaluations($state);
    if ($this->shouldContinue($state)) { yield $state; continue; }
    yield $this->onAfterExecution($state);
    return;
}
```

### 3. Hook System

| Aspect | Gemini CLI | instructor-php |
|--------|------------|---------------|
| **Events** | 11 named events | 8 HookTypes |
| **Output** | Simple JSON with flags | State modification + evaluations |
| **Flow control** | `continue: false` or `decision: 'block'` | Write ContinuationEvaluation |
| **Execution** | Subprocess with JSON I/O | In-process interface |

**Gemini CLI** - Hooks return simple JSON:
```typescript
// Hook output structure
interface HookOutput {
  continue?: boolean;      // false = stop execution
  decision?: 'allow' | 'block' | 'deny';
  reason?: string;
  stopReason?: string;
  // Event-specific modifications...
}

// Example hook (subprocess)
// Input: JSON with event data
// Output: JSON with decision
{
  "continue": false,
  "decision": "block",
  "reason": "Operation not allowed"
}
```

**instructor-php** - Hooks write evaluations:
```php
interface Hook {
    public function appliesTo(): array;
    public function process(AgentState $state, HookType $event): AgentState;
}

// Must write evaluation for flow control
return $state->withEvaluation(new ContinuationEvaluation(
    criterionClass: self::class,
    decision: ContinuationDecision::ForbidContinuation,
    reason: 'Step limit reached',
    stopReason: StopReason::StepsLimitReached,
));
```

### 4. Task Completion Detection

**Gemini CLI** - Special tool call:
```typescript
const TASK_COMPLETE_TOOL_NAME = 'complete_task';

// Check in turn result
private isTaskComplete(toolCalls: ToolCallRequestInfo[]): boolean {
    return toolCalls.some(tc => tc.name === TASK_COMPLETE_TOOL_NAME);
}

// Agent explicitly signals completion
if (result.completed) {
    terminateMode = AgentTerminateMode.GOAL;
    return extractResult(result);
}
```

**instructor-php** - Evaluation-based:
```php
// Hooks write evaluations
// ToolCallPresenceHook checks for tool calls
// FinishReasonHook checks LLM finish reason
// All aggregated via EvaluationProcessor
```

---

## Gemini CLI Architecture Highlights

### 1. Hook Output Classes with Clear Methods

```typescript
class DefaultHookOutput {
    // Simple boolean checks
    shouldStopExecution(): boolean {
        return this.continue === false;
    }

    isBlockingDecision(): boolean {
        return this.decision === 'block' || this.decision === 'deny';
    }

    getEffectiveReason(): string {
        return this.stopReason || this.reason || 'No reason provided';
    }
}
```

### 2. Recovery Mechanism

Grace period for non-goal terminations:
```typescript
// If hit max turns or timeout, give agent one more chance
private async attemptRecovery(
    chat: GeminiChat,
    terminateMode: AgentTerminateMode,
): Promise<AgentExecutionResult<TOutput> | null> {
    // Only for recoverable modes
    if (!isRecoverable(terminateMode)) return null;

    // Send recovery prompt
    const recoveryPrompt = this.getRecoveryPrompt(terminateMode);

    // One more turn with grace period timeout
    const result = await this.executeTurn(
        chat, recoveryPrompt, maxTurns + 1, gracePeriodSignal
    );

    if (result.completed) {
        return this.extractResult(result);  // Success!
    }
    return null;  // Failed to recover
}
```

### 3. Stream Event Processing

```typescript
enum StreamEventType {
    USAGE_METADATA = 'usageMetadata',
    TEXT_CHUNK = 'textChunk',
    TOOL_CALL = 'toolCall',
    BLOCKED = 'blocked',
    ERROR = 'error',
    GROUNDING_METADATA = 'groundingMetadata',
}

// Process stream in executeTurn
for await (const event of stream) {
    switch (event.type) {
        case StreamEventType.TEXT_CHUNK:
            fullText += event.data.text;
            break;
        case StreamEventType.TOOL_CALL:
            toolCalls.push(event.data);
            break;
        case StreamEventType.BLOCKED:
            throw new AgentExecutionBlockedError(event.data.reason);
    }
}
```

---

## Simplification Opportunities for instructor-php

### 1. Replace evaluation system with termination mode enum

**Current:**
```php
enum ContinuationDecision {
    case ForbidContinuation;
    case AllowContinuation;
    case RequestContinuation;
    case AllowStop;
}
// + EvaluationProcessor aggregation
```

**Simplified (Gemini-style):**
```php
enum TerminationMode: string {
    case Running = 'running';          // Still going
    case Completed = 'completed';      // Task finished successfully
    case MaxSteps = 'max_steps';       // Hit step limit
    case Timeout = 'timeout';          // Time limit exceeded
    case Error = 'error';              // Error occurred
    case Aborted = 'aborted';          // User cancelled
}
```

### 2. Simplify hook output

**Current:**
```php
interface Hook {
    public function process(AgentState $state, HookType $event): AgentState;
}
// Must write evaluations via $state->withEvaluation(...)
```

**Simplified (Gemini-style):**
```php
class HookOutput {
    public bool $continue = true;
    public ?string $decision = null;  // 'allow', 'block', 'deny'
    public ?string $reason = null;

    public function shouldStopExecution(): bool {
        return !$this->continue;
    }

    public function isBlocking(): bool {
        return $this->decision === 'block' || $this->decision === 'deny';
    }
}
```

### 3. Single loop with clear termination

**Simplified (Gemini-style):**
```php
public function execute(AgentState $state): ExecutionResult {
    $terminationMode = TerminationMode::Running;

    while ($terminationMode === TerminationMode::Running) {
        // Check limits first
        if ($this->stepCount >= $this->maxSteps) {
            $terminationMode = TerminationMode::MaxSteps;
            break;
        }

        if ($this->isTimedOut()) {
            $terminationMode = TerminationMode::Timeout;
            break;
        }

        // Execute step
        $step = $this->performStep($state);
        $this->stepCount++;

        // Check for task completion
        if ($step->isCompleted()) {
            $terminationMode = TerminationMode::Completed;
            break;
        }

        // Check for more work
        if (!$step->hasToolCalls()) {
            $terminationMode = TerminationMode::Completed;
            break;
        }
    }

    // Optional: recovery attempt for non-success
    if ($terminationMode !== TerminationMode::Completed) {
        $recovered = $this->attemptRecovery($terminationMode);
        if ($recovered) {
            $terminationMode = TerminationMode::Completed;
        }
    }

    return new ExecutionResult($steps, $terminationMode);
}
```

### 4. Hook returns simple output, doesn't modify state

**Current:** Hooks return modified AgentState
**Simplified:** Hooks return HookOutput

```php
interface Hook {
    public function handle(HookContext $context): HookOutput;
}

// In loop
$hookOutput = $this->fireBeforeStepHooks($context);
if ($hookOutput->shouldStopExecution()) {
    $terminationMode = TerminationMode::fromHook($hookOutput);
    break;
}
```

---

## Side-by-Side: Loop Logic

### Gemini CLI (~50 lines core)
```typescript
while (true) {
    if (turnCounter >= maxTurns) {
        terminateMode = AgentTerminateMode.MAX_TURNS;
        break;
    }

    if (signal.aborted) {
        terminateMode = AgentTerminateMode.ABORTED;
        break;
    }

    const result = await this.executeTurn(chat, currentMessage, turnCounter, signal);

    if (result.completed) {
        terminateMode = AgentTerminateMode.GOAL;
        return extractResult(result);
    }

    currentMessage = result.nextMessage;
    turnCounter++;
}

if (isRecoverable(terminateMode)) {
    const recovered = await attemptRecovery(chat, terminateMode);
    if (recovered) return recovered;
}

return { terminate_reason: terminateMode, result: null };
```

### instructor-php Current (~100+ lines)
```php
while (true) {
    $state = $state->withNewStepExecution();
    if (!$this->shouldContinue($state)) { yield ...; return; }
    $state = $this->onBeforeStep($state);
    if ($this->isContinuationForbidden($state)) { yield ...; return; }
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
    yield $this->onAfterExecution($state);
    return;
}
```

---

## Summary: Key Insights from Gemini CLI

| Pattern | Gemini CLI | Benefit |
|---------|------------|---------|
| **Termination** | 6-value enum | Clear, exhaustive states |
| **Hooks** | Return JSON with flags | Simple, subprocess-safe |
| **Completion** | `complete_task` tool | Explicit agent signal |
| **Recovery** | Grace period turn | Graceful degradation |
| **Loop** | Single while with breaks | One exit point per condition |

**Gemini's philosophy:** Termination is a clear enum state. Hooks return simple objects with boolean flags. Recovery is built-in for non-success cases.

**Total complexity reduction opportunity:**
- Replace 4-value decision → 6-value termination enum
- Remove evaluation aggregation → direct enum assignment
- Simplify hook output → JSON-like object with flags
- Remove ~200 lines of continuation logic
- Add recovery mechanism for graceful degradation
