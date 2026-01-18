# PRM Team Feedback Assessment & Next Steps

**Date**: 2026-01-16
**Status**: Internal Assessment
**Responding to**: `2026-01-16-agents-feedback.md` (Partnerspot PRM Team)

---

## Executive Summary

The Partnerspot PRM team has provided valuable feedback based on their real-world implementation of a Partner Management Assistant using InstructorPHP's Agent framework. Their hands-on experience surfacing practical issues is appreciated.

### Findings Summary

| Issue | Validity | Priority | Effort |
|-------|----------|----------|--------|
| 1. Tool args in message content | **Confirmed Bug** | High | Low |
| 2. ContinuationCriteria observability | **Valid** | High | Medium |
| 3. Time-based serialization | **Valid** (for pause/resume) | Medium | Medium |
| 4. MessageRole convenience methods | **Valid Usability** | Low | Low |
| 5. No built-in execution trace | **Partially Valid** | High | Medium |
| 6. Agent stops after one step | **Likely Misunderstanding** | Medium | - |

**Key Insight**: Issue #2 (ContinuationCriteria observability) is the highest-value improvement. Adding `decideWithTrace()` would have helped the team debug Issue #6 themselves, as the actual stopping reason would have been visible.

---

## Issue-by-Issue Analysis

### Issue 1: Tool Call Arguments Leak into Message Content

**Status**: ✅ CONFIRMED BUG

#### Root Cause Analysis

The issue originates in two locations:

**Primary**: `OpenAIResponseAdapter::makeContent()` at `packages/polyglot/src/Inference/Drivers/OpenAI/OpenAIResponseAdapter.php:82-90`:

```php
protected function makeContent(array $data): string {
    $contentMsg = $data['choices'][0]['message']['content'] ?? '';
    $contentFnArgs = $data['choices'][0]['message']['tool_calls'][0]['function']['arguments'] ?? '';
    return match(true) {
        !empty($contentMsg) => $contentMsg,
        !empty($contentFnArgs) => $contentFnArgs,  // <-- Fallback to tool args
        default => ''
    };
}
```

When the LLM responds with only tool calls (no natural language), `content` is empty, so the adapter falls back to returning tool call arguments as content.

**Secondary**: `ToolCallingDriver::buildStepFromResponse()` at `packages/addons/src/Agent/Drivers/ToolCalling/ToolCallingDriver.php:156-158`:

```php
$outputMessages = $followUps->appendMessage(
    Message::asAssistant($response->content()),  // <-- Appends the leaked args
);
```

#### Impact

- UI displays raw JSON like `{"query":"dbplus","types":["program","program_lead"]}`
- Conversation history polluted with tool arguments masquerading as assistant messages
- Teams forced to implement heuristic filtering workarounds

#### Recommended Fix

**Option A** (Preferred): Fix at the source in `OpenAIResponseAdapter::makeContent()`:
- Remove the `$contentFnArgs` fallback entirely
- Return empty string when only tool calls are present

**Option B**: Fix in `ToolCallingDriver::buildStepFromResponse()`:
- Check if `$response->hasToolCalls()` before appending content
- Only append non-empty content that isn't tool call JSON

```php
// Option B fix
$content = $response->content();
$hasToolCalls = $response->hasToolCalls();
if ($content !== '' && !$hasToolCalls) {
    $outputMessages = $followUps->appendMessage(
        Message::asAssistant($content),
    );
} else {
    $outputMessages = $followUps;
}
```

**Priority**: HIGH | **Effort**: LOW

---

### Issue 2: ContinuationCriteria Lacks Observability

**Status**: ✅ VALID

This is the **highest-value improvement** identified.

#### Root Cause Analysis

`ContinuationCriteria::decide()` at `packages/addons/src/StepByStep/Continuation/ContinuationCriteria.php:100-116`:

```php
public function decide(object $state): ContinuationDecision {
    if ($this->criteria === []) {
        return ContinuationDecision::AllowStop;
    }

    $decisions = array_map(
        fn(CanDecideToContinue $criterion) => $criterion->decide($state),
        $this->criteria
    );

    // Individual decisions are discarded here!
    $shouldContinue = ContinuationDecision::canContinueWith(...$decisions);

    return $shouldContinue
        ? ContinuationDecision::RequestContinuation
        : ContinuationDecision::AllowStop;
}
```

The method collects individual criterion decisions but immediately discards them, returning only the final aggregate result. When debugging why an agent stopped, developers have no visibility into which criterion made the decision.

#### Proposed Solution

