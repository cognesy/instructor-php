# Remaining PRM Issues Specification

## Overview
This document addresses the PRM team issues not covered by the continuation tracing or UI/Reverb proposals.

| Issue | Description | Status | Priority |
|-------|-------------|--------|----------|
| **CRITICAL** | ExecutionTimeLimit uses session start instead of execution start | **Specified here** | **P0** |
| Issue 1 | Tool args leak into message content | **Specified here** | HIGH |
| Issue 3 | Cumulative time tracking for pause/resume (within single execution) | **Specified here** | MEDIUM |
| Issue 4 | Message role convenience helpers | **Specified here** | LOW |

---

## CRITICAL BUG: ExecutionTimeLimit Uses Session Start Time

### Problem
`ExecutionTimeLimit` measures elapsed time from `stateInfo.startedAt`, which is set ONCE when the session is created and NEVER reset. This causes critical failures in multi-turn conversations:

**Scenario:**
1. Day 1, 10:00 AM: User creates conversation → `stateInfo.startedAt` = Day 1, 10:00 AM
2. Day 2, 10:00 AM: User sends new query
3. ExecutionTimeLimit calculates: `now - startedAt` = 24 hours
4. **Agent immediately times out** even though this execution just started!

### Root Cause

**In `AgentBuilder.php:305`:**
```php
new ExecutionTimeLimit($this->maxExecutionTime, static fn($state) => $state->startedAt(), null),
```

**In `AgentState.php:63`:**
```php
$this->stateInfo = $stateInfo ?? StateInfo::new();  // Called once at session creation
```

**In `StateInfo.php:16-19`:**
```php
public static function new(): self {
    $now = new DateTimeImmutable();
    return new self(Uuid::uuid4(), $now, $now);  // startedAt set here, never reset
}
```

The `stateInfo.startedAt` is correctly tracking "when was this session created" but it's being MISUSED by `ExecutionTimeLimit` to limit individual execution time.

### Correct Semantics

| Concept | When Set | Use Case |
|---------|----------|----------|
| `stateInfo.startedAt` | Session creation | Session age tracking, audit logs |
| `executionStartedAt` | Each user query | ExecutionTimeLimit - prevent runaway single queries |
| `cumulativeExecutionSeconds` | Accumulates per step | Track total processing time across pause/resume |

### Solution

#### 1. Add `executionStartedAt` to `AgentState`

```php
// In AgentState.php
final readonly class AgentState implements ...
{
    public ?DateTimeImmutable $executionStartedAt;

    public function __construct(
        // ... existing params
        ?DateTimeImmutable $executionStartedAt = null,
    ) {
        // ... existing initialization
        $this->executionStartedAt = $executionStartedAt;
    }

    public function markExecutionStarted(): self {
        return $this->with(executionStartedAt: new DateTimeImmutable());
    }

    public function executionStartedAt(): ?DateTimeImmutable {
        return $this->executionStartedAt;
    }
}
```

#### 2. Reset `executionStartedAt` at Execution Entry Point

In `Agent` or wherever execution begins for a user query:

```php
// At the start of processing a new user input
public function run(AgentState $state, Messages $userInput): AgentState {
    // Mark when THIS execution started (not the session)
    $state = $state->markExecutionStarted();

    // ... rest of execution
    return $this->finalStep($state);
}
```

Alternatively, in `StepByStep::finalStep()` or `StepByStep::iterator()`:

```php
public function finalStep(object $state): object {
    // Reset execution clock at start of run
    if (method_exists($state, 'markExecutionStarted')) {
        $state = $state->markExecutionStarted();
    }

    while ($this->hasNextStep($state)) {
        $state = $this->nextStep($state);
    }
    return $this->onNoNextStep($state);
}
```

#### 3. Update `AgentBuilder` to Use `executionStartedAt`

```php
// In AgentBuilder::buildContinuationCriteria()
new ExecutionTimeLimit(
    $this->maxExecutionTime,
    static fn($state) => $state->executionStartedAt() ?? $state->startedAt(),  // Fallback for safety
    null
),
```

#### 4. Update Serialization

