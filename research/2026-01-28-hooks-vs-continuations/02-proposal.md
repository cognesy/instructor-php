# Proposal: Hook-Only Flow Control

**Date:** 2026-01-28 (rev5 - fully validated)

---

## Core Principles

1. **Hooks return AgentState** - Not decision objects, not outcomes. Just state.
2. **Hooks append ContinuationEvaluation** - Not full outcomes. Aggregation happens once per phase.
3. **ContinuationOutcome remains the single flow control** - Built from accumulated evaluations.
4. **No AgentState embedded anywhere** - State is passed as parameter, not stored in contexts.
5. **CurrentExecution is the transient carrier** - All in-flight step data lives here.
6. **Order-independent resolution** - Same precedence as old criteria via evaluation accumulation.
7. **Keep EvaluationProcessor** - Static utility class, no reason to delete it.
8. **Adapt existing ToolExecutor** - Use `useTool()` API, don't assume new `execute()` method.

---

## Hook Signature

```php
interface Hook
{
    public function handle(AgentState $state, callable $next): AgentState;
}
```

**Two behaviors:**
1. **State mutation** - Modify state and return it
2. **Flow control** - Append `ContinuationEvaluation` to `CurrentExecution`

```php
// State mutation only
public function handle(AgentState $state, callable $next): AgentState
{
    $modified = $state->withMetadata('key', 'value');
    return $next($modified);
}

// Flow control (append evaluation, don't short-circuit)
public function handle(AgentState $state, callable $next): AgentState
{
    if ($state->stepCount() >= $this->maxSteps) {
        // Use existing ContinuationEvaluation::fromDecision() API
        $state = $state->withEvaluation(
            ContinuationEvaluation::fromDecision(
                self::class,
                ContinuationDecision::ForbidContinuation,
                StopReason::StepsLimitReached
            )
        );
    }
    return $next($state);  // Always call next - evaluations accumulate
}
```

**Important:** Hooks should NOT short-circuit by skipping `$next()`. All hooks run, all evaluations accumulate, then a single aggregator resolves the outcome.

---

## Evaluation Accumulation & Resolution

### Hooks Append Evaluations

```php
// NEW: Add to CurrentExecution
private array $evaluations = [];

public function withEvaluation(ContinuationEvaluation $e): self
{
    return new self(
        $this->stepNumber,
        $this->startedAt,
        $this->id,
        // ... other fields ...
        evaluations: [...$this->evaluations, $e],
    );
}

public function evaluations(): array
{
    return $this->evaluations;
}

public function withClearedEvaluations(): self
{
    return new self(
        // ... all fields with evaluations: []
    );
}
```

### Single Aggregation Point

After each hook phase, AgentLoop builds `ContinuationOutcome` once:

```php
// In AgentLoop, after running hooks for a phase
private function aggregateAndClearEvaluations(AgentState $state): AgentState
{
    $evaluations = $state->currentExecution()?->evaluations() ?? [];
    if (empty($evaluations)) {
        return $state;
    }

    $outcome = ContinuationOutcome::fromEvaluations($evaluations);
    $state = $state->withContinuationOutcome($outcome);

    // Clear evaluations to avoid re-processing
    $state = $state->withCurrentExecution(
        $state->currentExecution()->withClearedEvaluations()
    );

    $this->eventEmitter->continuationEvaluated($state, $outcome);

    return $state;
}
```

### Outcome Clearing

Clear `continuationOutcome` at the start of each step to prevent stale outcomes from affecting later phases:

```php
private function clearOutcomeForNewStep(AgentState $state): AgentState
{
    $execution = $state->currentExecution();
    if ($execution === null || $execution->continuationOutcome === null) {
        return $state;
    }
    return $state->withCurrentExecution(
        $execution->withClearedContinuationOutcome()
    );
}

// Call at start of each step iteration
$state = $this->clearOutcomeForNewStep($state);
```

