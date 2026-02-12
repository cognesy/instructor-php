# Comparison Analysis: OpenCode vs instructor-php Agent Loop & Hooks

## Executive Summary

**OpenCode** uses an elegantly simple approach: a `while(true)` loop that checks LLM `finishReason` to decide continuation. No complex evaluation aggregation, no multi-type decision enums. **instructor-php** could significantly simplify by adopting this pattern.

---

## Key Differences

### 1. Continuation Control

| Aspect | OpenCode | instructor-php |
|--------|----------|---------------|
| **Mechanism** | Check `finishReason` from LLM | 4-value `ContinuationDecision` with aggregation |
| **Logic** | `if (finish !== "tool-calls") break` | Multiple hooks write evaluations → aggregate → outcome |
| **Lines of code** | ~10 lines | ~200+ lines across 4 files |
| **Mental model** | "Stop when LLM says done" | "Collect votes from all guards, aggregate, decide" |

**OpenCode** - One simple check (`prompt.ts:295-302`):
```typescript
if (
    lastAssistant?.finish &&
    !["tool-calls", "unknown"].includes(lastAssistant.finish) &&
    lastUser.id < lastAssistant.id
) {
    log.info("exiting loop", { sessionID })
    break  // Natural completion
}
```

**instructor-php** - Complex multi-step process:
```php
// 1. Hooks write evaluations during processing
$state->withEvaluation(new ContinuationEvaluation(
    criterionClass: self::class,
    decision: ContinuationDecision::ForbidContinuation,
    ...
));

// 2. AgentLoop aggregates all evaluations
$state = $this->aggregateAndClearEvaluations($state);

// 3. Check precomputed outcome
$precomputedOutcome = $state->currentExecution()?->continuationOutcome();
if ($precomputedOutcome !== null) {
    return $precomputedOutcome->shouldContinue();
}

// 4. Also check last step outcome
$lastOutcome = $state->stepExecutions()->lastOutcome();
```

### 2. Hook System

| Aspect | OpenCode | instructor-php |
|--------|----------|---------------|
| **Hook types** | Simple input/output mutation | 8 event types with appliesTo() |
| **Flow control** | Hooks don't control flow | Hooks write evaluations for flow control |
| **Pattern** | `trigger(name, input, output)` | `Hook.process(state, event): state` |
| **Priority** | First-come, all run | Priority-based ordering |

**OpenCode** hooks - Simple mutation pattern:
```typescript
// Definition
interface Hooks {
    "experimental.chat.system.transform"?: (
        input: { sessionID: string; model: Model },
        output: { system: string[] }  // Mutate this
    ) => void

    "tool.execute.before"?: (
        input: { tool: string; sessionID: string },
        output: { args: any }  // Mutate this
    ) => void
}

// Invocation
await Plugin.trigger("experimental.chat.system.transform",
    { sessionID, model },
    { system: systemPrompts }  // Output gets mutated
)
```

**instructor-php** hooks - Complex evaluation-based:
```php
interface Hook {
    public function appliesTo(): array;  // [HookType::BeforeStep, ...]

    public function process(AgentState $state, HookType $event): AgentState;
}

// Hook must write evaluations for flow control
class StepsLimitHook implements Hook {
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

### 3. Stop Conditions

**OpenCode** - Natural LLM-driven:
```typescript
// Finish reasons that exit loop:
"stop"           // Model explicitly stopped
"length"         // Output length limit
"content-filter" // Safety filter
"other"          // Model internal reason

// Finish reasons that continue:
"tool-calls"     // More tools to execute
"unknown"        // Unclear, retry
```

**instructor-php** - Complex evaluation system:
```php
enum ContinuationDecision {
    case ForbidContinuation;  // Guard denial - STOP
    case AllowContinuation;   // Guard approval - permits but doesn't drive
    case RequestContinuation; // Work requested - CONTINUE
    case AllowStop;           // Work done - STOP unless Request present
}

