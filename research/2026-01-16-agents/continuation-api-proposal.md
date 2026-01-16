# Continuation + Error Policy API Proposal

## Goals
- Provide a concrete API for continuation decision tracing and error policy control.
- Preserve default behavior while enabling richer diagnostics and configurable error handling.
- Provide clear migration steps for existing users.

## Proposed Types

### ContinuationDecision (existing)
Continue using the current enum:
- `ForbidContinuation`
- `AllowContinuation`
- `RequestContinuation`
- `AllowStop`

### StopReason (new)
Standardized enum for UI consumption - explains why agent stopped:
```php
enum StopReason: string
{
    case Completed = 'completed';              // No more work requested (natural completion)
    case StepsLimitReached = 'steps_limit';    // Maximum steps exceeded
    case TokenLimitReached = 'token_limit';    // Maximum tokens exceeded
    case TimeLimitReached = 'time_limit';      // Maximum execution time exceeded
    case RetryLimitReached = 'retry_limit';    // Maximum retries after errors exceeded
    case ErrorForbade = 'error';               // Error policy forbade continuation
    case FinishReasonReceived = 'finish_reason'; // LLM returned terminal finish reason
    case GuardForbade = 'guard';               // Custom guard criterion forbade
    case UserRequested = 'user_requested';     // External stop request
}
```

### CanExplainContinuation (new, optional)
```php
interface CanExplainContinuation
{
    public function explain(object $state): ContinuationEvaluation;
}
```

### ContinuationEvaluation (new)
```php
final readonly class ContinuationEvaluation
{
    public function __construct(
        public string $criterionClass,
        public ContinuationDecision $decision,
        public string $reason,
        public array $context = [],
    ) {}

    public static function fromDecision(
        string $criterionClass,
        ContinuationDecision $decision,
    ): self {
        return new self(
            criterionClass: $criterionClass,
            decision: $decision,
            reason: self::defaultReason($criterionClass, $decision),
        );
    }

    private static function defaultReason(string $class, ContinuationDecision $decision): string {
        $shortName = substr(strrchr($class, '\\') ?: $class, 1);
        return match ($decision) {
            ContinuationDecision::ForbidContinuation => "{$shortName} forbade continuation",
            ContinuationDecision::RequestContinuation => "{$shortName} requested continuation",
            ContinuationDecision::AllowContinuation => "{$shortName} permits continuation",
            ContinuationDecision::AllowStop => "{$shortName} allows stop",
        };
    }
}
```

### ContinuationOutcome (new)
```php
final readonly class ContinuationOutcome
{
    /**
     * @param ContinuationDecision $decision Final aggregate decision
     * @param bool $shouldContinue Convenience boolean
     * @param string $resolvedBy Class name of criterion that determined outcome
     * @param StopReason $stopReason Standardized reason for UI (only meaningful when !shouldContinue)
     * @param list<ContinuationEvaluation> $evaluations Full trace of all criterion evaluations
     */
    public function __construct(
        public ContinuationDecision $decision,
        public bool $shouldContinue,
        public string $resolvedBy,
        public StopReason $stopReason,
        public array $evaluations,
    ) {}

    public function getEvaluationFor(string $criterionClass): ?ContinuationEvaluation {
        foreach ($this->evaluations as $eval) {
            if ($eval->criterionClass === $criterionClass) {
                return $eval;
            }
        }
        return null;
    }

    public function getForbiddingCriterion(): ?string {
        foreach ($this->evaluations as $eval) {
            if ($eval->decision === ContinuationDecision::ForbidContinuation) {
                return $eval->criterionClass;
            }
        }
        return null;
    }

    public function toArray(): array {
        return [
            'decision' => $this->decision->value,
            'shouldContinue' => $this->shouldContinue,
            'resolvedBy' => $this->resolvedBy,
            'stopReason' => $this->stopReason->value,
            'evaluations' => array_map(fn($e) => [
                'criterion' => $e->criterionClass,
                'decision' => $e->decision->value,
                'reason' => $e->reason,
                'context' => $e->context,
            ], $this->evaluations),
        ];
    }
}
```