```php
// In AgentState::toArray()
'executionStartedAt' => $this->executionStartedAt?->format(DATE_ATOM),

// In AgentState::fromArray()
// NOTE: executionStartedAt is intentionally NOT restored from serialization
// Each new execution should start fresh
executionStartedAt: null,  // Will be set by markExecutionStarted()
```

### Files to Modify

| File | Change |
|------|--------|
| `packages/addons/src/Agent/Core/Data/AgentState.php` | Add `executionStartedAt` field and methods |
| `packages/addons/src/Agent/Agent.php` or `StepByStep.php` | Call `markExecutionStarted()` at execution entry |
| `packages/addons/src/Agent/AgentBuilder.php` | Use `executionStartedAt()` in ExecutionTimeLimit |

### Test Cases

1. **Fresh execution**: `executionStartedAt` is set to now, time limit works correctly
2. **Multi-turn conversation**: Each new query resets `executionStartedAt`, never times out immediately
3. **Serialized/restored state**: `executionStartedAt` is null after restore, set fresh on next execution
4. **Long pause**: User queries after 1 week, execution starts fresh without timeout
5. **Session tracking unchanged**: `stateInfo.startedAt` still reflects original session creation time

### Migration Notes

- Existing serialized states will have `executionStartedAt: null`
- On first execution after upgrade, `markExecutionStarted()` will set it correctly
- No breaking changes to session tracking or audit logs

---

## Issue 1: Tool Call Arguments Leak into Message Content

### Problem
When the LLM responds with tool calls, `OpenAIResponseAdapter::makeContent()` falls back to returning tool call arguments as content. This pollutes the conversation history and UI.

### Root Cause

**Primary**: `packages/polyglot/src/Inference/Drivers/OpenAI/OpenAIResponseAdapter.php:82-90`
```php
protected function makeContent(array $data): string {
    $contentMsg = $data['choices'][0]['message']['content'] ?? '';
    $contentFnArgs = $data['choices'][0]['message']['tool_calls'][0]['function']['arguments'] ?? '';
    return match(true) {
        !empty($contentMsg) => $contentMsg,
        !empty($contentFnArgs) => $contentFnArgs,  // <-- Problematic fallback
        default => ''
    };
}
```

**Secondary**: `packages/addons/src/Agent/Drivers/ToolCalling/ToolCallingDriver.php:156-158`
```php
$outputMessages = $followUps->appendMessage(
    Message::asAssistant($response->content()),  // <-- Appends leaked args
);
```

### Solution

**Option A (Preferred)**: Fix at the source in `OpenAIResponseAdapter`
Remove the `$contentFnArgs` fallback entirely. When only tool calls are present, return empty string.

```php
protected function makeContent(array $data): string {
    return $data['choices'][0]['message']['content'] ?? '';
}
```

**Option B**: Fix in `ToolCallingDriver::buildStepFromResponse()`
Only append assistant content when it's meaningful (not empty, not tool call JSON).

```php
private function buildStepFromResponse(
    InferenceResponse $response,
    ToolExecutions $executions,
    Messages $followUps,
    Messages $context,
): AgentStep {
    $content = $response->content();
    $hasToolCalls = $response->hasToolCalls();

    // Only append content if it's natural language, not tool call JSON
    $outputMessages = ($content !== '' && !$hasToolCalls)
        ? $followUps->appendMessage(Message::asAssistant($content))
        : $followUps;

    return new AgentStep(
        inputMessages: $context,
        outputMessages: $outputMessages,
        // ... rest unchanged
    );
}
```

### Recommendation
Implement **Option A** as the primary fix (source-level), with **Option B** as defense-in-depth.

### Files to Modify
| File | Change |
|------|--------|
| `packages/polyglot/src/Inference/Drivers/OpenAI/OpenAIResponseAdapter.php` | Remove `$contentFnArgs` fallback |
| `packages/addons/src/Agent/Drivers/ToolCalling/ToolCallingDriver.php` | Add guard in `buildStepFromResponse()` |

### Test Cases
1. Tool call response with empty content → no assistant message appended
2. Tool call response with natural language content → assistant message with content appended
3. Final response with no tool calls → assistant message appended normally
4. Streaming: partial tool args should not leak into content delta

---

## Issue 3: Cumulative Time Tracking for Pause/Resume

