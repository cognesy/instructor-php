# Migration Plan: Hook-Only Flow Control

**Date:** 2026-01-28 (rev5 - fully validated)

---

## Phase 0: Safety Constraints

Before any changes:
- Confirm no `AgentState` inside any context/outcome class
- Ensure no new recursion or cyclic references
- Review all places that embed state objects
- Verify existing APIs work as expected:
  - `ContinuationEvaluation::fromDecision()` ✓
  - `ContinuationDecision::canContinueWith()` ✓
  - `ContinuationOutcome::fromEvaluations()` ✓ (uses EvaluationProcessor)
  - `ErrorPolicy::evaluate()` → `ErrorHandlingDecision` ✓
  - `ToolExecutor::useTool()` with observer pattern ✓
  - `ToolCallBlockedException` already exists ✓
  - `CompositeMatcher::and()` / `::or()` factory methods ✓

---

## Phase 1: Data Model Changes

### 1.1 Extend CurrentExecution

**File:** `Core/Data/CurrentExecution.php`

Add event-specific AND step-level transient fields (not serialized).

**Important:** Use named arguments in all `withX()` methods to avoid positional arg errors.

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

    // ALL withX() methods use named arguments to avoid order mistakes
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

    public function withCurrentToolExecution(?ToolExecution $e): self
    {
        return new self(
            stepNumber: $this->stepNumber,
            startedAt: $this->startedAt,
            id: $this->id,
            currentToolCall: $this->currentToolCall,
            currentToolExecution: $e,
            inferenceMessages: $this->inferenceMessages,
            inferenceResponse: $this->inferenceResponse,
            exception: $this->exception,
            toolExecutions: $this->toolExecutions,
            outputMessages: $this->outputMessages,
            evaluations: $this->evaluations,
            continuationOutcome: $this->continuationOutcome,
        );
    }

    public function withInferenceMessages(?Messages $m): self
    {
        return new self(
            stepNumber: $this->stepNumber,
            startedAt: $this->startedAt,
            id: $this->id,
            currentToolCall: $this->currentToolCall,
            currentToolExecution: $this->currentToolExecution,
            inferenceMessages: $m,
            inferenceResponse: $this->inferenceResponse,
            exception: $this->exception,
            toolExecutions: $this->toolExecutions,
            outputMessages: $this->outputMessages,
            evaluations: $this->evaluations,
            continuationOutcome: $this->continuationOutcome,
        );
    }

    public function withInferenceResponse(?InferenceResponse $r): self
    {
        return new self(
            stepNumber: $this->stepNumber,
            startedAt: $this->startedAt,
            id: $this->id,
            currentToolCall: $this->currentToolCall,
            currentToolExecution: $this->currentToolExecution,
            inferenceMessages: $this->inferenceMessages,
            inferenceResponse: $r,
            exception: $this->exception,
            toolExecutions: $this->toolExecutions,
            outputMessages: $this->outputMessages,
            evaluations: $this->evaluations,
            continuationOutcome: $this->continuationOutcome,
        );
    }

    public function withException(?AgentException $e): self
    {
        return new self(
            stepNumber: $this->stepNumber,
            startedAt: $this->startedAt,
            id: $this->id,
            currentToolCall: $this->currentToolCall,
            currentToolExecution: $this->currentToolExecution,
            inferenceMessages: $this->inferenceMessages,
            inferenceResponse: $this->inferenceResponse,
            exception: $e,
            toolExecutions: $this->toolExecutions,
            outputMessages: $this->outputMessages,
            evaluations: $this->evaluations,
            continuationOutcome: $this->continuationOutcome,
        );
    }

    public function withToolExecutions(?ToolExecutions $t): self
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
            toolExecutions: $t,
            outputMessages: $this->outputMessages,
            evaluations: $this->evaluations,
            continuationOutcome: $this->continuationOutcome,
        );
    }

    public function withOutputMessages(?Messages $m): self
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
            outputMessages: $m,
            evaluations: $this->evaluations,
            continuationOutcome: $this->continuationOutcome,
        );
    }

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

    public function evaluations(): array
    {
        return $this->evaluations;
    }

    public function withClearedEvaluations(): self
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
            evaluations: [],
            continuationOutcome: $this->continuationOutcome,
        );
    }

    public function withContinuationOutcome(?ContinuationOutcome $o): self
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
            evaluations: $this->evaluations,
            continuationOutcome: $o,
        );
    }

    public function withClearedContinuationOutcome(): self
    {
        return $this->withContinuationOutcome(null);
    }

    public function withClearedEventData(): self
    {
        return new self(
            stepNumber: $this->stepNumber,
            startedAt: $this->startedAt,
            id: $this->id,
            currentToolCall: null,
            currentToolExecution: null,
            inferenceMessages: null,
            inferenceResponse: null,
            exception: null,
            toolExecutions: $this->toolExecutions,  // Keep step-level data
            outputMessages: $this->outputMessages,  // Keep step-level data
            evaluations: $this->evaluations,
            continuationOutcome: $this->continuationOutcome,
        );
    }

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

    public static function fromArray(array $data): self
    {
        return new self(
            stepNumber: (int)($data['stepNumber'] ?? 0),
            startedAt: isset($data['startedAt']) ? new DateTimeImmutable($data['startedAt']) : new DateTimeImmutable(),
            id: $data['id'] ?? '',
        );
    }
}
```

### 1.2 Add AgentState Helpers

**File:** `Core/Data/AgentState.php`

```php
public function withEvaluation(ContinuationEvaluation $evaluation): self
{
    $execution = $this->currentExecution();
    if ($execution === null) {
        return $this;
    }
    return $this->withCurrentExecution(
        $execution->withEvaluation($evaluation)
    );
}