## ContinuationCriteria API Changes

### Add evaluate() (new)
```php
public function evaluate(object $state): ContinuationOutcome;
```

### Existing APIs (retained)
```php
public function canContinue(object $state): bool;
public function decide(object $state): ContinuationDecision;
```

Behavior:
- `canContinue()` delegates to `evaluate()` and returns `shouldContinue`.
- `decide()` delegates to `evaluate()` and returns `decision`.
- `evaluate()` aggregates `ContinuationEvaluation` records and resolves the final decision using existing priority logic.

### Implementation sketch
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

        // Track first forbidding criterion for resolvedBy
        if ($eval->decision === ContinuationDecision::ForbidContinuation && $resolvedBy === null) {
            $resolvedBy = $eval->criterionClass;
            $stopReason = $this->inferStopReason($eval);
        }
    }

    $decisions = array_map(fn($e) => $e->decision, $evaluations);
    $shouldContinue = ContinuationDecision::canContinueWith(...$decisions);

    // If continuing, find the requesting criterion
    if ($shouldContinue && $resolvedBy === null) {
        foreach ($evaluations as $eval) {
            if ($eval->decision === ContinuationDecision::RequestContinuation) {
                $resolvedBy = $eval->criterionClass;
                break;
            }
        }
    }

    return new ContinuationOutcome(
        decision: $shouldContinue
            ? ContinuationDecision::RequestContinuation
            : ContinuationDecision::AllowStop,
        shouldContinue: $shouldContinue,
        resolvedBy: $resolvedBy ?? 'aggregate',
        stopReason: $shouldContinue ? StopReason::Completed : $stopReason,
        evaluations: $evaluations,
    );
}