### Problem
`ExecutionTimeLimit` uses wall-clock time (`now - startedAt`). When an agent is serialized to a database, paused, and resumed later, the elapsed time includes the pause duration, causing immediate timeouts.

### Root Cause

`packages/addons/src/StepByStep/Continuation/Criteria/ExecutionTimeLimit.php:48-58`
```php
public function decide(object $state): ContinuationDecision {
    $startedAt = ($this->startedAtResolver)($state);
    $now = $this->clock->now();
    $elapsedSeconds = $now->getTimestamp() - $startedAt->getTimestamp();
    // ...
}
```

`packages/addons/src/StepByStep/State/StateInfo.php` only tracks `startedAt` and `updatedAt`, no cumulative execution time.

### Solution

#### 1. Add cumulative time tracking to `StateInfo`

```php
final readonly class StateInfo
{
    public function __construct(
        private string $id,
        private DateTimeImmutable $startedAt,
        private DateTimeImmutable $updatedAt,
        private float $cumulativeExecutionSeconds = 0.0,
    ) {}

    public function cumulativeExecutionSeconds(): float {
        return $this->cumulativeExecutionSeconds;
    }

    public function addExecutionTime(float $seconds): self {
        return new self(
            $this->id,
            $this->startedAt,
            new DateTimeImmutable(),
            $this->cumulativeExecutionSeconds + $seconds,
        );
    }

    public function toArray(): array {
        return [
            'id' => $this->id,
            'startedAt' => $this->startedAt->format(DateTimeImmutable::ATOM),
            'updatedAt' => $this->updatedAt->format(DateTimeImmutable::ATOM),
            'cumulativeExecutionSeconds' => $this->cumulativeExecutionSeconds,
        ];
    }

    public static function fromArray(array $data): self {
        return new self(
            $data['id'] ?? Uuid::uuid4(),
            new DateTimeImmutable($data['startedAt'] ?? 'now'),
            new DateTimeImmutable($data['updatedAt'] ?? 'now'),
            $data['cumulativeExecutionSeconds'] ?? 0.0,
        );
    }
}
```

#### 2. Create `CumulativeExecutionTimeLimit` criterion

```php
final readonly class CumulativeExecutionTimeLimit implements CanDecideToContinue, CanExplainContinuation
{
    public function __construct(
        private int $maxSeconds,
        private Closure $cumulativeTimeResolver,
    ) {
        if ($maxSeconds <= 0) {
            throw new \InvalidArgumentException('Max seconds must be greater than zero.');
        }
    }

    public function decide(object $state): ContinuationDecision {
        return $this->explain($state)->decision;
    }

    public function explain(object $state): ContinuationEvaluation {
        $cumulativeSeconds = ($this->cumulativeTimeResolver)($state);
        $exceeded = $cumulativeSeconds >= $this->maxSeconds;

        return new ContinuationEvaluation(
            criterionClass: self::class,
            decision: $exceeded
                ? ContinuationDecision::ForbidContinuation
                : ContinuationDecision::AllowContinuation,
            reason: $exceeded
                ? sprintf('Cumulative execution time %.1fs exceeded limit %ds', $cumulativeSeconds, $this->maxSeconds)
                : sprintf('Cumulative execution time %.1fs under limit %ds', $cumulativeSeconds, $this->maxSeconds),
            context: [
                'cumulativeSeconds' => $cumulativeSeconds,
                'maxSeconds' => $this->maxSeconds,
            ],
        );
    }
}
```

#### 3. Update Agent execution loop to track step duration

In `Agent::iterate()` or step processor, record step duration:

```php
$stepStartTime = microtime(true);
// ... execute step ...
$stepDuration = microtime(true) - $stepStartTime;

$state = $state->with(
    stateInfo: $state->stateInfo()->addExecutionTime($stepDuration)
);
```

#### 4. AgentBuilder integration

```php
// Add option to use cumulative time limit
public function withCumulativeTimeout(int $seconds): self {
    $this->useCumulativeTimeLimit = true;
    $this->maxExecutionTime = $seconds;
    return $this;
}

// In buildContinuationCriteria(), conditionally use CumulativeExecutionTimeLimit
$timeLimitCriterion = $this->useCumulativeTimeLimit
    ? new CumulativeExecutionTimeLimit(
        $this->maxExecutionTime,
        static fn($state) => $state->stateInfo()->cumulativeExecutionSeconds()
    )
    : new ExecutionTimeLimit(
        $this->maxExecutionTime,
        static fn($state) => $state->startedAt()
    );
```