### AgentState::continuationOutcome() Update

Update `AgentState::continuationOutcome()` to check `currentExecution` first:

```php
// In AgentState
public function continuationOutcome(): ?ContinuationOutcome
{
    // Check currentExecution first (for in-flight evaluations)
    $currentOutcome = $this->execution?->currentExecution()?->continuationOutcome;
    if ($currentOutcome !== null) {
        return $currentOutcome;
    }

    // Fallback to last step execution (for historical data)
    return $this->execution?->continuationOutcome();
}
```

### Resolution Logic (Order-Independent)

Add `fromEvaluations()` to `ContinuationOutcome` using existing `ContinuationDecision::canContinueWith()`:

```php
// NEW: Add to ContinuationOutcome
public static function fromEvaluations(array $evaluations): self
{
    if (empty($evaluations)) {
        return self::empty();
    }

    $decisions = array_map(
        fn(ContinuationEvaluation $e) => $e->decision,
        $evaluations
    );

    $shouldContinue = ContinuationDecision::canContinueWith(...$decisions);

    // Find the dominating evaluation for stopReason/resolvedBy
    $dominated = self::findDominatingEvaluation($evaluations, $shouldContinue);

    return new self(
        shouldContinue: $shouldContinue,
        evaluations: $evaluations,
        stopReason: $dominated?->stopReason,
        resolvedBy: $dominated?->criterionClass,
    );
}

private static function findDominatingEvaluation(array $evaluations, bool $shouldContinue): ?ContinuationEvaluation
{
    if ($shouldContinue) {
        // Find RequestContinuation or AllowContinuation
        foreach ($evaluations as $eval) {
            if ($eval->decision === ContinuationDecision::RequestContinuation) {
                return $eval;
            }
        }
        foreach ($evaluations as $eval) {
            if ($eval->decision === ContinuationDecision::AllowContinuation) {
                return $eval;
            }
        }
    } else {
        // Find ForbidContinuation or AllowStop
        foreach ($evaluations as $eval) {
            if ($eval->decision === ContinuationDecision::ForbidContinuation) {
                return $eval;
            }
        }
        foreach ($evaluations as $eval) {
            if ($eval->decision === ContinuationDecision::AllowStop) {
                return $eval;
            }
        }
    }
    return $evaluations[0] ?? null;
}
```

---

## CurrentExecution Extensions

Current `CurrentExecution` is minimal. Extend with:
1. **Event-specific transient data** - tool call, inference response, exception
2. **Step-level transient data** - tool executions, messages (for AfterStep access)
3. **Evaluation accumulation** - evaluations list, aggregated outcome