public function withContinuationOutcome(ContinuationOutcome $outcome): self
{
    $execution = $this->currentExecution();
    if ($execution === null) {
        return $this;
    }
    return $this->withCurrentExecution(
        $execution->withContinuationOutcome($outcome)
    );
}

// UPDATE existing method to check currentExecution first
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

### 1.3 Add ContinuationOutcome::fromEvaluations()

**File:** `Core/Continuation/Data/ContinuationOutcome.php`

Uses existing `ContinuationDecision::canContinueWith()`:

```php
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
    $targetDecisions = $shouldContinue
        ? [ContinuationDecision::RequestContinuation, ContinuationDecision::AllowContinuation]
        : [ContinuationDecision::ForbidContinuation, ContinuationDecision::AllowStop];

    foreach ($targetDecisions as $target) {
        foreach ($evaluations as $eval) {
            if ($eval->decision === $target) {
                return $eval;
            }
        }
    }

    return $evaluations[0] ?? null;
}
```

---

## Phase 2: Hook System Updates

### 2.1 Update Hook Interface

**File:** `AgentHooks/Contracts/Hook.php`

```php
interface Hook
{
    public function handle(AgentState $state, callable $next): AgentState;
}
```

### 2.2 Update HookMatcher Contract

**File:** `AgentHooks/Contracts/HookMatcher.php`

```php
interface HookMatcher
{
    public function matches(AgentState $state, HookType $type): bool;
}
```

### 2.3 Update All Matchers

**Files:** All matcher classes

```php
// EventTypeMatcher
final class EventTypeMatcher implements HookMatcher
{
    public function __construct(private HookType $targetType) {}

    public function matches(AgentState $state, HookType $type): bool
    {
        return $type === $this->targetType;
    }
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
        if ($execution->currentToolCall !== null) {
            return fnmatch($this->pattern, $execution->currentToolCall->name());
        }

        // Fallback to currentToolExecution
        if ($execution->currentToolExecution !== null) {
            return fnmatch($this->pattern, $execution->currentToolExecution->name());
        }

        return false;
    }
}

// CompositeMatcher
final class CompositeMatcher implements HookMatcher
{
    public function __construct(private array $matchers) {}

    public function matches(AgentState $state, HookType $type): bool
    {
        foreach ($this->matchers as $matcher) {
            if (!$matcher->matches($state, $type)) {
                return false;
            }
        }
        return true;
    }
}
```

### 2.4 Update HookType Enum

**File:** `AgentHooks/Enums/HookType.php`