private function inferStopReason(ContinuationEvaluation $eval): StopReason {
    // Map criterion class to StopReason
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

## Error Policy API (new)

### ErrorType (new enum)
Classification of errors for policy decisions:
```php
enum ErrorType: string
{
    case Tool = 'tool';                 // Tool execution failed (timeout, invalid args, exception)
    case Model = 'model';               // LLM returned error or refused request
    case Validation = 'validation';     // Response validation/parsing failed
    case RateLimit = 'rate_limit';      // Provider rate limit hit (typically retryable)
    case Timeout = 'timeout';           // Request timed out
    case Unknown = 'unknown';           // Unclassified error
}
```

### ErrorHandlingDecision (enum)
```php
enum ErrorHandlingDecision: string
{
    case Stop = 'stop';       // Forbid continuation immediately
    case Retry = 'retry';     // Allow continuation (for retry attempts)
    case Ignore = 'ignore';   // Allow continuation (treat as non-error)
}
```

### ErrorContext (new)
Structured error information for policy evaluation:
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

### CanResolveErrorContext (new interface)
Replaces closure for better testability:
```php
interface CanResolveErrorContext
{
    public function resolve(object $state): ErrorContext;
}
```

Default implementation for Agent:
```php
final readonly class AgentErrorContextResolver implements CanResolveErrorContext
{
    public function resolve(object $state): ErrorContext {
        /** @var AgentState $state */
        $currentStep = $state->currentStep();
        if ($currentStep === null || !$currentStep->hasErrors()) {
            return ErrorContext::none();
        }

        $executions = $currentStep->toolExecutions();
        $consecutiveFailures = $this->countConsecutiveFailures($state);

        return new ErrorContext(
            type: $this->classifyError($currentStep, $executions),
            consecutiveFailures: $consecutiveFailures,
            totalFailures: $this->countTotalFailures($state),
            message: $executions->firstError()?->getMessage(),
            toolName: $executions->firstErrorToolName(),
        );
    }

    private function classifyError(AgentStep $step, ToolExecutions $executions): ErrorType {
        // Classification strategy:
        // 1. Check if it's a tool execution error
        if ($executions->hasErrors()) {
            $error = $executions->firstError();
            return match (true) {
                $error instanceof RateLimitException => ErrorType::RateLimit,
                $error instanceof TimeoutException => ErrorType::Timeout,
                default => ErrorType::Tool,
            };
        }

        // 2. Check inference response for model errors
        $response = $step->inferenceResponse();
        if ($response?->hasError()) {
            return ErrorType::Model;
        }

        // 3. Default to unknown
        return ErrorType::Unknown;
    }

    private function countConsecutiveFailures(AgentState $state): int {
        $count = 0;
        foreach (array_reverse($state->steps()->all()) as $step) {
            if (!$step->hasErrors()) {
                break;
            }
            $count++;
        }
        return $count;
    }

    private function countTotalFailures(AgentState $state): int {
        return count(array_filter(
            $state->steps()->all(),
            fn($step) => $step->hasErrors()
        ));
    }
}
```

### ErrorPolicy (rich object with named constructors)
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

    // === Named Constructors (Presets) ===

    /**
     * Default behavior: stop on any error (matches current ErrorPresenceCheck).
     */
    public static function stopOnAnyError(): self {
        return new self(
            onToolError: ErrorHandlingDecision::Stop,
            onModelError: ErrorHandlingDecision::Stop,
            onValidationError: ErrorHandlingDecision::Stop,
            onRateLimitError: ErrorHandlingDecision::Stop,
            onTimeoutError: ErrorHandlingDecision::Stop,
            onUnknownError: ErrorHandlingDecision::Stop,
            maxRetries: 0,
        );
    }

    /**
     * Retry tool errors up to maxRetries, stop on model errors.
     */
    public static function retryToolErrors(int $maxRetries = 3): self {
        return new self(
            onToolError: ErrorHandlingDecision::Retry,
            onModelError: ErrorHandlingDecision::Stop,
            onValidationError: ErrorHandlingDecision::Retry,
            onRateLimitError: ErrorHandlingDecision::Retry,
            onTimeoutError: ErrorHandlingDecision::Retry,
            onUnknownError: ErrorHandlingDecision::Stop,
            maxRetries: $maxRetries,
        );
    }

    /**
     * Ignore tool errors entirely, only stop on model errors.
     */
    public static function ignoreToolErrors(): self {
        return new self(
            onToolError: ErrorHandlingDecision::Ignore,
            onModelError: ErrorHandlingDecision::Stop,
            onValidationError: ErrorHandlingDecision::Ignore,
            onRateLimitError: ErrorHandlingDecision::Retry,
            onTimeoutError: ErrorHandlingDecision::Retry,
            onUnknownError: ErrorHandlingDecision::Ignore,
            maxRetries: 0,
        );
    }

    /**
     * Lenient policy: retry everything possible.
     */
    public static function retryAll(int $maxRetries = 5): self {
        return new self(
            onToolError: ErrorHandlingDecision::Retry,
            onModelError: ErrorHandlingDecision::Retry,
            onValidationError: ErrorHandlingDecision::Retry,
            onRateLimitError: ErrorHandlingDecision::Retry,
            onTimeoutError: ErrorHandlingDecision::Retry,
            onUnknownError: ErrorHandlingDecision::Retry,
            maxRetries: $maxRetries,
        );
    }

    // === Fluent Modifiers ===

    public function withMaxRetries(int $maxRetries): self {
        return new self(
            onToolError: $this->onToolError,
            onModelError: $this->onModelError,
            onValidationError: $this->onValidationError,
            onRateLimitError: $this->onRateLimitError,
            onTimeoutError: $this->onTimeoutError,
            onUnknownError: $this->onUnknownError,
            maxRetries: $maxRetries,
        );
    }

    public function withToolErrorHandling(ErrorHandlingDecision $decision): self {
        return new self(
            onToolError: $decision,
            onModelError: $this->onModelError,
            onValidationError: $this->onValidationError,
            onRateLimitError: $this->onRateLimitError,
            onTimeoutError: $this->onTimeoutError,
            onUnknownError: $this->onUnknownError,
            maxRetries: $this->maxRetries,
        );
    }

    // === Policy Evaluation ===

    public function evaluate(ErrorContext $context): ErrorHandlingDecision {
        if ($context->consecutiveFailures === 0) {
            return ErrorHandlingDecision::Ignore; // No error present
        }

        $decision = match ($context->type) {
            ErrorType::Tool => $this->onToolError,
            ErrorType::Model => $this->onModelError,
            ErrorType::Validation => $this->onValidationError,
            ErrorType::RateLimit => $this->onRateLimitError,
            ErrorType::Timeout => $this->onTimeoutError,
            ErrorType::Unknown => $this->onUnknownError,
        };

        // If policy says retry, check if we've exceeded max retries
        if ($decision === ErrorHandlingDecision::Retry) {
            if ($context->consecutiveFailures >= $this->maxRetries) {
                return ErrorHandlingDecision::Stop;
            }
        }

        return $decision;
    }
}
```

### ErrorPolicyCriterion (new)
**Replaces both `ErrorPresenceCheck` AND `RetryLimit`** with unified policy-driven logic:
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

    #[\Override]
    public function decide(object $state): ContinuationDecision {
        return $this->explain($state)->decision;
    }

    #[\Override]
    public function explain(object $state): ContinuationEvaluation {
        $context = $this->contextResolver->resolve($state);
        $handling = $this->policy->evaluate($context);

        $decision = match ($handling) {
            ErrorHandlingDecision::Stop => ContinuationDecision::ForbidContinuation,
            ErrorHandlingDecision::Retry => ContinuationDecision::AllowContinuation,
            ErrorHandlingDecision::Ignore => ContinuationDecision::AllowContinuation,
        };

        $reason = $this->buildReason($context, $handling);

        return new ContinuationEvaluation(
            criterionClass: self::class,
            decision: $decision,
            reason: $reason,
            context: [
                'errorType' => $context->type->value,
                'consecutiveFailures' => $context->consecutiveFailures,
                'totalFailures' => $context->totalFailures,
                'maxRetries' => $this->policy->maxRetries,
                'handling' => $handling->value,
                'toolName' => $context->toolName,
            ],
        );
    }

    private function buildReason(ErrorContext $context, ErrorHandlingDecision $handling): string {
        if ($context->consecutiveFailures === 0) {
            return 'No errors present';
        }

        $typeLabel = ucfirst($context->type->value);
        return match ($handling) {
            ErrorHandlingDecision::Stop => sprintf(
                '%s error after %d consecutive failures (max: %d)',
                $typeLabel,
                $context->consecutiveFailures,
                $this->policy->maxRetries
            ),
            ErrorHandlingDecision::Retry => sprintf(
                '%s error, retrying (%d/%d)',
                $typeLabel,
                $context->consecutiveFailures,
                $this->policy->maxRetries
            ),
            ErrorHandlingDecision::Ignore => sprintf(
                '%s error ignored by policy',
                $typeLabel
            ),
        };
    }
}
```

