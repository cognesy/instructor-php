# Response: InstructorPHP Agent Framework Improvements

**Date**: 2026-01-16
**To**: Partnerspot PRM Team
**From**: InstructorPHP/Cognesy Team
**Re**: Change Request Feedback (2026-01-16-agents-feedback.md)

---

## Executive Summary

Thank you for the detailed, actionable feedback with specific code references. Your real-world implementation of the Partner Management Assistant helped us identify genuine pain points in the framework.

**All 6 issues have been addressed.** In most cases, we delivered more than originally requested. The implementation includes several features you didn't explicitly request but which directly support your use case:

- **Error Policy system** with configurable retry behavior
- **Slim serialization** for database/Reverb payloads
- **Reverb event adapter** matching your event structure
- **Per-execution timing** (not just cumulative) for multi-turn conversations

---

## Issue Resolution Summary

| # | Your Issue | Your Request | What We Delivered |
|---|------------|--------------|-------------------|
| 1 | Tool args leak into content | Don't append content when tool calls present | Fixed at source (`OpenAIResponseAdapter`) + defense-in-depth (`ToolCallingDriver`) |
| 2 | ContinuationCriteria lacks observability | Rich decision objects with reasons | `ContinuationOutcome` with full trace, `ContinuationEvaluated` event, `StopReason` enum |
| 3 | Time-based serialization issues | Cumulative execution time | Per-execution timing (`executionStartedAt`) + cumulative tracking + `withCumulativeTimeout()` |
| 4 | MessageRole not exported | `isAssistant()`, `isTool()`, `isUser()` helpers | All requested + `isSystem()`, `isDeveloper()`, `hasRole(MessageRole...)` |
| 5 | No built-in execution tracing | Structured trace + PSR-3 logger | `ContinuationEvaluated` event, `SlimAgentStateSerializer`, `ReverbAgentEventAdapter` |
| 6 | Agent stops after one step | Better continuation visibility | Root cause was #2 - now fully debuggable via `ContinuationOutcome` |

---

## Issue 1: Tool Call Arguments Leak into Message Content

### What You Reported

```php
// Your workaround in AgentExecutionService.php:398-431
private function looksLikeToolCallArgs(string $content): bool
{
    $trimmed = trim($content);
    if (!str_starts_with($trimmed, '{')) return false;
    // ... heuristic checks ...
}
```

### What We Fixed

**Primary fix** in `OpenAIResponseAdapter::makeContent()`:
```php
// Before (problematic fallback):
return match(true) {
    !empty($contentMsg) => $contentMsg,
    !empty($contentFnArgs) => $contentFnArgs,  // <-- Removed
    default => ''
};

// After:
return $data['choices'][0]['message']['content'] ?? '';
```

**Defense-in-depth** in `ToolCallingDriver::appendResponseContent()`:
```php
private function appendResponseContent(Messages $messages, InferenceResponse $response): Messages {
    $content = $response->content();
    if ($content === '') {
        return $messages;
    }
    if ($this->isToolArgsLeak($content, $response->toolCalls())) {
        return $messages;
    }
    return $messages->appendMessage(Message::asAssistant($content));
}
```

### Action for Partnerspot

**Remove `looksLikeToolCallArgs()` from `AgentExecutionService.php`** - it's no longer needed. The framework now prevents tool arguments from leaking into message content at the source.

---

## Issue 2: ContinuationCriteria Lacks Observability

### What You Reported

> Debugging why agent stopped after 1 step required manually instantiating each criterion and logging results.

### What We Delivered

**New Types** in `packages/addons/src/StepByStep/Continuation/`:

