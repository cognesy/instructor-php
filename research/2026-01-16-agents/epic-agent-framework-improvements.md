# Epic: Agent Framework Improvements

**Epic ID**: AGENT-2026-01
**Created**: 2026-01-16
**Status**: In Progress
**Priority**: P0 (Critical)
**Estimated Effort**: ~54 hours
**Timeline**: 4 weeks

---

## Executive Summary

This epic addresses critical bugs and feature gaps identified by the PRM team in the InstructorPHP Agent framework. The work is organized into 8 phases with clear dependencies.

### Completed Work
- [x] Phase 0: ExecutionTimeLimit Critical Bug Fix
- [x] Phase 1.1: Message Role Convenience Helpers

### Reference Documents
All specifications are in `research/2026-01-16-agents/`:
- `continuation-api-proposal.md` - Complete API specification for continuation tracing + error policy
- `continuation-tracing-redesign-spec.md` - High-level design spec
- `remaining-prm-issues-spec.md` - Issues 1, 3, 4 specifications
- `slim-serialization-reverb-adapter.md` - Serialization + Reverb adapter
- `ui-tool-call-rendering-contract.md` - UI rendering contract

---

## Phase 0: Critical Bug Fix [COMPLETED]

### Task 0.1: ExecutionTimeLimit Uses Wrong Timestamp [COMPLETED]

**Status**: ✅ COMPLETED (2026-01-16)

**Outcome**: Multi-turn conversations no longer timeout immediately when sessions span multiple days.

**What was implemented**:
1. Added `executionStartedAt` field to `AgentState`
2. Added `markExecutionStarted()` method called at execution entry point
3. Updated `StepByStep.php` to reset execution clock in `finalStep()` and `iterator()`
4. Updated `AgentBuilder.php` to use `executionStartedAt() ?? startedAt()` fallback

**Files Modified**:
- `packages/addons/src/Agent/Core/Data/AgentState.php`
- `packages/addons/src/StepByStep/StepByStep.php`
- `packages/addons/src/Agent/AgentBuilder.php`
- `packages/addons/src/StepByStep/Continuation/Criteria/ExecutionTimeLimit.php`

**Verification Status**: ✅ Verified working

---

## Phase 1: Foundation [PARTIALLY COMPLETED]

### Task 1.1: Message Role Convenience Helpers [COMPLETED]

**Status**: ✅ COMPLETED (2026-01-16)

**Outcome**: Developers can use `$message->isAssistant()` instead of `$message->role() === MessageRole::Assistant`.

**What was implemented**:
- `isUser(): bool`
- `isAssistant(): bool`
- `isTool(): bool`
- `isSystem(): bool` (covers System AND Developer)
- `isDeveloper(): bool`
- `hasRole(MessageRole ...$roles): bool`

**Files Modified**:
- `packages/messages/src/Message.php`

**Verification Status**: ✅ Verified working

---

### Task 1.2: Tool Args Leak Fix (Issue 1)

**Status**: 🔲 TODO
**Priority**: HIGH
**Effort**: 4 hours
**Dependencies**: None
**Assignee**: _unassigned_

#### Objective
Fix bug where tool call arguments leak into message content, polluting conversation history and UI.

#### Problem Description
When the LLM responds with tool calls, `OpenAIResponseAdapter::makeContent()` falls back to returning tool call arguments as content. This causes JSON tool args to appear in the UI and conversation history.

#### Root Cause
**Primary** (`packages/polyglot/src/Inference/Drivers/OpenAI/OpenAIResponseAdapter.php:82-90`):
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

**Secondary** (`packages/addons/src/Agent/Drivers/ToolCalling/ToolCallingDriver.php:156-158`):
```php
$outputMessages = $followUps->appendMessage(
    Message::asAssistant($response->content()),  // <-- Appends leaked args
);
```

#### Implementation Requirements

**Option A (Primary - Source Fix)**:
Modify `OpenAIResponseAdapter.php` to remove the fallback:
```php
protected function makeContent(array $data): string {
    return $data['choices'][0]['message']['content'] ?? '';
}
```

**Option B (Defense-in-Depth)**:
Also modify `ToolCallingDriver::buildStepFromResponse()` to guard against empty/JSON content:
```php
private function buildStepFromResponse(...): AgentStep {
    $content = $response->content();
    $hasToolCalls = $response->hasToolCalls();

    // Only append content if it's natural language, not tool call JSON
    $outputMessages = ($content !== '' && !$hasToolCalls)
        ? $followUps->appendMessage(Message::asAssistant($content))
        : $followUps;
    // ...
}
```

#### Files to Modify
| File | Change |
|------|--------|
| `packages/polyglot/src/Inference/Drivers/OpenAI/OpenAIResponseAdapter.php` | Remove `$contentFnArgs` fallback |
| `packages/addons/src/Agent/Drivers/ToolCalling/ToolCallingDriver.php` | Add guard in `buildStepFromResponse()` |

#### Acceptance Criteria & Validation Plan