```php
enum HookType: string
{
    case ExecutionStart = 'execution_start';
    case ExecutionEnd = 'execution_end';
    case BeforeStep = 'before_step';
    case AfterStep = 'after_step';
    case BeforeInference = 'before_inference';  // NEW
    case AfterInference = 'after_inference';    // NEW
    case PreToolUse = 'pre_tool_use';
    case PostToolUse = 'post_tool_use';
    case OnError = 'on_error';                  // NEW
    case Stop = 'stop';
    case SubagentStop = 'subagent_stop';

    public function isToolEvent(): bool
    {
        return $this === self::PreToolUse || $this === self::PostToolUse;
    }

    public function isInferenceEvent(): bool
    {
        return $this === self::BeforeInference || $this === self::AfterInference;
    }
}
```

### 2.5 Update HookStack

**File:** `AgentHooks/HookStack.php`

Store hooks with EventTypeMatcher injected at registration. Use `CompositeMatcher::and()` (existing API).

**Important:** Track registration order for stable sorting (FIFO for equal priority).

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

        // Stable sort: priority DESC, then registration order ASC
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
        $matchingHooks = array_filter(
            $this->hooks,
            fn($entry) => $entry['matcher']->matches($state, $type)
        );

        if (empty($matchingHooks)) {
            return $state;
        }

        $chain = fn(AgentState $s) => $s;

        foreach (array_reverse($matchingHooks) as ['hook' => $hook]) {
            $next = $chain;
            $chain = fn(AgentState $s) => $hook->handle($s, $next);
        }

        return $chain($state);
    }
}
```

### 2.6 Update CallableHook

**File:** `AgentHooks/CallableHook.php`

```php
final class CallableHook implements Hook
{
    private Closure $callback;

    public function __construct(callable $callback)
    {
        $this->callback = $this->normalizeCallback($callback);
    }

    private function normalizeCallback(callable $callback): Closure
    {
        $reflection = new ReflectionFunction(Closure::fromCallable($callback));
        $paramCount = $reflection->getNumberOfParameters();

        return match ($paramCount) {
            1 => fn(AgentState $state, callable $next) => $callback($state) ?? $next($state),
            2 => Closure::fromCallable($callback),
            default => throw new InvalidArgumentException(
                'Hook callback must accept 1 or 2 parameters'
            ),
        };
    }