```php
final readonly class CurrentExecution
{
    public string $id;

    public function __construct(
        public int $stepNumber,
        public DateTimeImmutable $startedAt = new DateTimeImmutable(),
        string $id = '',

        // Event-specific transient data (cleared after each event)
        public ?ToolCall $currentToolCall = null,
        public ?ToolExecution $currentToolExecution = null,
        public ?Messages $inferenceMessages = null,
        public ?InferenceResponse $inferenceResponse = null,
        public ?AgentException $exception = null,

        // Step-level transient data (for AfterStep hooks access)
        public ?ToolExecutions $toolExecutions = null,
        public ?Messages $outputMessages = null,

        // Evaluation accumulation
        public array $evaluations = [],
        public ?ContinuationOutcome $continuationOutcome = null,
    ) {
        $this->id = $id !== '' ? $id : Uuid::uuid4();
    }

    // Fluent setters - USE NAMED ARGUMENTS to avoid order mistakes
    public function withCurrentToolCall(?ToolCall $t): self
    {
        return new self(
            stepNumber: $this->stepNumber,
            startedAt: $this->startedAt,
            id: $this->id,
            currentToolCall: $t,
            currentToolExecution: $this->currentToolExecution,
            inferenceMessages: $this->inferenceMessages,
            inferenceResponse: $this->inferenceResponse,
            exception: $this->exception,
            toolExecutions: $this->toolExecutions,
            outputMessages: $this->outputMessages,
            evaluations: $this->evaluations,
            continuationOutcome: $this->continuationOutcome,
        );
    }
    // ... similar for other withX() methods using named args ...

    public function withEvaluation(ContinuationEvaluation $e): self
    {
        return new self(
            stepNumber: $this->stepNumber,
            startedAt: $this->startedAt,
            id: $this->id,
            currentToolCall: $this->currentToolCall,
            currentToolExecution: $this->currentToolExecution,
            inferenceMessages: $this->inferenceMessages,
            inferenceResponse: $this->inferenceResponse,
            exception: $this->exception,
            toolExecutions: $this->toolExecutions,
            outputMessages: $this->outputMessages,
            evaluations: [...$this->evaluations, $e],
            continuationOutcome: $this->continuationOutcome,
        );
    }

    public function withClearedEvaluations(): self { /* evaluations: [] */ }
    public function withClearedContinuationOutcome(): self { /* continuationOutcome: null */ }
    public function withClearedEventData(): self { /* tool call/exec, messages, exception = null */ }

    // Exclude transient fields from serialization
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'stepNumber' => $this->stepNumber,
            'startedAt' => $this->startedAt->format(DateTimeImmutable::ATOM),
            // All transient fields NOT serialized
        ];
    }
}
```

---

## Matcher Contract Update

Matchers must work with `AgentState` + `HookType` instead of `HookContext`:

```php
interface HookMatcher
{
    public function matches(AgentState $state, HookType $type): bool;
}

// ToolNameMatcher - works for both PreToolUse and PostToolUse
final class ToolNameMatcher implements HookMatcher
{
    public function __construct(private string $pattern) {}

    public function matches(AgentState $state, HookType $type): bool
    {
        if (!$type->isToolEvent()) {
            return false;
        }

        $execution = $state->currentExecution();
        if ($execution === null) {
            return false;
        }

        // Check currentToolCall first (set for both Pre and Post)
        $toolCall = $execution->currentToolCall;
        if ($toolCall !== null) {
            return fnmatch($this->pattern, $toolCall->name());
        }

        // Fallback to currentToolExecution for PostToolUse
        $toolExec = $execution->currentToolExecution;
        if ($toolExec !== null) {
            return fnmatch($this->pattern, $toolExec->name());
        }

        return false;
    }
}
```

---

## HookStack Event Filtering

Store hooks with EventTypeMatcher injected. Use `CompositeMatcher::and()` (existing API).

**Important:** Preserve registration order for stable hook execution (use stable sort).

```php
final class HookStack
{
    /** @var list<array{hook: Hook, matcher: HookMatcher, priority: int, order: int}> */
    private array $hooks = [];
    private int $registrationOrder = 0;

    public function add(HookType $type, Hook $hook, int $priority = 0, ?HookMatcher $additionalMatcher = null): void
    {
        $eventMatcher = new EventTypeMatcher($type);

        // Use existing CompositeMatcher::and() API (constructor is private)
        $matcher = $additionalMatcher !== null
            ? CompositeMatcher::and($eventMatcher, $additionalMatcher)
            : $eventMatcher;

        $this->hooks[] = [
            'hook' => $hook,
            'matcher' => $matcher,
            'priority' => $priority,
            'order' => $this->registrationOrder++,  // Track registration order
        ];

        // Stable sort: priority DESC, then registration order ASC (preserves FIFO for equal priority)
        usort($this->hooks, function($a, $b) {
            $priorityDiff = $b['priority'] <=> $a['priority'];
            if ($priorityDiff !== 0) {
                return $priorityDiff;
            }
            return $a['order'] <=> $b['order'];  // Earlier registration wins on tie
        });
    }

    public function process(HookType $type, AgentState $state): AgentState
    {
        // Filter hooks that match this event
        $matchingHooks = array_filter(
            $this->hooks,
            fn($entry) => $entry['matcher']->matches($state, $type)
        );

        if (empty($matchingHooks)) {
            return $state;
        }

        // Build chain (reverse to get correct execution order)
        $chain = fn(AgentState $s) => $s;

        foreach (array_reverse($matchingHooks) as ['hook' => $hook]) {
            $next = $chain;
            $chain = fn(AgentState $s) => $hook->handle($s, $next);
        }

        return $chain($state);
    }
}
```