```php
// StopReason.php - Semantic stop reasons
enum StopReason: string
{
    case Completed = 'completed';
    case StepsLimitReached = 'steps_limit';
    case TokenLimitReached = 'token_limit';
    case TimeLimitReached = 'time_limit';
    case RetryLimitReached = 'retry_limit';
    case ErrorForbade = 'error';
    case FinishReasonReceived = 'finish_reason';
    case GuardForbade = 'guard';
    case UserRequested = 'user_requested';
}

// ContinuationEvaluation.php - Individual criterion result
final readonly class ContinuationEvaluation
{
    public function __construct(
        public string $criterionClass,
        public ContinuationDecision $decision,
        public string $reason,
        public array $context = [],
    ) {}
}

// ContinuationOutcome.php - Full decision trace
final readonly class ContinuationOutcome
{
    public function __construct(
        public ContinuationDecision $decision,
        public bool $shouldContinue,
        public string $resolvedBy,
        public StopReason $stopReason,
        public array $evaluations,  // All criteria results
    ) {}

    public function getEvaluationFor(string $criterionClass): ?ContinuationEvaluation;
    public function getForbiddingCriterion(): ?string;
    public function toArray(): array;
}
```

**New Event** - `ContinuationEvaluated`:
```php
// Emitted after each step's continuation check
final class ContinuationEvaluated extends AgentEvent
{
    public function __construct(
        public readonly string $agentId,
        public readonly ?string $parentAgentId,
        public readonly int $stepNumber,
        public readonly ContinuationOutcome $outcome,
    ) {}
}
```

**New API** - `ContinuationCriteria::evaluate()`:
```php
// Returns full trace instead of just bool
public function evaluate(object $state): ContinuationOutcome;

// Existing methods now delegate to evaluate():
public function canContinue(object $state): bool {
    return $this->evaluate($state)->shouldContinue();
}
```

### Action for Partnerspot

**Add a listener for debugging**:
```php
use Cognesy\Addons\Agent\Events\ContinuationEvaluated;

$agent->onEvent(ContinuationEvaluated::class, function(ContinuationEvaluated $event) {
    if (!$event->outcome->shouldContinue) {
        Log::info("Agent stopped", [
            'step' => $event->stepNumber,
            'reason' => $event->outcome->stopReason->value,
            'resolved_by' => $event->outcome->resolvedBy,
            'evaluations' => $event->outcome->toArray()['evaluations'],
        ]);
    }
});
```

---

## Issue 3: AgentState Serialization Issues with Time-Based Fields

### What You Reported

> If paused overnight, elapsed appears as 12+ hours → immediate stop

### What We Delivered

We implemented **two complementary solutions**:

**1. Per-execution timing** (for multi-turn conversations):

```php
// AgentState.php
public ?DateTimeImmutable $executionStartedAt;

public function markExecutionStarted(): self {
    return $this->with(executionStartedAt: new DateTimeImmutable());
}

// Called automatically at start of finalStep() and iterator()
// NOT restored from serialization - intentionally resets each execution
```

`ExecutionTimeLimit` now uses `executionStartedAt` with fallback:
```php
new ExecutionTimeLimit(
    $this->maxExecutionTime,
    static fn($state) => $state->executionStartedAt() ?? $state->startedAt(),
    null
),
```

**2. Cumulative execution time** (for pause/resume with total time limit):

```php
// StateInfo.php
public function cumulativeExecutionSeconds(): float;
public function addExecutionTime(float $seconds): self;

// New criterion
use Cognesy\Addons\StepByStep\Continuation\Criteria\CumulativeExecutionTimeLimit;

// Builder method
$agent = AgentBuilder::base()
    ->withCumulativeTimeout(300)  // 5 minutes total processing
    ->build();
```

### Action for Partnerspot

**For multi-turn conversations** (your primary use case): No changes needed. The default `ExecutionTimeLimit` now resets per execution.

**For pause/resume with total time tracking**:
```php
$agent = AgentBuilder::base()
    ->withCumulativeTimeout(300)
    ->build();
```

---

## Issue 4: MessageRole Convenience Methods

### What You Reported

```php
// Your code in AgentExecutionService.php:348-352
$messageRole = match ($cognesyRole) {
    CognesyMessageRole::Assistant => MessageRole::Assistant,
    CognesyMessageRole::Tool => MessageRole::Tool,
    default => null,
};
```

### What We Delivered

```php
// Message.php
public function isUser(): bool;
public function isAssistant(): bool;
public function isTool(): bool;
public function isSystem(): bool;       // Covers System AND Developer roles
public function isDeveloper(): bool;
public function hasRole(MessageRole ...$roles): bool;
```

### Action for Partnerspot