| Test Case | Expected Result | Validation Method |
|-----------|-----------------|-------------------|
| Tool call with empty content | No assistant message appended | Unit test |
| Tool call with natural language content | Assistant message preserved | Unit test |
| Final response without tool calls | Normal assistant message | Unit test |
| Streaming: partial tool args | Should not leak to content delta | Integration test |
| UI displays tool call response | No JSON visible in chat | Manual verification |

```bash
# Run tests after implementation
./vendor/bin/pest --filter "OpenAIResponseAdapter"
./vendor/bin/pest --filter "ToolCallingDriver"
```

---

## Phase 2: Continuation Tracing

### Task 2.1: Create Core Continuation Types

**Status**: 🔲 TODO
**Priority**: HIGH
**Effort**: 4 hours
**Dependencies**: None
**Assignee**: _unassigned_

#### Objective
Create new types for continuation decision tracing to enable observability and debugging.

#### Implementation Requirements

Create the following new files in `packages/addons/src/StepByStep/Continuation/`:

**1. StopReason.php** - Enum for standardized stop reasons:
```php
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
```

**2. ContinuationEvaluation.php** - Per-criterion decision record:
```php
final readonly class ContinuationEvaluation
{
    public function __construct(
        public string $criterionClass,
        public ContinuationDecision $decision,
        public string $reason,
        public array $context = [],
    ) {}

    public static function fromDecision(string $criterionClass, ContinuationDecision $decision): self;
}
```

**3. ContinuationOutcome.php** - Aggregate result with full trace:
```php
final readonly class ContinuationOutcome
{
    public function __construct(
        public ContinuationDecision $decision,
        public bool $shouldContinue,
        public string $resolvedBy,
        public StopReason $stopReason,
        public array $evaluations,
    ) {}

    public function getEvaluationFor(string $criterionClass): ?ContinuationEvaluation;
    public function getForbiddingCriterion(): ?string;
    public function toArray(): array;
}
```

**4. CanExplainContinuation.php** - Optional interface:
```php
interface CanExplainContinuation
{
    public function explain(object $state): ContinuationEvaluation;
}
```

#### Reference
See `continuation-api-proposal.md` for complete implementation code.

#### Acceptance Criteria & Validation Plan

| Test Case | Expected Result | Validation Method |
|-----------|-----------------|-------------------|
| `StopReason` enum | Has all 9 cases | Unit test |
| `ContinuationEvaluation::fromDecision()` | Generates default reason | Unit test |
| `ContinuationOutcome::shouldContinue()` | Returns correct boolean | Unit test |
| `ContinuationOutcome::getForbiddingCriterion()` | Finds first forbid | Unit test |
| `ContinuationOutcome::toArray()` | Produces valid array | Unit test |

```bash
# Create test file: packages/addons/tests/Unit/StepByStep/Continuation/
# - StopReasonTest.php
# - ContinuationEvaluationTest.php
# - ContinuationOutcomeTest.php
```

---

### Task 2.2: Add ContinuationCriteria.evaluate()

**Status**: 🔲 TODO
**Priority**: HIGH
**Effort**: 4 hours
**Dependencies**: Task 2.1
**Assignee**: _unassigned_

#### Objective
Add `evaluate()` method to `ContinuationCriteria` that returns rich decision trace.

#### Implementation Requirements

Modify `packages/addons/src/StepByStep/Continuation/ContinuationCriteria.php`:

```php
public function evaluate(object $state): ContinuationOutcome {
    if ($this->criteria === []) {
        return new ContinuationOutcome(
            decision: ContinuationDecision::AllowStop,
            shouldContinue: false,
            resolvedBy: self::class,
            stopReason: StopReason::Completed,
            evaluations: [],
        );
    }

    $evaluations = [];
    $resolvedBy = null;
    $stopReason = StopReason::Completed;

    foreach ($this->criteria as $criterion) {
        $eval = $criterion instanceof CanExplainContinuation
            ? $criterion->explain($state)
            : ContinuationEvaluation::fromDecision($criterion::class, $criterion->decide($state));

        $evaluations[] = $eval;

        if ($eval->decision === ContinuationDecision::ForbidContinuation && $resolvedBy === null) {
            $resolvedBy = $eval->criterionClass;
            $stopReason = $this->inferStopReason($eval);
        }
    }

    $decisions = array_map(fn($e) => $e->decision, $evaluations);
    $shouldContinue = ContinuationDecision::canContinueWith(...$decisions);

    // ... rest of implementation
}

private function inferStopReason(ContinuationEvaluation $eval): StopReason {
    return match (true) {
        str_contains($eval->criterionClass, 'StepsLimit') => StopReason::StepsLimitReached,
        str_contains($eval->criterionClass, 'TokenUsageLimit') => StopReason::TokenLimitReached,
        str_contains($eval->criterionClass, 'ExecutionTimeLimit') => StopReason::TimeLimitReached,
        str_contains($eval->criterionClass, 'ErrorPolicy') => StopReason::ErrorForbade,
        str_contains($eval->criterionClass, 'FinishReason') => StopReason::FinishReasonReceived,
        default => StopReason::GuardForbade,
    };
}
```