---

## ErrorPolicy Integration (Correct API)

**Problem:** `AgentErrorContextResolver::resolve($state)` reads errors from `currentStep()`. In the new error flow, `currentStep` may be null when OnError hooks run.

**Solution:** Extend resolver to also read from `currentExecution->exception` when `currentStep` is null.

```php
// Update AgentErrorContextResolver
final class AgentErrorContextResolver
{
    public function resolve(object $state): ErrorContext
    {
        // ... existing logic reading from currentStep() ...

        // NEW: Also check currentExecution->exception as fallback
        if ($state instanceof AgentState) {
            $exception = $state->currentExecution()?->exception;
            if ($exception !== null && $consecutiveFailures === 0) {
                // Exception captured but not yet recorded in step
                $consecutiveFailures = 1;
                $lastError = $exception;
            }
        }

        return new ErrorContext(
            consecutiveFailures: $consecutiveFailures,
            lastError: $lastError,
            // ... other fields ...
        );
    }
}

// ErrorPolicyHook remains the same
final class ErrorPolicyHook implements Hook
{
    public function __construct(
        private ErrorPolicy $policy,
        private AgentErrorContextResolver $resolver,
    ) {}

    public function handle(AgentState $state, callable $next): AgentState
    {
        // Resolver now reads from both currentStep() AND currentExecution->exception
        $errorContext = $this->resolver->resolve($state);

        if ($errorContext->consecutiveFailures === 0) {
            return $next($state);
        }

        $decision = $this->policy->evaluate($errorContext);

        if ($decision === ErrorHandlingDecision::Stop) {
            $state = $state->withEvaluation(
                ContinuationEvaluation::fromDecision(
                    self::class,
                    ContinuationDecision::ForbidContinuation,
                    StopReason::ErrorForbade
                )
            );
        }
        // Retry/Ignore: no evaluation needed, continue

        return $next($state);
    }
}
```

---

## Tool Blocking Flow (Correct API)

**Note:** `ToolCallBlockedException` already exists. Use existing `ToolExecutor::useTool()` API.

The existing `ToolExecutor` already:
1. Calls `observer->onBeforeToolUse()` which returns `ToolUseDecision`
2. Throws `ToolCallBlockedException` if blocked
3. Calls `observer->onAfterToolUse()` after execution

**Strategy:** Adapt HookStack to work with existing ToolExecutor via observer pattern.