Add a `decideWithTrace()` method that returns a rich result object:

```php
final readonly class ContinuationResult
{
    public function __construct(
        public ContinuationDecision $finalDecision,
        /** @var array<class-string, ContinuationDecision> */
        public array $criteriaDecisions,
        public ?string $determiningCriterion,
        public ?string $reason,
        public array $context = [],
    ) {}

    public function shouldContinue(): bool {
        return $this->finalDecision->shouldContinue();
    }

    public function getDecisionFor(string $criterionClass): ?ContinuationDecision {
        return $this->criteriaDecisions[$criterionClass] ?? null;
    }
}
```

Implementation in `ContinuationCriteria`:

```php
public function decideWithTrace(object $state): ContinuationResult {
    if ($this->criteria === []) {
        return new ContinuationResult(
            finalDecision: ContinuationDecision::AllowStop,
            criteriaDecisions: [],
            determiningCriterion: null,
            reason: 'No criteria configured',
        );
    }

    $decisions = [];
    $determiningCriterion = null;
    $reason = null;

    foreach ($this->criteria as $criterion) {
        $decision = $criterion->decide($state);
        $className = $criterion::class;
        $decisions[$className] = $decision;

        // Track which criterion determined the outcome
        if ($decision === ContinuationDecision::ForbidContinuation && $determiningCriterion === null) {
            $determiningCriterion = $className;
            $reason = 'Guard forbade continuation';
        }
    }

    if ($determiningCriterion === null) {
        // Find if any criterion requested continuation
        foreach ($decisions as $className => $decision) {
            if ($decision === ContinuationDecision::RequestContinuation) {
                $determiningCriterion = $className;
                $reason = 'Work driver requested continuation';
                break;
            }
        }
    }

    $finalDecision = ContinuationDecision::canContinueWith(...array_values($decisions))
        ? ContinuationDecision::RequestContinuation
        : ContinuationDecision::AllowStop;

    return new ContinuationResult(
        finalDecision: $finalDecision,
        criteriaDecisions: $decisions,
        determiningCriterion: $determiningCriterion ?? 'aggregate',
        reason: $reason ?? 'No continuation requested',
    );
}
```

**Priority**: HIGH | **Effort**: MEDIUM

---

### Issue 3: AgentState Serialization Issues with Time-Based Fields

**Status**: ✅ VALID (for pause/resume workflows)

#### Root Cause Analysis

`ExecutionTimeLimit` at `packages/addons/src/StepByStep/Continuation/Criteria/ExecutionTimeLimit.php:48-58`:

```php
public function decide(object $state): ContinuationDecision {
    $startedAt = ($this->startedAtResolver)($state);
    $now = $this->clock->now();
    $elapsedSeconds = $now->getTimestamp() - $startedAt->getTimestamp();

    return $elapsedSeconds < $this->maxSeconds
        ? ContinuationDecision::AllowContinuation
        : ContinuationDecision::ForbidContinuation;
}
```

The criterion uses wall-clock time (`now - startedAt`). If an agent is serialized to a database, paused overnight, and resumed the next day, the elapsed time would show 12+ hours even if actual execution was only 30 seconds.

#### Current State Tracking

`AgentState` at `packages/addons/src/Agent/Core/Data/AgentState.php:39`:
```php
public ?DateTimeImmutable $currentStepStartedAt;
```

There's `currentStepStartedAt` but no cumulative execution time field.

#### Proposed Solution

Add cumulative execution tracking to `AgentState`:

```php
// In AgentState
public readonly float $cumulativeExecutionSeconds;

public function recordStepDuration(float $seconds): self {
    return $this->with(
        cumulativeExecutionSeconds: $this->cumulativeExecutionSeconds + $seconds
    );
}
```

Create a new `CumulativeExecutionTimeLimit` criterion:

```php
final readonly class CumulativeExecutionTimeLimit implements CanDecideToContinue
{
    public function __construct(
        private int $maxSeconds,
        private Closure $cumulativeTimeResolver,
    ) {}

    public function decide(object $state): ContinuationDecision {
        $cumulativeSeconds = ($this->cumulativeTimeResolver)($state);
        return $cumulativeSeconds < $this->maxSeconds
            ? ContinuationDecision::AllowContinuation
            : ContinuationDecision::ForbidContinuation;
    }
}
```

**Priority**: MEDIUM | **Effort**: MEDIUM

---

### Issue 4: MessageRole Convenience Methods

**Status**: ✅ VALID USABILITY IMPROVEMENT

#### Root Cause Analysis