**Backward Compatibility**: Existing `canContinue()` and `decide()` should delegate to `evaluate()`:
```php
public function canContinue(object $state): bool {
    return $this->evaluate($state)->shouldContinue;
}

public function decide(object $state): ContinuationDecision {
    return $this->evaluate($state)->decision;
}
```

#### Reference
See `continuation-api-proposal.md` for complete implementation.

#### Acceptance Criteria & Validation Plan

| Test Case | Expected Result | Validation Method |
|-----------|-----------------|-------------------|
| `evaluate()` with forbid scenario | Returns ForbidContinuation outcome | Unit test |
| `evaluate()` with continue scenario | Returns shouldContinue=true | Unit test |
| `evaluate()` includes all criteria | All evaluations in trace | Unit test |
| `canContinue()` matches `evaluate()` | Same boolean result | Unit test |
| `decide()` matches `evaluate()` | Same decision result | Unit test |
| Empty criteria | Returns AllowStop | Unit test |
| Existing agent behavior | No changes to output | Integration test |

---

### Task 2.3: Add ContinuationEvaluated Event

**Status**: 🔲 TODO
**Priority**: HIGH
**Effort**: 2 hours
**Dependencies**: Task 2.1, Task 2.2
**Assignee**: _unassigned_

#### Objective
Add event for observability of continuation decisions.

#### Implementation Requirements

**Create new file** `packages/addons/src/Agent/Events/ContinuationEvaluated.php`:

```php
final class ContinuationEvaluated extends AgentEvent
{
    public function __construct(
        public readonly string $agentId,
        public readonly ?string $parentAgentId,
        public readonly int $stepNumber,
        public readonly ContinuationOutcome $outcome,
    ) {
        parent::__construct([
            'agentId' => $this->agentId,
            'parentAgentId' => $this->parentAgentId,
            'step' => $this->stepNumber,
            'shouldContinue' => $this->outcome->shouldContinue,
            'stopReason' => $this->outcome->stopReason->value,
            'resolvedBy' => $this->outcome->resolvedBy,
        ]);
    }

    #[\Override]
    public function __toString(): string {
        $action = $this->outcome->shouldContinue ? 'CONTINUE' : 'STOP';
        $reason = $this->outcome->shouldContinue
            ? "requested by {$this->outcome->resolvedBy}"
            : $this->outcome->stopReason->value;

        return sprintf(
            'Agent [%s] step %d: %s (%s)',
            substr($this->agentId, 0, 8),
            $this->stepNumber,
            $action,
            $reason
        );
    }
}
```

**Modify** `packages/addons/src/Agent/Agent.php` to emit the event after each step's continuation check.

#### Acceptance Criteria & Validation Plan

| Test Case | Expected Result | Validation Method |
|-----------|-----------------|-------------------|
| Event emitted after each step | Event received by listener | Integration test |
| Event contains full `ContinuationOutcome` | All fields populated | Unit test |
| `__toString()` output | Readable format | Unit test |
| Event payload matches spec | All expected keys present | Unit test |

---

## Phase 3: Error Policy

### Task 3.1: Create Error Classification Types

**Status**: 🔲 TODO
**Priority**: HIGH
**Effort**: 4 hours
**Dependencies**: None
**Assignee**: _unassigned_

#### Objective
Create types for granular error classification and handling decisions.

#### Implementation Requirements

Create the following files in `packages/addons/src/StepByStep/Continuation/`:

**1. ErrorType.php**:
```php
enum ErrorType: string
{
    case Tool = 'tool';
    case Model = 'model';
    case Validation = 'validation';
    case RateLimit = 'rate_limit';
    case Timeout = 'timeout';
    case Unknown = 'unknown';
}
```

**2. ErrorHandlingDecision.php**:
```php
enum ErrorHandlingDecision: string
{
    case Stop = 'stop';
    case Retry = 'retry';
    case Ignore = 'ignore';
}
```

**3. ErrorContext.php**:
```php
final readonly class ErrorContext
{
    public function __construct(
        public ErrorType $type,
        public int $consecutiveFailures,
        public int $totalFailures,
        public ?string $message = null,
        public ?string $toolName = null,
        public array $metadata = [],
    ) {}

    public static function none(): self {
        return new self(
            type: ErrorType::Unknown,
            consecutiveFailures: 0,
            totalFailures: 0,
        );
    }
}
```

**4. CanResolveErrorContext.php**:
```php
interface CanResolveErrorContext
{
    public function resolve(object $state): ErrorContext;
}
```

#### Reference
See `continuation-api-proposal.md` for complete code.

#### Acceptance Criteria & Validation Plan

| Test Case | Expected Result | Validation Method |
|-----------|-----------------|-------------------|
| `ErrorType` enum | Has all 6 cases | Unit test |
| `ErrorHandlingDecision` enum | Has Stop, Retry, Ignore | Unit test |
| `ErrorContext::none()` | Returns zeroed context | Unit test |
| All types serializable | `toArray()` works | Unit test |