```php
// HookStackObserver implements CanObserveAgentLifecycle
// It bridges HookStack to the existing observer interface
final class HookStackObserver implements CanObserveAgentLifecycle
{
    public function __construct(
        private HookStack $hookStack,
        private AgentState $state,  // Updated during execution
    ) {}

    public function onBeforeToolUse(ToolCall $toolCall, AgentState $state): ToolUseDecision
    {
        // Set currentToolCall for hook access
        $this->state = $state->withCurrentExecution(
            $state->currentExecution()->withCurrentToolCall($toolCall)
        );

        // Run PreToolUse hooks
        $this->state = $this->hookStack->process(HookType::PreToolUse, $this->state);

        // Check for blocking exception
        $exception = $this->state->currentExecution()?->exception;
        if ($exception instanceof ToolCallBlockedException) {
            return ToolUseDecision::block($exception->getMessage());
        }

        return ToolUseDecision::proceed($toolCall);
    }

    public function onAfterToolUse(ToolExecution $execution, AgentState $state): ToolExecution
    {
        // Set currentToolExecution for hook access
        $this->state = $this->state->withCurrentExecution(
            $this->state->currentExecution()->withCurrentToolExecution($execution)
        );

        // Run PostToolUse hooks
        $this->state = $this->hookStack->process(HookType::PostToolUse, $this->state);

        // Hooks may modify execution via state
        return $this->state->currentExecution()?->currentToolExecution ?? $execution;
    }

    public function state(): AgentState
    {
        return $this->state;
    }
}

// Tool blocking hook (simplified - just sets exception)
final class ToolBlockingHook implements Hook
{
    public function __construct(private array $blockedTools) {}

    public function handle(AgentState $state, callable $next): AgentState
    {
        $toolCall = $state->currentExecution()?->currentToolCall;
        if ($toolCall === null) {
            return $next($state);
        }

        if (in_array($toolCall->name(), $this->blockedTools, true)) {
            $exception = new ToolCallBlockedException(
                $toolCall->name(),
                "Tool '{$toolCall->name()}' is blocked"
            );
            $state = $state->withCurrentExecution(
                $state->currentExecution()->withException($exception)
            );
        }

        return $next($state);
    }
}
```

**Tool blocking and ErrorPolicy:** Blocked tools should feed into ErrorPolicy. Record blocked tools into the step's errors:

```php
// In loop, after tool execution
if ($execution->result()->isFailure()) {
    $step = $step->withError($execution->result()->error());
}
// This ensures ErrorPolicy can see tool failures including blocks
```
```

---

## OnError Lifecycle

Define exact error capture flow:

```php
// In AgentLoop, wrap step execution in try-catch
private function executeStepWithErrorHandling(AgentState $state): AgentState
{
    try {
        return $this->executeStep($state);
    } catch (AgentException $e) {
        return $this->handleError($state, $e);
    } catch (Throwable $e) {
        return $this->handleError($state, AgentException::fromThrowable($e));
    }
}

private function handleError(AgentState $state, AgentException $exception): AgentState
{
    // 1. Store exception in CurrentExecution
    $state = $state->withCurrentExecution(
        $state->currentExecution()->withException($exception)
    );

    // 2. Run OnError hooks
    $state = $this->hookStack->process(HookType::OnError, $state);

    // 3. Aggregate evaluations
    $state = $this->aggregateAndClearEvaluations($state);

    // 4. Clear exception from event data
    $state = $state->withCurrentExecution(
        $state->currentExecution()->withException(null)
    );

    return $state;
}
```

---

## Stop Hook Lifecycle

**Important semantics:** Stop hooks can only prevent stop when the stop was caused by `AllowStop` (work driver finished). They **cannot** override `ForbidContinuation` (guard forbids like step limit, time limit, error policy).

This is because `ContinuationDecision::canContinueWith()` has this priority:
1. ANY `ForbidContinuation` → STOP (cannot be overridden)
2. ANY `RequestContinuation` → CONTINUE (overrides AllowStop only)
3. ANY `AllowStop` → STOP
4. ANY `AllowContinuation` → CONTINUE

```php
// In AgentLoop, after deciding to stop but before finalizing
private function runStopHooks(AgentState $state): AgentState
{
    $outcome = $state->continuationOutcome();

    // Only allow stop prevention if stopped by AllowStop (not ForbidContinuation)
    $canPreventStop = $outcome !== null
        && !$outcome->shouldContinue()
        && $outcome->getForbiddingCriterion() === null;  // No forbid present

    if (!$canPreventStop) {
        // Guard forbade - stop hooks run for observation only
        $this->hookStack->process(HookType::Stop, $state);
        return $state;  // Don't aggregate - can't change outcome
    }

    // AllowStop - hooks can append RequestContinuation to prevent
    $state = $this->hookStack->process(HookType::Stop, $state);
    $state = $this->aggregateAndClearEvaluations($state);

    return $state;
}