`Message` class at `packages/messages/src/Message.php` provides `role()` returning `MessageRole` enum, but no convenience methods for common role checks.

Current usage requires:
```php
use Cognesy\Messages\Enums\MessageRole;

if ($message->role() === MessageRole::Assistant) { ... }
```

#### Proposed Solution

Add helper methods to `Message`:

```php
// In Message class
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
    return $this->role() === MessageRole::System;
}

public function isDeveloper(): bool {
    return $this->role() === MessageRole::Developer;
}
```

This aligns with common Laravel/PHP conventions (e.g., `$user->isAdmin()`).

**Priority**: LOW | **Effort**: LOW

---

### Issue 5: No Built-in Execution Logging/Tracing

**Status**: ⚠️ PARTIALLY VALID

#### What Already Exists

The framework **does have** an event system with relevant events:

| Event | Location |
|-------|----------|
| `AgentStepStarted` | `packages/addons/src/Agent/Events/AgentStepStarted.php` |
| `AgentStepCompleted` | `packages/addons/src/Agent/Events/AgentStepCompleted.php` |
| `AgentFinished` | `packages/addons/src/Agent/Events/AgentFinished.php` |
| `AgentFailed` | `packages/addons/src/Agent/Events/AgentFailed.php` |
| `TokenUsageReported` | `packages/addons/src/Agent/Events/TokenUsageReported.php` |
| `ToolCallStarted` | `packages/addons/src/Agent/Events/ToolCallStarted.php` |
| `ToolCallCompleted` | `packages/addons/src/Agent/Events/ToolCallCompleted.php` |

`AgentStepCompleted` already captures timing, usage, errors, and tool call status:

```php
// AgentStepCompleted.php:18-42
public readonly DateTimeImmutable $completedAt;
public readonly float $durationMs;
public readonly int $stepNumber;
public readonly bool $hasToolCalls;
public readonly int $errorCount;
public readonly Usage $usage;
```

#### What's Missing

1. **No aggregate trace object** - Events fire individually but there's no built-in collector
2. **No continuation decision event** - When criteria decide to stop, no event is emitted explaining why
3. **No convenient access pattern** - No `$state->executionTrace()` or similar

#### Proposed Additions

**1. New Event**: `ContinuationDecisionMade`
```php
final class ContinuationDecisionMade extends AgentEvent
{
    public function __construct(
        public readonly string $agentId,
        public readonly int $stepNumber,
        public readonly ContinuationResult $result,
    ) {}
}
```

**2. Aggregate Trace** (optional convenience layer):
```php
final class ExecutionTrace
{
    /** @var list<StepTrace> */
    private array $steps = [];

    public function recordStep(AgentStepCompleted $event, ContinuationResult $decision): void {
        $this->steps[] = new StepTrace(
            stepNumber: $event->stepNumber,
            durationMs: $event->durationMs,
            hasToolCalls: $event->hasToolCalls,
            errorCount: $event->errorCount,
            usage: $event->usage,
            continuationDecision: $decision,
        );
    }
}
```

**Priority**: HIGH | **Effort**: MEDIUM

---

### Issue 6: Agent Stops After One Step Despite Tool Calls

**Status**: ❓ LIKELY MISUNDERSTANDING

#### Analysis

The PRM team reports that `ToolCallPresenceCheck` correctly returned `RequestContinuation`, but the agent still stopped. Given the existing continuation logic, this seems unlikely to be a framework bug.

The resolution logic in `ContinuationCriteria` is:
1. If **any** criterion returns `ForbidContinuation` → STOP
2. Else if **any** criterion returns `RequestContinuation` → CONTINUE
3. Else → STOP

#### Probable Root Cause

A **guard criterion** (e.g., `StepsLimit`, `ExecutionTimeLimit`, `TokenUsageLimit`) likely returned `ForbidContinuation`, overriding the `RequestContinuation` from `ToolCallPresenceCheck`.

Due to **Issue #2** (lack of observability), the team couldn't see which guard blocked continuation.

#### Resolution Path

1. Implement Issue #2's `decideWithTrace()` - this would reveal the actual blocking criterion
2. Add the `ContinuationDecisionMade` event from Issue #5
3. Provide documentation on criterion priority resolution