### Files to Modify
| File | Change |
|------|--------|
| `packages/addons/src/StepByStep/State/StateInfo.php` | Add `cumulativeExecutionSeconds` field |
| `packages/addons/src/StepByStep/Continuation/Criteria/CumulativeExecutionTimeLimit.php` | New class |
| `packages/addons/src/Agent/Agent.php` | Track step duration |
| `packages/addons/src/Agent/AgentBuilder.php` | Add `withCumulativeTimeout()` |

### Test Cases
1. Fresh agent: cumulative time tracks actual execution
2. Serialized/deserialized agent: cumulative time preserved, not reset
3. Pause for 1 hour, resume: only actual execution time counts
4. Step duration accurately recorded in state

---

## Issue 4: Message Role Convenience Helpers

### Problem
The `Message` class requires importing `MessageRole` enum and comparing against it. The PRM team requested convenience methods like `isAssistant()`, `isTool()`, etc.

### Current State

`MessageRole` enum already has `is()`, `isNot()`, `oneOf()`, and `isSystem()` methods.

`Message` class has `role(): MessageRole` but no convenience helpers.

### Solution

Add convenience methods to `Message` class:

```php
// In packages/messages/src/Message.php

public function isUser(): bool {
    return $this->role() === MessageRole::User;
}

public function isAssistant(): bool {
    return $this->role() === MessageRole::Assistant;
}

public function isTool(): bool {
    return $this->role() === MessageRole::Tool;
}

public function isSystem(): bool {
    return $this->role()->isSystem(); // Covers System and Developer
}

public function isDeveloper(): bool {
    return $this->role() === MessageRole::Developer;
}

public function hasRole(MessageRole ...$roles): bool {
    return $this->role()->oneOf(...$roles);
}
```

### Usage

```php
// Before
use Cognesy\Messages\Enums\MessageRole;
if ($message->role() === MessageRole::Assistant) { ... }

// After
if ($message->isAssistant()) { ... }

// Multiple role check
if ($message->hasRole(MessageRole::User, MessageRole::Assistant)) { ... }
```

### Files to Modify
| File | Change |
|------|--------|
| `packages/messages/src/Message.php` | Add convenience methods |

### Test Cases
1. `isUser()` returns true for user messages
2. `isAssistant()` returns true for assistant messages
3. `isTool()` returns true for tool messages
4. `isSystem()` returns true for both system and developer messages
5. `hasRole()` works with multiple role arguments

---

## Summary

| Issue | Priority | Effort | Dependencies |
|-------|----------|--------|--------------|
| **CRITICAL: ExecutionTimeLimit bug** | **P0** | Low | None |
| Issue 1: Tool args leak | **HIGH** | Low | None |
| Issue 3: Cumulative time | **MEDIUM** | Medium | StateInfo changes |
| Issue 4: Message helpers | **LOW** | Low | None |

### Recommended Order
1. **CRITICAL: ExecutionTimeLimit bug** (blocks all multi-turn usage, must fix immediately)
2. Issue 4 (quick win, no dependencies)
3. Issue 1 (high impact, low effort)
4. Issue 3 (requires more changes, affects serialization)

### Clarification: ExecutionTimeLimit vs CumulativeExecutionTime

These address **different** use cases:

| Concept | Purpose | Scope |
|---------|---------|-------|
| `ExecutionTimeLimit` | Prevent single query from running too long | Per user query |
| `CumulativeExecutionTimeLimit` | Track total processing across pause/resume | Within single execution |

**Example:**
- User asks question → execution starts
- Agent runs 5 seconds → paused (serialized to DB)
- 1 hour later → resumed (deserialized)
- Agent runs 3 more seconds → finishes

With `ExecutionTimeLimit`:
- Measures from `executionStartedAt` (set at "User asks question")
- Would see ~1 hour elapsed → timeout! (WRONG if we want cumulative)

With `CumulativeExecutionTimeLimit`:
- Accumulates: 5s + 3s = 8s total processing time
- Correct for tracking actual computation time across pause/resume