## AgentBuilder Integration

### New Builder API
```php
public function withErrorPolicy(ErrorPolicy $policy): self;
```

### Base Criteria Changes
**Remove** `ErrorPresenceCheck` and `RetryLimit` from base criteria.
**Add** `ErrorPolicyCriterion` with default `stopOnAnyError()` policy for backward compatibility.

```php
private function buildContinuationCriteria(): ContinuationCriteria {
    $baseCriteria = [
        // Hard limits (return ForbidContinuation when exceeded)
        new StepsLimit($this->maxSteps, static fn($state) => $state->stepCount()),
        new TokenUsageLimit($this->maxTokens, static fn($state) => $state->usage()->total()),
        new ExecutionTimeLimit($this->maxExecutionTime, static fn($state) => $state->startedAt(), null),
        new FinishReasonCheck($this->finishReasons, static fn($state) => $state->currentStep()?->finishReason()),

        // Error policy (replaces ErrorPresenceCheck + RetryLimit)
        ErrorPolicyCriterion::withPolicy($this->errorPolicy ?? ErrorPolicy::stopOnAnyError()),

        // Continue signal (returns RequestContinuation when tool calls present)
        new ToolCallPresenceCheck(
            static fn($state) => $state->stepCount() === 0 || ($state->currentStep()?->hasToolCalls() ?? false)
        ),
    ];

    return ContinuationCriteria::from(...$baseCriteria, ...$this->criteria);
}
```