// In main loop
if (!$this->shouldContinue($state)) {
    // Give stop hooks a chance to prevent stop (only for AllowStop)
    $state = $this->runStopHooks($state);

    // Re-check after stop hooks
    if (!$this->shouldContinue($state)) {
        break;  // Confirmed stop
    }
    // Stop was prevented, continue loop
}
```

---

## Driver Boundary

Current `ToolCallingDriver::useTools()` does both inference and tool execution. Two options:

**Option A (minimal change):** Keep driver as-is, hook points are in AgentLoop wrapper:
- BeforeInference/AfterInference hooks wrap the inference call
- Tool hooks wrap each tool execution inside the step processor

**Option B (cleaner):** Split driver interface:
```php
interface InferenceDriver
{
    public function infer(AgentState $state): InferenceResponse;
}

interface ToolExecutionDriver
{
    public function executeTools(AgentState $state, array $toolCalls): ToolExecutions;
}
```

**Recommendation:** Start with Option A to minimize breaking changes. Refactor driver interface in a follow-up.

---

## What Gets Deleted

- `HookContext` and all subclasses
- `HookOutcome` class
- `ContinuationCriteria` class
- Individual criterion classes
- `StopDecision` class

## What Gets Kept (Unchanged)

- `ContinuationEvaluation` - use existing `fromDecision()` API
- `ContinuationDecision` enum - use existing `canContinueWith()` API
- `ContinuationOutcome` - use existing `fromEvaluations()` (already calls `EvaluationProcessor`)
- `EvaluationProcessor` - keep as static utility class (used by `ContinuationOutcome`)
- `ErrorPolicy` class - use existing `evaluate()` API
- `ErrorHandlingDecision` enum - check directly with `===`
- `AgentErrorContextResolver` - update to also read `currentExecution->exception`
- `ToolExecution` - use `Result::failure()` for blocked tools
- `ToolUseDecision` class - used by existing `ToolExecutor` observer pattern
- `ToolCallBlockedException` - already exists
- All existing `ErrorType` cases

## What Gets Added

- `HookType::BeforeInference`, `HookType::AfterInference`, `HookType::OnError`
- `CurrentExecution` transient fields and methods (event-specific + step-level)
- Updated `HookMatcher` contract (accepts `AgentState` + `HookType`)
- Limit hooks: `StepsLimitHook`, `TimeLimitHook`, `TokenLimitHook`, `ToolCallPresenceHook`, `ErrorPolicyHook`
- `HookStack` registration order tracking for stable execution

---

## StepRecorder Update

Current `StepRecorder` uses `ContinuationCriteria::evaluateAll()`. Update to use accumulated evaluations:

```php
final readonly class StepRecorder
{
    public function __construct(
        private CanEmitAgentEvents $eventEmitter,
    ) {}

    public function record(CurrentExecution $execution, AgentState $state, AgentStep $step): AgentState
    {
        $transitionState = $state->withNewStepRecorded($step);

        // Use outcome already computed by hooks (stored in currentExecution)
        $outcome = $execution->continuationOutcome ?? ContinuationOutcome::empty();
        $this->eventEmitter->continuationEvaluated($transitionState, $outcome);

        $stepExecution = new StepExecution(
            step: $step,
            outcome: $outcome,
            startedAt: $execution->startedAt,
            completedAt: new DateTimeImmutable(),
            stepNumber: $execution->stepNumber,
            id: $step->id(),
        );

        $nextState = $transitionState->withStepExecutionRecorded($stepExecution);
        $this->eventEmitter->stateUpdated($nextState);

        return $nextState;
    }
}
```

**Key change:** No longer depends on `ContinuationCriteria`. Outcome comes from hook evaluations already stored in `currentExecution->continuationOutcome`.