**Priority**: MEDIUM (resolves naturally with Issue #2 fix)

---

## What the PRM Team May Have Missed

### Existing Capabilities Worth Leveraging

#### 1. Event System for Observability

The team mentioned adding custom `wiretap()` handlers. The built-in event system can serve similar purposes:

```php
$events->listen(AgentStepCompleted::class, function(AgentStepCompleted $event) {
    $this->logger->info($event->__toString());
});
```

#### 2. DeterministicAgentDriver for Testing

For reproducible testing without LLM calls:
```php
use Cognesy\Addons\Agent\Drivers\Testing\DeterministicAgentDriver;

$driver = new DeterministicAgentDriver([
    ['content' => 'First response', 'tool_calls' => [...]],
    ['content' => 'Second response'],
]);
```

#### 3. Metadata System for Custom State

`AgentState` supports arbitrary metadata:
```php
$state = $state->withMetadataKeyValue('custom_field', $value);
$value = $state->metadata()->get('custom_field');
```

#### 4. AgentCapability Pattern

For extending agent behavior modularly, the framework supports capabilities that can be composed.

---

## Recommended Implementation Order

| Order | Issue | Rationale |
|-------|-------|-----------|
| 1 | **Issue 2**: `decideWithTrace()` | Unblocks debugging; resolves Issue 6 |
| 2 | **Issue 1**: Fix content leakage | High impact, low effort |
| 3 | **Issue 5**: Add `ContinuationDecisionMade` event | Complements Issue 2 |
| 4 | **Issue 4**: Message role helpers | Quick win for API ergonomics |
| 5 | **Issue 3**: Cumulative time tracking | Only needed for pause/resume workflows |

---

## Proposed ContinuationResult Design

```php
<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\Continuation;

/**
 * Rich result object for continuation decisions with full trace.
 */
final readonly class ContinuationResult
{
    /**
     * @param ContinuationDecision $finalDecision The aggregate decision
     * @param array<class-string, ContinuationDecision> $criteriaDecisions Individual decisions by class
     * @param string|null $determiningCriterion Class name of the criterion that determined outcome
     * @param string|null $reason Human-readable explanation
     * @param array<string, mixed> $context Additional debugging context
     */
    public function __construct(
        public ContinuationDecision $finalDecision,
        public array $criteriaDecisions,
        public ?string $determiningCriterion,
        public ?string $reason,
        public array $context = [],
    ) {}

    public function shouldContinue(): bool {
        return $this->finalDecision->shouldContinue();
    }

    public function shouldStop(): bool {
        return !$this->shouldContinue();
    }

    public function getDecisionFor(string $criterionClass): ?ContinuationDecision {
        return $this->criteriaDecisions[$criterionClass] ?? null;
    }

    public function wasForbiddenBy(): ?string {
        foreach ($this->criteriaDecisions as $class => $decision) {
            if ($decision === ContinuationDecision::ForbidContinuation) {
                return $class;
            }
        }
        return null;
    }

    public function toArray(): array {
        return [
            'finalDecision' => $this->finalDecision->value,
            'criteriaDecisions' => array_map(
                fn(ContinuationDecision $d) => $d->value,
                $this->criteriaDecisions
            ),
            'determiningCriterion' => $this->determiningCriterion,
            'reason' => $this->reason,
            'context' => $this->context,
        ];
    }
}
```

---

## Documentation Improvements

Based on this feedback, consider adding:

1. **Troubleshooting Guide**: Common continuation issues and how to debug them
2. **Event Subscription Patterns**: How to use the event system for observability
3. **Pause/Resume Workflow**: Examples for serializing and resuming agent state
4. **Criterion Priority Documentation**: Clear explanation of `ForbidContinuation > RequestContinuation > AllowStop` resolution

---

## Key Files to Modify

| File | Change |
|------|--------|
| `packages/addons/src/StepByStep/Continuation/ContinuationCriteria.php` | Add `decideWithTrace()` |
| `packages/addons/src/StepByStep/Continuation/ContinuationResult.php` | **New class** |
| `packages/addons/src/Agent/Drivers/ToolCalling/ToolCallingDriver.php` | Fix `buildStepFromResponse` |
| `packages/polyglot/src/Inference/Drivers/OpenAI/OpenAIResponseAdapter.php` | Remove content fallback |
| `packages/messages/src/Message.php` | Add `isAssistant()`, `isTool()`, `isUser()` |
| `packages/addons/src/Agent/Events/ContinuationDecisionMade.php` | **New event** |
| `packages/addons/src/Agent/Core/Data/AgentState.php` | Add cumulative execution time |

---

## Acknowledgment

The PRM team's detailed feedback demonstrates serious engagement with the framework. Their workarounds (heuristic JSON filtering, manual criterion instantiation for debugging) validate the genuine need for these improvements.

We appreciate their willingness to contribute patches for low-effort fixes.