## Event + State Exposure

### AgentState
Add optional tracking:
```php
public function lastContinuationOutcome(): ?ContinuationOutcome;

public function withContinuationOutcome(ContinuationOutcome $outcome): self;
```

### Events
Add new event:
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

## Migration Notes

### Behavior Compatibility
- Default `ErrorPolicy::stopOnAnyError()` matches current `ErrorPresenceCheck` behavior exactly.
- `canContinue()` behavior remains unchanged when called directly.
- Existing custom criteria continue to work without modification.

### Breaking Changes
- `ErrorPresenceCheck` removed from base criteria (replaced by `ErrorPolicyCriterion`)
- `RetryLimit` removed from base criteria (functionality merged into `ErrorPolicyCriterion`)

Users who explicitly added these criteria should migrate:
```php
// Before
->addContinuationCriteria(new ErrorPresenceCheck(...))
->addContinuationCriteria(new RetryLimit(3, ...))

// After
->withErrorPolicy(ErrorPolicy::retryToolErrors(3))
```

### Recommended Upgrade Steps
1. Add `evaluate()` to `ContinuationCriteria` and keep `canContinue()` as wrapper.
2. Introduce `CanExplainContinuation` for criteria that can provide reasons.
3. Add `ErrorPolicy` + `ErrorPolicyCriterion` and remove `ErrorPresenceCheck`/`RetryLimit` from base criteria.
4. Expose continuation outcome via events/state for observability.
5. Update documentation with troubleshooting guide for continuation issues.

## File Locations

| Type | Location |
|------|----------|
| `ContinuationEvaluation` | `packages/addons/src/StepByStep/Continuation/ContinuationEvaluation.php` |
| `ContinuationOutcome` | `packages/addons/src/StepByStep/Continuation/ContinuationOutcome.php` |
| `StopReason` | `packages/addons/src/StepByStep/Continuation/StopReason.php` |
| `CanExplainContinuation` | `packages/addons/src/StepByStep/Continuation/CanExplainContinuation.php` |
| `ErrorType` | `packages/addons/src/StepByStep/Continuation/ErrorType.php` |
| `ErrorContext` | `packages/addons/src/StepByStep/Continuation/ErrorContext.php` |
| `ErrorHandlingDecision` | `packages/addons/src/StepByStep/Continuation/ErrorHandlingDecision.php` |
| `ErrorPolicy` | `packages/addons/src/StepByStep/Continuation/ErrorPolicy.php` |
| `CanResolveErrorContext` | `packages/addons/src/StepByStep/Continuation/CanResolveErrorContext.php` |
| `ErrorPolicyCriterion` | `packages/addons/src/StepByStep/Continuation/Criteria/ErrorPolicyCriterion.php` |
| `AgentErrorContextResolver` | `packages/addons/src/Agent/Core/Continuation/AgentErrorContextResolver.php` |
| `ContinuationEvaluated` | `packages/addons/src/Agent/Events/ContinuationEvaluated.php` |

## Summary of Changes from Original Proposal

| Original | Updated |
|----------|---------|
| `ErrorPolicyMode` enum + granular settings | Named constructors on `ErrorPolicy` (clearer API) |
| String-based error types | `ErrorType` enum (type-safe) |
| No stop reason standardization | `StopReason` enum (UI-ready) |
| `ErrorPresenceCheck` + `RetryLimit` coexist | `ErrorPolicyCriterion` replaces both |
| `ContinuationTrace` wrapper class | Evaluations directly on `ContinuationOutcome` |
| Closure for error context | `CanResolveErrorContext` interface (testable) |
| Implicit error classification | Explicit `AgentErrorContextResolver` with strategy |