**Simplify `storeStepMessages()`**:
```php
// Before:
$cognesyRole = $message->role();
$messageRole = match ($cognesyRole) {
    CognesyMessageRole::Assistant => MessageRole::Assistant,
    CognesyMessageRole::Tool => MessageRole::Tool,
    default => null,
};
if ($messageRole === null) continue;

// After:
if (!$message->isAssistant() && !$message->isTool()) {
    continue;
}
$messageRole = $message->isAssistant()
    ? MessageRole::Assistant
    : MessageRole::Tool;
```

---

## Issue 5: No Built-in Execution Logging/Tracing

### What You Reported

> We added custom `wiretap()` handlers, but this is ad-hoc.

### What We Delivered

**1. ContinuationEvaluated event** (see Issue #2) - provides continuation decision visibility

**2. SlimAgentStateSerializer** for database/Reverb payloads:

```php
use Cognesy\Addons\Agent\Serialization\SlimAgentStateSerializer;
use Cognesy\Addons\Agent\Serialization\SlimSerializationConfig;

// Presets
$config = SlimSerializationConfig::minimal();   // 10 messages, 500 chars, no tool args
$config = SlimSerializationConfig::standard();  // 50 messages, 1000 chars, with tool args
$config = SlimSerializationConfig::full();      // No limits

$serializer = new SlimAgentStateSerializer($config);
$payload = $serializer->serialize($state);
```

**3. ReverbAgentEventAdapter** matching your event envelope format:

```php
use Cognesy\Addons\Agent\Broadcasting\ReverbAgentEventAdapter;

// Emits envelopes like:
{
    "type": "agent.step.completed",
    "session_id": "...",
    "execution_id": "...",
    "timestamp": "2026-01-16T12:00:00.000Z",
    "payload": {
        "step": 3,
        "has_tool_calls": true,
        "usage": { "input": 150, "output": 50, "total": 200 }
    }
}
```

**4. Enhanced existing events**:
- `ToolCallStarted`: Uses `tool` property (not `name`)
- `ToolCallCompleted`: Includes `tool`, `success`, `error`, `duration_ms`
- `AgentStepCompleted`: Includes `durationMs`, `hasToolCalls`, `errorCount`, `usage`

### Action for Partnerspot

**Consider using `ReverbAgentEventAdapter`** in `AssistantEventBroadcaster.php` for standardized envelopes, or use it as reference for your existing implementation.

**Use slim serialization** for `state_snapshot` storage:
```php
private function serializeStateForStorage(AgentState $state): array {
    $serializer = new SlimAgentStateSerializer(
        SlimSerializationConfig::standard()
    );
    return $serializer->serialize($state);
}
```

---

## Issue 6: Agent Stops After One Step Despite Tool Calls

### What You Reported

> `ToolCallPresenceCheck` correctly returned `RequestContinuation`, but agent still stopped.

### Root Cause

This was a **symptom of Issue #2**. A guard criterion (likely `ExecutionTimeLimit`, `ErrorPresenceCheck`, or a custom one) returned `ForbidContinuation`, which takes priority over `RequestContinuation`. Without observability, you couldn't see which guard blocked continuation.

### Resolution

With the new `ContinuationOutcome` and `ContinuationEvaluated` event, you can now see exactly why the agent stopped:

```php
$agent->onEvent(ContinuationEvaluated::class, function($e) {
    if (!$e->outcome->shouldContinue) {
        // This will show you the exact criterion that stopped the agent
        dump([
            'stopped_by' => $e->outcome->resolvedBy,
            'stop_reason' => $e->outcome->stopReason->value,
            'forbidding_criterion' => $e->outcome->getForbiddingCriterion(),
        ]);
    }
});
```

---

## New Error Policy System

Beyond your requests, we identified that default error handling was too aggressive. We've added a configurable error policy system:

```php
use Cognesy\Addons\StepByStep\Continuation\ErrorPolicy;

// Presets
ErrorPolicy::stopOnAnyError();        // Default - matches previous behavior
ErrorPolicy::retryToolErrors(3);      // Retry tool failures up to 3 times
ErrorPolicy::ignoreToolErrors();      // Continue despite tool failures
ErrorPolicy::retryAll(5);             // Retry all errors up to 5 times

// Builder integration
$agent = AgentBuilder::base()
    ->withErrorPolicy(ErrorPolicy::retryToolErrors(3))
    ->build();
```

This replaces the separate `ErrorPresenceCheck` and `RetryLimit` criteria with a unified `ErrorPolicyCriterion`.

---

## Summary of Recommended Code Changes

### 1. Remove workarounds (no longer needed)

```diff
// AgentExecutionService.php
- private function looksLikeToolCallArgs(string $content): bool { ... }

// In storeStepMessages():
- if ($messageRole === MessageRole::Assistant && $this->looksLikeToolCallArgs($content)) {
-     continue;
- }
```

### 2. Add continuation debugging

```php
// In attachAssistantBroadcaster() or similar:
$agent->onEvent(ContinuationEvaluated::class, function($e) use ($sessionId) {
    if (!$e->outcome->shouldContinue) {
        AssistantLogger::log($sessionId, 'continuation_stopped', [
            'step' => $e->stepNumber,
            'reason' => $e->outcome->stopReason->value,
            'by' => $e->outcome->resolvedBy,
        ]);
    }
});
```

### 3. Configure error policy (optional)

```php
// In your agent blueprint/factory:
$agent = AgentBuilder::base()
    ->withErrorPolicy(ErrorPolicy::retryToolErrors(3))
    ->build();
```

### 4. Simplify role checking

```php
// Before:
use Cognesy\Messages\Enums\MessageRole as CognesyMessageRole;
$messageRole = match ($cognesyRole) { ... };

// After:
if ($message->isAssistant()) { ... }
if ($message->isTool()) { ... }
```

---

## Documentation

Updated documentation is available in `packages/addons/AGENT.md`:

- **Troubleshooting** section with common issues
- **Migration Guide** (2026-01-16) for ErrorPolicy transition
- **Event Reference** with all event payloads
- **Testing Utilities** including `DeterministicDriver`

---

## Questions for Partnerspot

A few clarifying questions to ensure our fixes fully address your needs:

1. **Which LLM provider/preset** are you using? Some providers have quirks in their tool call response format.

2. **Are you seeing `Result::failure` from your tools** (SearchEntities, CreateProgramLead, DeleteProgramLead)? The new error policy may help if tools occasionally fail.

3. **Do you need the `ReverbAgentEventAdapter`** as a standalone package, or is the reference implementation in `packages/addons/src/Agent/Broadcasting/` sufficient?

---

## Acknowledgment

Your detailed feedback with code references made this work possible. The workarounds you documented (especially `looksLikeToolCallArgs()`) helped us understand the exact pain points. We appreciate your willingness to contribute and look forward to hearing how the updates work in your production system.

---

**Files Changed**:
- `packages/polyglot/src/Inference/Drivers/OpenAI/OpenAIResponseAdapter.php`
- `packages/addons/src/Agent/Drivers/ToolCalling/ToolCallingDriver.php`
- `packages/addons/src/StepByStep/Continuation/ContinuationCriteria.php`
- `packages/addons/src/StepByStep/Continuation/ContinuationOutcome.php` (new)
- `packages/addons/src/StepByStep/Continuation/ContinuationEvaluation.php` (new)
- `packages/addons/src/StepByStep/Continuation/StopReason.php` (new)
- `packages/addons/src/StepByStep/Continuation/ErrorPolicy.php` (new)
- `packages/addons/src/StepByStep/Continuation/Criteria/ErrorPolicyCriterion.php` (new)
- `packages/addons/src/Agent/Core/Data/AgentState.php`
- `packages/addons/src/StepByStep/State/StateInfo.php`
- `packages/addons/src/Agent/AgentBuilder.php`
- `packages/addons/src/Agent/Events/ContinuationEvaluated.php` (new)
- `packages/addons/src/Agent/Serialization/SlimAgentStateSerializer.php` (new)
- `packages/addons/src/Agent/Broadcasting/ReverbAgentEventAdapter.php` (new)
- `packages/messages/src/Message.php`
- `packages/addons/AGENT.md`