---

### Task 3.2: Create ErrorPolicy Class

**Status**: 🔲 TODO
**Priority**: HIGH
**Effort**: 4 hours
**Dependencies**: Task 3.1
**Assignee**: _unassigned_

#### Objective
Create configurable error policy with named constructors (presets).

#### Implementation Requirements

**Create** `packages/addons/src/StepByStep/Continuation/ErrorPolicy.php`:

```php
final readonly class ErrorPolicy
{
    public function __construct(
        public ErrorHandlingDecision $onToolError,
        public ErrorHandlingDecision $onModelError,
        public ErrorHandlingDecision $onValidationError,
        public ErrorHandlingDecision $onRateLimitError,
        public ErrorHandlingDecision $onTimeoutError,
        public ErrorHandlingDecision $onUnknownError,
        public int $maxRetries,
    ) {}

    // Named Constructors (Presets)
    public static function stopOnAnyError(): self;      // Default - matches current behavior
    public static function retryToolErrors(int $maxRetries = 3): self;
    public static function ignoreToolErrors(): self;
    public static function retryAll(int $maxRetries = 5): self;

    // Fluent Modifiers
    public function withMaxRetries(int $maxRetries): self;
    public function withToolErrorHandling(ErrorHandlingDecision $decision): self;

    // Policy Evaluation
    public function evaluate(ErrorContext $context): ErrorHandlingDecision;
}
```

#### Reference
See `continuation-api-proposal.md` for complete implementation.

#### Acceptance Criteria & Validation Plan

| Test Case | Expected Result | Validation Method |
|-----------|-----------------|-------------------|
| `stopOnAnyError()` | Returns Stop for all error types | Unit test |
| `retryToolErrors(3)` | Returns Retry for tool errors | Unit test |
| `evaluate()` respects maxRetries | Stops after max reached | Unit test |
| Fluent modifiers | Return modified instance | Unit test |
| Default preset matches current behavior | Same as ErrorPresenceCheck | Integration test |

---

### Task 3.3: Create ErrorPolicyCriterion

**Status**: 🔲 TODO
**Priority**: HIGH
**Effort**: 4 hours
**Dependencies**: Task 2.1, Task 3.1, Task 3.2
**Assignee**: _unassigned_

#### Objective
Create unified error policy criterion that **replaces both** `ErrorPresenceCheck` AND `RetryLimit`.

#### Implementation Requirements

**Create** `packages/addons/src/StepByStep/Continuation/Criteria/ErrorPolicyCriterion.php`:

```php
final readonly class ErrorPolicyCriterion implements CanDecideToContinue, CanExplainContinuation
{
    public function __construct(
        private ErrorPolicy $policy,
        private CanResolveErrorContext $contextResolver,
    ) {}

    public static function withPolicy(ErrorPolicy $policy): self {
        return new self($policy, new AgentErrorContextResolver());
    }

    public function decide(object $state): ContinuationDecision {
        return $this->explain($state)->decision;
    }

    public function explain(object $state): ContinuationEvaluation {
        $context = $this->contextResolver->resolve($state);
        $handling = $this->policy->evaluate($context);

        $decision = match ($handling) {
            ErrorHandlingDecision::Stop => ContinuationDecision::ForbidContinuation,
            ErrorHandlingDecision::Retry => ContinuationDecision::AllowContinuation,
            ErrorHandlingDecision::Ignore => ContinuationDecision::AllowContinuation,
        };

        return new ContinuationEvaluation(
            criterionClass: self::class,
            decision: $decision,
            reason: $this->buildReason($context, $handling),
            context: [...],
        );
    }
}
```

**Create** `packages/addons/src/Agent/Core/Continuation/AgentErrorContextResolver.php`:
```php
final readonly class AgentErrorContextResolver implements CanResolveErrorContext
{
    public function resolve(object $state): ErrorContext {
        // Implementation that classifies errors from AgentState
    }
}
```

#### Reference
See `continuation-api-proposal.md` for complete implementation.

#### Acceptance Criteria & Validation Plan

| Test Case | Expected Result | Validation Method |
|-----------|-----------------|-------------------|
| Implements `CanDecideToContinue` | Interface satisfied | Unit test |
| Implements `CanExplainContinuation` | Interface satisfied | Unit test |
| Returns ForbidContinuation on Stop | Correct decision | Unit test |
| Returns AllowContinuation on Retry/Ignore | Correct decision | Unit test |
| Explanation includes error type, count | Context populated | Unit test |
| Error classification works | Tool, model, rate limit detected | Integration test |

---

### Task 3.4: Integrate ErrorPolicy into AgentBuilder

**Status**: 🔲 TODO
**Priority**: HIGH
**Effort**: 2 hours
**Dependencies**: Task 3.3
**Assignee**: _unassigned_

#### Objective
Update AgentBuilder to use new ErrorPolicy, removing deprecated criteria.

#### Implementation Requirements