// Resolution priority:
// 1. Any ForbidContinuation → false (guard denied)
// 2. Any RequestContinuation → true (work requested, overrides AllowStop)
// 3. Any AllowStop → false (work driver finished)
// 4. Any AllowContinuation (no Stop) → true (guards permit, bootstrap)
```

### 4. Step Limits

**OpenCode** - Inject message, let LLM decide:
```typescript
const maxSteps = agent.steps ?? Infinity
const isLastStep = step >= maxSteps

if (isLastStep) {
    messages.push({
        role: "assistant",
        content: MAX_STEPS  // "You've reached max steps, wrap up"
    })
}
// LLM naturally stops after seeing this message
```

**instructor-php** - Guard hook with evaluation:
```php
class StepsLimitHook implements Hook {
    public function appliesTo(): array {
        return [HookType::BeforeStep];
    }

    public function process(AgentState $state, HookType $event): AgentState {
        $currentSteps = ($this->stepCounter)($state);
        $exceeded = $currentSteps >= $this->maxSteps;

        $evaluation = new ContinuationEvaluation(
            criterionClass: self::class,
            decision: $exceeded
                ? ContinuationDecision::ForbidContinuation
                : ContinuationDecision::AllowStop,
            reason: $exceeded
                ? sprintf('Step limit reached: %d/%d', $currentSteps, $this->maxSteps)
                : sprintf('Steps under limit: %d/%d', $currentSteps, $this->maxSteps),
            context: ['currentSteps' => $currentSteps, 'maxSteps' => $this->maxSteps],
            stopReason: $exceeded ? StopReason::StepsLimitReached : null,
        );

        return $state->withEvaluation($evaluation);
    }
}
```

### 5. Doom Loop Detection

**OpenCode** - Simple pattern matching:
```typescript
const DOOM_LOOP_THRESHOLD = 3

const parts = await MessageV2.parts(input.assistantMessage.id)
const lastThree = parts.slice(-DOOM_LOOP_THRESHOLD)