    public function handle(AgentState $state, callable $next): AgentState
    {
        return ($this->callback)($state, $next);
    }
}
```

### 2.7 Delete HookContext Classes

Delete entirely:
- `AgentHooks/Data/HookContext.php`
- `AgentHooks/Data/*HookContext.php` (all subclasses)

### 2.8 Delete HookOutcome

Delete: `AgentHooks/Data/HookOutcome.php`

---

## Phase 3: Delete ContinuationCriteria System

### 3.1 Delete Classes

- `Core/Continuation/ContinuationCriteria.php`
- `Core/Continuation/EvaluationProcessor.php`
- `Core/Continuation/Criteria/*.php` (all)

### 3.2 Create Replacement Hooks

**New directory:** `AgentHooks/Limits/`

```php
// StepsLimitHook.php
final class StepsLimitHook implements Hook
{
    public function __construct(private int $maxSteps) {}

    public function handle(AgentState $state, callable $next): AgentState
    {
        if ($state->stepCount() >= $this->maxSteps) {
            $state = $state->withEvaluation(
                ContinuationEvaluation::fromDecision(
                    self::class,
                    ContinuationDecision::ForbidContinuation,
                    StopReason::StepsLimitReached
                )
            );
        }
        return $next($state);
    }
}

// TimeLimitHook.php
final class TimeLimitHook implements Hook
{
    private ?DateTimeImmutable $startedAt = null;

    public function __construct(private int $maxSeconds) {}

    public function handle(AgentState $state, callable $next): AgentState
    {
        $this->startedAt ??= new DateTimeImmutable();

        $elapsed = (new DateTimeImmutable())->getTimestamp() - $this->startedAt->getTimestamp();
        if ($elapsed >= $this->maxSeconds) {
            $state = $state->withEvaluation(
                ContinuationEvaluation::fromDecision(
                    self::class,
                    ContinuationDecision::ForbidContinuation,
                    StopReason::TimeLimitReached
                )
            );
        }
        return $next($state);
    }
}

// TokenLimitHook.php
final class TokenLimitHook implements Hook
{
    public function __construct(private int $maxTokens) {}

    public function handle(AgentState $state, callable $next): AgentState
    {
        if ($state->usage()->total() >= $this->maxTokens) {
            $state = $state->withEvaluation(
                ContinuationEvaluation::fromDecision(
                    self::class,
                    ContinuationDecision::ForbidContinuation,
                    StopReason::TokenLimitReached
                )
            );
        }
        return $next($state);
    }
}

// ToolCallPresenceHook.php
final class ToolCallPresenceHook implements Hook
{
    public function handle(AgentState $state, callable $next): AgentState
    {
        $step = $state->currentStep();
        if ($step !== null && !$step->hasToolCalls()) {
            $state = $state->withEvaluation(
                ContinuationEvaluation::fromDecision(
                    self::class,
                    ContinuationDecision::AllowStop,
                    StopReason::Completed
                )
            );
        }
        return $next($state);
    }
}

// ErrorPolicyHook.php - uses correct APIs
// NOTE: Requires AgentErrorContextResolver update to read currentExecution->exception
final class ErrorPolicyHook implements Hook
{
    public function __construct(
        private ErrorPolicy $policy,
        private AgentErrorContextResolver $resolver,
    ) {}

    public function handle(AgentState $state, callable $next): AgentState
    {
        // resolve() now reads from both currentStep() AND currentExecution->exception
        $errorContext = $this->resolver->resolve($state);

        if ($errorContext->consecutiveFailures === 0) {
            return $next($state);
        }

        $decision = $this->policy->evaluate($errorContext);

        // Check enum directly
        if ($decision === ErrorHandlingDecision::Stop) {
            $state = $state->withEvaluation(
                ContinuationEvaluation::fromDecision(
                    self::class,
                    ContinuationDecision::ForbidContinuation,
                    StopReason::ErrorForbade
                )
            );
        }

        return $next($state);
    }
}
```

### 3.4 Update AgentErrorContextResolver

**File:** `Core/Errors/AgentErrorContextResolver.php`

Extend to also read `currentExecution->exception`:

```php
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
```

### 3.3 Update AgentBuilder

```php
public function withMaxSteps(int $max): self
{
    return $this->addHook(HookType::BeforeStep, new StepsLimitHook($max), priority: 100);
}

public function withMaxDuration(int $seconds): self
{
    return $this->addHook(HookType::BeforeStep, new TimeLimitHook($seconds), priority: 100);
}

public function withMaxTokens(int $tokens): self
{
    return $this->addHook(HookType::BeforeStep, new TokenLimitHook($tokens), priority: 100);
}

public function withErrorPolicy(ErrorPolicy $policy): self
{
    $resolver = new AgentErrorContextResolver();
    return $this->addHook(HookType::OnError, new ErrorPolicyHook($policy, $resolver), priority: 100);
}

public function addHook(HookType $type, Hook|callable $hook, int $priority = 0, ?HookMatcher $matcher = null): self
{
    $hookInstance = $hook instanceof Hook ? $hook : new CallableHook($hook);
    $this->hookStack->add($type, $hookInstance, $priority, $matcher);
    return $this;
}
```

---

## Phase 4: AgentLoop Integration

### 4.1 Core Loop Methods

```php
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

private function shouldContinue(AgentState $state): bool
{
    $outcome = $state->continuationOutcome();
    return $outcome === null || $outcome->shouldContinue();
}

private function clearEventData(AgentState $state): AgentState
{
    $execution = $state->currentExecution();
    if ($execution === null) {
        return $state;
    }
    return $state->withCurrentExecution($execution->withClearedEventData());
}

// NEW: Clear outcome at start of each step to prevent stale outcomes
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

// NEW: Populate step-level data for AfterStep hooks
private function populateStepLevelData(AgentState $state, ToolExecutions $toolExecutions, Messages $outputMessages): AgentState
{
    $execution = $state->currentExecution();
    if ($execution === null) {
        return $state;
    }
    return $state->withCurrentExecution(
        $execution
            ->withToolExecutions($toolExecutions)
            ->withOutputMessages($outputMessages)
    );
}
```

### 4.2 Error Handling

```php
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
    // Store exception
    $state = $state->withCurrentExecution(
        $state->currentExecution()->withException($exception)
    );

    // Run OnError hooks
    $state = $this->hookStack->process(HookType::OnError, $state);
    $state = $this->aggregateAndClearEvaluations($state);

    // Clear exception
    $state = $state->withCurrentExecution(
        $state->currentExecution()->withException(null)
    );

    return $state;
}
```

### 4.3 Stop Hook Lifecycle

**Important semantics:** Stop hooks can only prevent stop when caused by `AllowStop` (work driver finished). They **cannot** override `ForbidContinuation` (guard forbids like step limit).

```php
private function checkStopWithHooks(AgentState $state): bool
{
    if ($this->shouldContinue($state)) {
        return false;  // Not stopping
    }

    $outcome = $state->continuationOutcome();

    // Only allow stop prevention if stopped by AllowStop (not ForbidContinuation)
    $canPreventStop = $outcome !== null
        && !$outcome->shouldContinue()
        && $outcome->getForbiddingCriterion() === null;  // No forbid present

    if (!$canPreventStop) {
        // Guard forbade - stop hooks run for observation only
        $this->hookStack->process(HookType::Stop, $state);
        return true;  // Confirmed stop (can't change outcome)
    }

    // AllowStop - hooks can append RequestContinuation to prevent
    $state = $this->hookStack->process(HookType::Stop, $state);
    $state = $this->aggregateAndClearEvaluations($state);

    return !$this->shouldContinue($state);  // Re-check after hooks
}

### 4.4 Step Finalization with StepRecorder

Update step finalization to finalize BEFORE AfterStep hooks so hooks have access:

```php
private function finalizeStep(AgentState $state, AgentStep $step): AgentState
{
    // Populate step-level data for AfterStep hooks
    $state = $this->populateStepLevelData(
        $state,
        $step->toolExecutions(),
        $step->outputMessages()
    );

    // Run BeforeStep evaluation hooks (limits, etc.)
    $state = $this->hookStack->process(HookType::AfterStep, $state);
    $state = $this->aggregateAndClearEvaluations($state);

    // Record step with outcome from hooks
    $state = $this->stepRecorder->record(
        $state->currentExecution(),
        $state,
        $step
    );

    return $state;
}
```

---

## Phase 5: Tool Blocking

### 5.1 ToolCallBlockedException Already Exists

**File:** `Core/Exceptions/ToolCallBlockedException.php` - already exists, no changes needed.

### 5.2 Use Existing ToolExecutor Observer Pattern

The existing `ToolExecutor::useTool()` already:
1. Calls `observer->onBeforeToolUse()` returning `ToolUseDecision`
2. Throws `ToolCallBlockedException` if blocked
3. Calls `observer->onAfterToolUse()` after execution

**Strategy:** Bridge HookStack to existing observer interface.

```php
// HookStackObserver bridges HookStack to CanObserveAgentLifecycle
final class HookStackObserver implements CanObserveAgentLifecycle
{
    private AgentState $state;

    public function __construct(
        private HookStack $hookStack,
    ) {}

    public function setState(AgentState $state): void
    {
        $this->state = $state;
    }

    public function state(): AgentState
    {
        return $this->state;
    }

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

        return $this->state->currentExecution()?->currentToolExecution ?? $execution;
    }

    // ... other lifecycle methods delegating to hookStack ...
}
```

### 5.3 Record Blocked Tools for ErrorPolicy

Blocked tools should feed into ErrorPolicy. Record failures into the step:

```php
// In loop, after tool execution
foreach ($toolExecutions as $execution) {
    if ($execution->result()->isFailure()) {
        $step = $step->withError($execution->result()->error());
    }
}
// This ensures ErrorPolicy can see tool failures including blocks
```

---

## Phase 6: Cleanup

### 6.1 Delete Decision Classes

- `Core/Decisions/StopDecision.php` - DELETE
- Keep `Core/Decisions/ToolUseDecision.php` - used by existing ToolExecutor

### 6.2 Update CanObserveAgentLifecycle

**File:** `Core/Lifecycle/CanObserveAgentLifecycle.php`

All methods return `AgentState`:

```php
interface CanObserveAgentLifecycle
{
    public function onExecutionStart(AgentState $state): AgentState;
    public function onExecutionEnd(AgentState $state): AgentState;
    public function onBeforeStep(AgentState $state): AgentState;
    public function onAfterStep(AgentState $state): AgentState;
    public function onBeforeInference(AgentState $state): AgentState;
    public function onAfterInference(AgentState $state): AgentState;
    public function onBeforeToolUse(AgentState $state): AgentState;
    public function onAfterToolUse(AgentState $state): AgentState;
    public function onError(AgentState $state): AgentState;
}
```

---

## Files Summary

**Delete:**
- `AgentHooks/Data/*HookContext.php`
- `AgentHooks/Data/HookOutcome.php`
- `Core/Continuation/ContinuationCriteria.php`
- `Core/Continuation/Criteria/*.php`
- `Core/Decisions/StopDecision.php`

**Keep (unchanged or with minor updates):**
- `Core/Continuation/EvaluationProcessor.php` - static utility class, keep as-is
- `Core/Continuation/Data/ContinuationOutcome.php` - already has `fromEvaluations()` using EvaluationProcessor
- `Core/Decisions/ToolUseDecision.php` - used by existing ToolExecutor
- `Core/Exceptions/ToolCallBlockedException.php` - already exists

**Create:**
- `AgentHooks/Limits/StepsLimitHook.php`
- `AgentHooks/Limits/TimeLimitHook.php`
- `AgentHooks/Limits/TokenLimitHook.php`
- `AgentHooks/Limits/ToolCallPresenceHook.php`
- `AgentHooks/Limits/ErrorPolicyHook.php`
- `AgentHooks/HookStackObserver.php` - bridges HookStack to CanObserveAgentLifecycle

**Modify:**
- `Core/Data/CurrentExecution.php` - event-specific + step-level transient fields, named args, not serialized
- `Core/Data/AgentState.php` - withEvaluation(), withContinuationOutcome(), update continuationOutcome() to check currentExecution first
- `Core/Errors/AgentErrorContextResolver.php` - also read currentExecution->exception
- `AgentHooks/Contracts/Hook.php` - new signature
- `AgentHooks/Contracts/HookMatcher.php` - new signature (AgentState + HookType)
- `AgentHooks/Matchers/*.php` - update to new signature
- `AgentHooks/Matchers/CompositeMatcher.php` - use existing ::and() / ::or() API
- `AgentHooks/HookStack.php` - inject EventTypeMatcher at registration, track registration order for stable sort
- `AgentHooks/CallableHook.php` - simplified
- `AgentHooks/Enums/HookType.php` - add BeforeInference, AfterInference, OnError
- `AgentBuilder.php` - update addHook() and convenience methods
- `Core/Lifecycle/StepRecorder.php` - use accumulated evaluations from currentExecution instead of ContinuationCriteria
- `AgentLoop.php` - hook-only flow with aggregation, outcome clearing, step-level data population
- `Core/Lifecycle/CanObserveAgentLifecycle.php` - consistent signatures

---

## Success Criteria

- [ ] Uses existing `ContinuationEvaluation::fromDecision()` API
- [ ] Uses existing `ContinuationDecision::canContinueWith()` API (via EvaluationProcessor)
- [ ] Uses existing `ContinuationOutcome::fromEvaluations()` API (keeps EvaluationProcessor)
- [ ] Uses existing `ErrorPolicy::evaluate()` returning `ErrorHandlingDecision` enum
- [ ] Uses existing `AgentErrorContextResolver::resolve($state)` (extended to also read currentExecution->exception)
- [ ] Uses existing `ToolExecutor::useTool()` with observer pattern
- [ ] Uses existing `ToolCallBlockedException` class
- [ ] Uses existing `CompositeMatcher::and()` API (not constructor)
- [ ] Tool blocking feeds into ErrorPolicy via step error recording
- [ ] Evaluations cleared after aggregation (no re-processing)
- [ ] Continuation outcome cleared at start of each step (no stale outcomes)
- [ ] Transient fields excluded from serialization
- [ ] Step-level data populated before AfterStep hooks run
- [ ] OnError lifecycle defined with exception capture/clear
- [ ] Stop hooks can only prevent AllowStop (not ForbidContinuation)
- [ ] ToolNameMatcher works for both PreToolUse and PostToolUse
- [ ] HookStack filters by event type via injected EventTypeMatcher
- [ ] HookStack preserves registration order for stable execution (FIFO on priority tie)
- [ ] AgentState::continuationOutcome() checks currentExecution first
- [ ] All withX() methods use named arguments to avoid order mistakes
- [ ] All tests pass