**Modify** `packages/addons/src/Agent/AgentBuilder.php`:

1. Add property and builder method:
```php
private ?ErrorPolicy $errorPolicy = null;

public function withErrorPolicy(ErrorPolicy $policy): self {
    $this->errorPolicy = $policy;
    return $this;
}
```

2. Update `buildContinuationCriteria()`:
```php
private function buildContinuationCriteria(): ContinuationCriteria {
    $baseCriteria = [
        new StepsLimit(...),
        new TokenUsageLimit(...),
        new ExecutionTimeLimit(...),
        new FinishReasonCheck(...),

        // REPLACE ErrorPresenceCheck + RetryLimit with:
        ErrorPolicyCriterion::withPolicy(
            $this->errorPolicy ?? ErrorPolicy::stopOnAnyError()
        ),

        new ToolCallPresenceCheck(...),
    ];
    // ...
}
```

3. **Remove** references to `ErrorPresenceCheck` and `RetryLimit` from base criteria.

#### Breaking Changes
- `ErrorPresenceCheck` removed from base criteria
- `RetryLimit` removed from base criteria

**Migration path**:
```php
// Before
->addContinuationCriteria(new ErrorPresenceCheck(...))
->addContinuationCriteria(new RetryLimit(3, ...))

// After
->withErrorPolicy(ErrorPolicy::retryToolErrors(3))
```

#### Acceptance Criteria & Validation Plan

| Test Case | Expected Result | Validation Method |
|-----------|-----------------|-------------------|
| Default behavior unchanged | Stops on any error | Integration test |
| Custom error policy applied | Policy respected | Integration test |
| `withErrorPolicy()` works | Fluent builder returns self | Unit test |
| No `ErrorPresenceCheck` in base | Removed from criteria | Code review |
| No `RetryLimit` in base | Removed from criteria | Code review |

---

## Phase 4: Cumulative Time Tracking

### Task 4.1: Enhance StateInfo with Cumulative Time

**Status**: 🔲 TODO
**Priority**: MEDIUM
**Effort**: 2 hours
**Dependencies**: None
**Assignee**: _unassigned_

#### Objective
Add cumulative execution time tracking to StateInfo for pause/resume scenarios.

#### Implementation Requirements

**Modify** `packages/addons/src/StepByStep/State/StateInfo.php`:

```php
final readonly class StateInfo
{
    public function __construct(
        private string $id,
        private DateTimeImmutable $startedAt,
        private DateTimeImmutable $updatedAt,
        private float $cumulativeExecutionSeconds = 0.0,  // NEW
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
            'cumulativeExecutionSeconds' => $this->cumulativeExecutionSeconds,  // NEW
        ];
    }

    public static function fromArray(array $data): self {
        return new self(
            $data['id'] ?? Uuid::uuid4(),
            new DateTimeImmutable($data['startedAt'] ?? 'now'),
            new DateTimeImmutable($data['updatedAt'] ?? 'now'),
            $data['cumulativeExecutionSeconds'] ?? 0.0,  // NEW - with backward compat default
        );
    }
}
```

#### Reference
See `remaining-prm-issues-spec.md` Issue 3.

#### Acceptance Criteria & Validation Plan

| Test Case | Expected Result | Validation Method |
|-----------|-----------------|-------------------|
| New field serializes correctly | Appears in `toArray()` | Unit test |
| New field deserializes correctly | Restored from `fromArray()` | Unit test |
| `addExecutionTime()` accumulates | Sum is correct | Unit test |
| Backward compatible | Old data loads with default 0.0 | Unit test |

---

### Task 4.2: Create CumulativeExecutionTimeLimit Criterion

**Status**: 🔲 TODO
**Priority**: MEDIUM
**Effort**: 2 hours
**Dependencies**: Task 2.1, Task 4.1
**Assignee**: _unassigned_

#### Objective
Create criterion that limits total processing time across pause/resume cycles.

#### Implementation Requirements

**Create** `packages/addons/src/StepByStep/Continuation/Criteria/CumulativeExecutionTimeLimit.php`:

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

#### Acceptance Criteria & Validation Plan

| Test Case | Expected Result | Validation Method |
|-----------|-----------------|-------------------|
| Implements `CanExplainContinuation` | Interface satisfied | Unit test |
| Returns ForbidContinuation when exceeded | Correct decision | Unit test |
| Returns AllowContinuation when under | Correct decision | Unit test |
| Explanation includes time and limit | Context populated | Unit test |

---

### Task 4.3: Track Step Duration in Agent

**Status**: 🔲 TODO
**Priority**: MEDIUM
**Effort**: 2 hours
**Dependencies**: Task 4.1, Task 4.2
**Assignee**: _unassigned_

#### Objective
Record step execution duration and accumulate in state.

#### Implementation Requirements

**Modify** `packages/addons/src/Agent/Agent.php`:

```php
protected function performStep(object $state): object {
    $stepStartTime = microtime(true);

    try {
        $nextStep = $this->makeNextStep($state);
        $nextState = $this->applyStep(state: $state, nextStep: $nextStep);

        // Track step duration
        $stepDuration = microtime(true) - $stepStartTime;
        $nextState = $nextState->with(
            stateInfo: $nextState->stateInfo()->addExecutionTime($stepDuration)
        );

        return $this->onStepCompleted($nextState);
    } catch (Throwable $error) {
        return $this->onFailure($error, $state);
    }
}
```

#### Acceptance Criteria & Validation Plan

| Test Case | Expected Result | Validation Method |
|-----------|-----------------|-------------------|
| Step duration recorded | Non-zero value | Unit test |
| Cumulative time matches sum | Total equals sum of steps | Integration test |
| Pause/resume preserves time | Time restored from serialization | Integration test |

---

### Task 4.4: Add withCumulativeTimeout to AgentBuilder

**Status**: 🔲 TODO
**Priority**: MEDIUM
**Effort**: 1 hour
**Dependencies**: Task 4.1, Task 4.2, Task 4.3
**Assignee**: _unassigned_

#### Objective
Add builder method to opt-in to cumulative time limit.

#### Implementation Requirements

**Modify** `packages/addons/src/Agent/AgentBuilder.php`:

```php
private bool $useCumulativeTimeLimit = false;

public function withCumulativeTimeout(int $seconds): self {
    $this->useCumulativeTimeLimit = true;
    $this->maxExecutionTime = $seconds;
    return $this;
}

// In buildContinuationCriteria():
$timeLimitCriterion = $this->useCumulativeTimeLimit
    ? new CumulativeExecutionTimeLimit(
        $this->maxExecutionTime,
        static fn($state) => $state->stateInfo()->cumulativeExecutionSeconds()
    )
    : new ExecutionTimeLimit(
        $this->maxExecutionTime,
        static fn($state) => $state->executionStartedAt() ?? $state->startedAt(),
        null
    );
```

#### Acceptance Criteria & Validation Plan

| Test Case | Expected Result | Validation Method |
|-----------|-----------------|-------------------|
| Default uses wall-clock | ExecutionTimeLimit used | Unit test |
| `withCumulativeTimeout()` switches | CumulativeExecutionTimeLimit used | Unit test |
| Builder returns self | Fluent chaining works | Unit test |

---

## Phase 5: Serialization

### Task 5.1: Create SlimSerializationConfig

**Status**: 🔲 TODO
**Priority**: MEDIUM
**Effort**: 2 hours
**Dependencies**: None
**Assignee**: _unassigned_

#### Objective
Create configurable serialization presets.

#### Implementation Requirements

**Create** `packages/addons/src/Agent/Serialization/SlimSerializationConfig.php`:

```php
final readonly class SlimSerializationConfig
{
    public function __construct(
        public int $maxMessages = 50,
        public int $maxContentLength = 1000,
        public bool $includeToolArgs = true,
        public bool $includeMetadata = true,
        public bool $includeAllSteps = false,
    ) {}

    public static function minimal(): self {
        return new self(
            maxMessages: 10,
            maxContentLength: 500,
            includeToolArgs: false,
            includeMetadata: false,
            includeAllSteps: false,
        );
    }

    public static function standard(): self {
        return new self();  // Defaults
    }

    public static function full(): self {
        return new self(
            maxMessages: PHP_INT_MAX,
            maxContentLength: PHP_INT_MAX,
            includeToolArgs: true,
            includeMetadata: true,
            includeAllSteps: true,
        );
    }
}
```

#### Reference
See `slim-serialization-reverb-adapter.md` for complete specification.

#### Acceptance Criteria & Validation Plan

| Test Case | Expected Result | Validation Method |
|-----------|-----------------|-------------------|
| `minimal()` preset | Restrictive settings | Unit test |
| `standard()` preset | Default settings | Unit test |
| `full()` preset | No restrictions | Unit test |

---

### Task 5.2: Create SlimAgentStateSerializer

**Status**: 🔲 TODO
**Priority**: MEDIUM
**Effort**: 4 hours
**Dependencies**: Task 4.1, Task 5.1
**Assignee**: _unassigned_

#### Objective
Create serializer that produces smaller state payloads for Reverb.

#### Implementation Requirements

**Create** `packages/addons/src/Agent/Serialization/CanSerializeAgentState.php`:
```php
interface CanSerializeAgentState
{
    public function serialize(AgentState $state): array;
    public function deserialize(array $data): AgentState;
}
```

**Create** `packages/addons/src/Agent/Serialization/SlimAgentStateSerializer.php`:
```php
final readonly class SlimAgentStateSerializer implements CanSerializeAgentState
{
    public function __construct(
        private SlimSerializationConfig $config,
    ) {}

    public function serialize(AgentState $state): array {
        return [
            'agent_id' => $state->agentId,
            'status' => $state->status()->value,
            'execution' => [
                'step_count' => $state->stepCount(),
                'cumulative_seconds' => $state->stateInfo()->cumulativeExecutionSeconds(),
            ],
            'messages' => $this->serializeMessages($state),
            'current_step' => $this->serializeStep($state->currentStep()),
            // ... truncation logic
        ];
    }

    public function deserialize(array $data): AgentState {
        // Reconstruct state from slim format
    }
}
```