if (
    lastThree.length === DOOM_LOOP_THRESHOLD &&
    lastThree.every(p =>
        p.type === "tool" &&
        p.tool === value.toolName &&
        JSON.stringify(p.state.input) === JSON.stringify(value.input)
    )
) {
    // Ask user to intervene
    await PermissionNext.ask({
        permission: "doom_loop",
        patterns: [value.toolName],
        metadata: { tool: value.toolName, input: value.input }
    })
}
```

**instructor-php** - Would need a dedicated hook with evaluation logic.

---

## OpenCode's Core Loop - Complete Flow

```typescript
export const loop = fn(Identifier.schema("session"), async (sessionID) => {
    const abort = start(sessionID)

    while (true) {
        // 1. Check abort
        if (abort.aborted) break

        // 2. Load messages
        let msgs = await MessageV2.filterCompacted(MessageV2.stream(sessionID))

        // 3. Find last messages
        let lastUser, lastAssistant, lastFinished
        for (let i = msgs.length - 1; i >= 0; i--) { ... }

        // 4. EXIT CONDITION - Simple finish reason check
        if (
            lastAssistant?.finish &&
            !["tool-calls", "unknown"].includes(lastAssistant.finish) &&
            lastUser.id < lastAssistant.id
        ) {
            break  // Done!
        }

        // 5. Handle pending subtasks
        if (task?.type === "subtask") { ... }

        // 6. Check context overflow
        if (await SessionCompaction.isOverflow({...})) {
            await SessionCompaction.create({...})
            continue
        }

        // 7. Create processor and call LLM
        const processor = SessionProcessor.create({...})
        const result = await processor.process({...})

        // 8. Handle processor result
        if (result === "stop") break
        if (result === "compact") continue
    }
})
```

**Processor return values:**
- `"continue"` - loop continues (default)
- `"compact"` - trigger context compaction
- `"stop"` - exit loop (error, blocked, or permission denied)

---

## Simplification Opportunities for instructor-php

### 1. Replace evaluation aggregation with finish reason check

**Current instructor-php:**
```php
protected function shouldContinue(AgentState $state): bool {
    if ($state->status() === AgentStatus::Failed) return false;

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

**Simplified (OpenCode-style):**
```php
protected function shouldContinue(AgentState $state): bool {
    // Failed state - stop
    if ($state->status() === AgentStatus::Failed) {
        return false;
    }

    // Check LLM finish reason
    $finishReason = $state->lastInferenceResponse()?->finishReason();

    // Continue only if there are tool calls to execute
    return $finishReason === FinishReason::ToolCalls;
}
```

### 2. Simplify hooks to input/output mutation

**Current:**
```php
interface Hook {
    public function appliesTo(): array;
    public function process(AgentState $state, HookType $event): AgentState;
}
```

**Simplified (OpenCode-style):**
```php
interface Hook {
    public function name(): string;
    public function mutate(mixed $input, mixed &$output): void;
}

// Usage
$output = ['messages' => $messages];
foreach ($hooks as $hook) {
    $hook->mutate($context, $output);
}
$messages = $output['messages'];
```

### 3. Remove evaluation system entirely

Files that could be removed:
- `ContinuationEvaluation.php`
- `ContinuationOutcome.php`
- `EvaluationProcessor.php`
- `ContinuationDecision.php` (reduce to simple enum or remove)

### 4. Move flow control out of hooks

Instead of hooks writing evaluations for flow control, handle limits directly in the loop:

```php
public function iterate(AgentState $state): iterable {
    while (true) {
        // Direct checks - no hooks needed
        if ($state->stepCount() >= $this->maxSteps) {
            $state = $state->withStopReason(StopReason::StepsLimitReached);
            break;
        }

        if ($state->tokenCount() >= $this->maxTokens) {
            $state = $state->withStopReason(StopReason::TokenLimitReached);
            break;
        }

        // Hooks for mutation only (not flow control)
        $state = $this->runHooks('before_step', $state);

        $state = $this->performStep($state);

        $state = $this->runHooks('after_step', $state);

        // Simple finish reason check
        if ($state->finishReason() !== FinishReason::ToolCalls) {
            break;
        }

        yield $state;
    }
}
```

### 5. Adopt processor result pattern

Instead of complex evaluation aggregation, use simple return values:

```php
enum ProcessorResult {
    case Continue;
    case Stop;
    case Compact;
    case Retry;
}

// In loop
$result = $this->processor->process($state);
match ($result) {
    ProcessorResult::Stop => break,
    ProcessorResult::Compact => { $this->compact(); continue; },
    ProcessorResult::Retry => { $this->retry(); continue; },
    ProcessorResult::Continue => null,
};
```

---

## Side-by-Side: Complete Loop Comparison

### OpenCode (~40 lines of core logic)
```typescript
while (true) {
    if (abort.aborted) break

    let msgs = await loadMessages(sessionID)
    let { lastUser, lastAssistant } = findLastMessages(msgs)

    // EXIT: finish reason check
    if (lastAssistant?.finish &&
        !["tool-calls", "unknown"].includes(lastAssistant.finish)) {
        break
    }

    // Handle subtasks
    if (task?.type === "subtask") { await executeSubtask(task) }

    // Context management
    if (await isOverflow(tokens, model)) { await compact(); continue }

    // Process LLM response
    const result = await processor.process(streamInput)
    if (result === "stop") break
    if (result === "compact") continue
}
```

### instructor-php Current (~100+ lines of core logic)
```php
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

    yield $this->onAfterExecution($state);
    return;
}
```

---

## Summary: Key Insights from OpenCode

| Pattern | OpenCode | Benefit |
|---------|----------|---------|
| **Finish reason** | Primary exit condition | Simple, LLM-driven |
| **Hooks** | Input/output mutation only | No flow control complexity |
| **Limits** | Direct checks in loop | No hook/evaluation overhead |
| **Doom loop** | Pattern detection + permission | User intervention, not auto-stop |
| **Processor result** | Simple enum (stop/continue/compact) | Clear control flow |
| **Step limits** | Inject message to LLM | Graceful, LLM handles wrap-up |

**Total complexity reduction opportunity:**
- Remove 4-value decision enum → use finish reason
- Remove evaluation aggregation → direct checks
- Simplify hooks → mutation only
- Remove ~200 lines of continuation logic
- Clearer mental model: "LLM drives, guards check"