#### Reference
See `slim-serialization-reverb-adapter.md` for complete specification.

#### Acceptance Criteria & Validation Plan

| Test Case | Expected Result | Validation Method |
|-----------|-----------------|-------------------|
| Message truncation works | Limited to maxMessages | Unit test |
| Content length truncation | Long content trimmed | Unit test |
| Tool args redaction | Args removed when configured | Unit test |
| Cumulative time included | Field present | Unit test |
| Deserialization produces valid state | Can continue execution | Integration test |
| Round-trip preserves essential data | Critical fields match | Unit test |

---

## Phase 6: Reverb/Events

### Task 6.1: Create Event Adapter Interface

**Status**: 🔲 TODO
**Priority**: MEDIUM
**Effort**: 1 hour
**Dependencies**: None
**Assignee**: _unassigned_

#### Objective
Create interface for broadcasting agent events to external systems.

#### Implementation Requirements

**Create** `packages/addons/src/Agent/Broadcasting/CanBroadcastAgentEvents.php`:

```php
interface CanBroadcastAgentEvents
{
    public function broadcast(AgentEvent $event): void;
    public function broadcastBatch(array $events): void;
}
```

#### Acceptance Criteria & Validation Plan

| Test Case | Expected Result | Validation Method |
|-----------|-----------------|-------------------|
| Interface defined | Can be implemented | Code review |

---

### Task 6.2: Create AgentEventEnvelopeAdapter

**Status**: 🔲 TODO
**Priority**: MEDIUM
**Effort**: 4 hours
**Dependencies**: Task 2.3, Task 6.1
**Assignee**: _unassigned_

#### Objective
Create adapter that transforms agent events into Reverb-compatible format.

#### Implementation Requirements

**Create** `packages/addons/src/Agent/Broadcasting/AgentEventEnvelopeAdapter.php`:

```php
final class AgentEventEnvelopeAdapter implements CanBroadcastAgentEvents
{
    public function broadcast(AgentEvent $event): void {
        $envelope = $this->toEnvelope($event);
        // Emit to Reverb
    }

    private function toEnvelope(AgentEvent $event): array {
        return match (true) {
            $event instanceof AgentStepStarted => $this->mapStepStarted($event),
            $event instanceof AgentStepCompleted => $this->mapStepCompleted($event),
            $event instanceof ToolCallStarted => $this->mapToolStarted($event),
            $event instanceof ToolCallCompleted => $this->mapToolCompleted($event),
            $event instanceof ContinuationEvaluated => $this->mapContinuation($event),
            default => $this->mapGeneric($event),
        };
    }
}
```

**Event envelope format**:
```json
{
  "event": "agent.step.completed",
  "timestamp": "2026-01-16T10:05:01Z",
  "agent_id": "abc123",
  "data": { ... }
}
```

#### Reference
See `slim-serialization-reverb-adapter.md` and `ui-tool-call-rendering-contract.md` for specifications.

#### Acceptance Criteria & Validation Plan

| Test Case | Expected Result | Validation Method |
|-----------|-----------------|-------------------|
| `agent.step.started` emitted | Correct format | Unit test |
| `agent.step.completed` with usage/duration | Fields present | Unit test |
| `agent.tool.started` event keys | Uses `tool` not `name` | Unit test |
| `agent.tool.completed` event keys | Has `tool`, `success`, `error` | Unit test |
| `agent.continuation` event | Includes evaluations | Unit test |
| Envelope format matches spec | Valid JSON structure | Unit test |

---

### Task 6.3: Verify Tool Event Keys

**Status**: 🔲 TODO
**Priority**: MEDIUM
**Effort**: 1 hour
**Dependencies**: None
**Assignee**: _unassigned_

#### Objective
Verify existing tool events have correct property names for adapter.

#### Implementation Requirements

**Verify** `packages/addons/src/Agent/Events/ToolCallStarted.php`:
- Should have `tool` property (not `name`)

**Verify** `packages/addons/src/Agent/Events/ToolCallCompleted.php`:
- Should have `tool`, `success`, `error` properties

**Modify if needed** to align with adapter expectations.

#### Acceptance Criteria & Validation Plan

| Test Case | Expected Result | Validation Method |
|-----------|-----------------|-------------------|
| `ToolCallStarted` has `tool` | Property exists | Code review |
| `ToolCallCompleted` has required properties | All present | Code review |

---

## Phase 7: Documentation

### Task 7.1: Create Troubleshooting Guide

**Status**: 🔲 TODO
**Priority**: LOW
**Effort**: 2 hours
**Dependencies**: All previous phases
**Assignee**: _unassigned_

#### Objective
Create documentation for debugging common continuation issues.

#### Topics to Cover
1. "Why did my agent stop after one step?"
   - How to use `ContinuationOutcome` for debugging
   - Checking `evaluations` array
2. "How to debug continuation decisions"
   - Subscribing to `ContinuationEvaluated` events
   - Reading the trace
3. "Configuring error policies"
   - Presets and when to use them
   - Custom policies
4. "Using cumulative time for pause/resume"
   - When to use `withCumulativeTimeout()`
   - Serialization considerations

#### Acceptance Criteria & Validation Plan

| Test Case | Expected Result | Validation Method |
|-----------|-----------------|-------------------|
| All topics covered | Documentation complete | Review |
| Examples compile | Code samples work | Manual test |

---

### Task 7.2: Create Migration Guide

**Status**: 🔲 TODO
**Priority**: LOW
**Effort**: 2 hours
**Dependencies**: All previous phases
**Assignee**: _unassigned_

#### Objective
Document breaking changes and migration path.

#### Topics to Cover
1. `ErrorPresenceCheck` → `ErrorPolicyCriterion`
   - Before/after code examples
2. `RetryLimit` → `ErrorPolicy.maxRetries`
   - Before/after code examples
3. Using slim serialization
   - When to use each preset
4. Reverb event envelope format
   - Event names and payload structure

#### Acceptance Criteria & Validation Plan

| Test Case | Expected Result | Validation Method |
|-----------|-----------------|-------------------|
| All breaking changes documented | Complete list | Review |
| Migration examples work | Code compiles | Manual test |

---

## Summary

### Task Status Overview

| Phase | Task | Status | Priority | Effort |
|-------|------|--------|----------|--------|
| 0 | ExecutionTimeLimit fix | ✅ DONE | P0 | 4h |
| 1.1 | Message role helpers | ✅ DONE | LOW | 2h |
| 1.2 | Tool args leak fix | 🔲 TODO | HIGH | 4h |
| 2.1 | Core continuation types | 🔲 TODO | HIGH | 4h |
| 2.2 | ContinuationCriteria.evaluate() | 🔲 TODO | HIGH | 4h |
| 2.3 | Continuation event | 🔲 TODO | HIGH | 2h |
| 3.1 | Error types | 🔲 TODO | HIGH | 4h |
| 3.2 | ErrorPolicy | 🔲 TODO | HIGH | 4h |
| 3.3 | ErrorPolicyCriterion | 🔲 TODO | HIGH | 4h |
| 3.4 | AgentBuilder integration | 🔲 TODO | HIGH | 2h |
| 4.1 | StateInfo enhancement | 🔲 TODO | MEDIUM | 2h |
| 4.2 | CumulativeExecutionTimeLimit | 🔲 TODO | MEDIUM | 2h |
| 4.3 | Agent step duration | 🔲 TODO | MEDIUM | 2h |
| 4.4 | AgentBuilder cumulative | 🔲 TODO | MEDIUM | 1h |
| 5.1 | SlimSerializationConfig | 🔲 TODO | MEDIUM | 2h |
| 5.2 | SlimAgentStateSerializer | 🔲 TODO | MEDIUM | 4h |
| 6.1 | Event adapter interface | 🔲 TODO | MEDIUM | 1h |
| 6.2 | AgentEventEnvelopeAdapter | 🔲 TODO | MEDIUM | 4h |
| 6.3 | Verify tool event keys | 🔲 TODO | MEDIUM | 1h |
| 7.1 | Troubleshooting guide | 🔲 TODO | LOW | 2h |
| 7.2 | Migration guide | 🔲 TODO | LOW | 2h |

### Dependency Graph

```
Phase 0 ✅ DONE
Phase 1.1 ✅ DONE

Phase 1.2 (independent)
Phase 2.1 (independent)
Phase 3.1 (independent)
Phase 4.1 (independent)
Phase 5.1 (independent)
Phase 6.1 (independent)
Phase 6.3 (independent)

Phase 2.2 → 2.1
Phase 2.3 → 2.1, 2.2
Phase 3.2 → 3.1
Phase 3.3 → 2.1, 3.1, 3.2
Phase 3.4 → 3.3
Phase 4.2 → 2.1, 4.1
Phase 4.3 → 4.1, 4.2
Phase 4.4 → 4.1, 4.2, 4.3
Phase 5.2 → 4.1, 5.1
Phase 6.2 → 2.3, 6.1
Phase 7.x → All previous
```

### Recommended Execution Order

**Sprint 1 (Week 1)**: Independent foundations
- Task 1.2, 2.1, 3.1, 4.1, 5.1, 6.1, 6.3 (in parallel)

**Sprint 2 (Week 2)**: Continuation + Error Policy
- Task 2.2, 2.3, 3.2, 3.3, 3.4 (sequential with dependencies)

**Sprint 3 (Week 3)**: Time Tracking + Serialization
- Task 4.2, 4.3, 4.4, 5.2 (sequential with dependencies)

**Sprint 4 (Week 4)**: Reverb + Documentation
- Task 6.2, 7.1, 7.2

**Total Remaining Effort**: ~48 hours (excluding completed tasks)
