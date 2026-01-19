<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\Continuation\Criteria;

use Closure;
use Cognesy\Addons\StepByStep\Continuation\CanEvaluateContinuation;
use Cognesy\Addons\StepByStep\Continuation\ContinuationDecision;
use Cognesy\Addons\StepByStep\Continuation\ContinuationEvaluation;
use Cognesy\Addons\StepByStep\Continuation\StopReason;

/**
 * Guard: Forbids continuation when the current step reports execution errors.
 *
 * Returns ForbidContinuation when errors present (guard denial),
 * AllowContinuation otherwise (guard approval - permits continuation).
 *
 * @template TState of object
 * @implements CanEvaluateContinuation<TState>
 */
final readonly class ErrorPresenceCheck implements CanEvaluateContinuation
{
    /** @var Closure(TState): bool */
    private Closure $hasErrorsResolver;

    /**
     * @param Closure(TState): bool $hasErrorsResolver
     */
    public function __construct(callable $hasErrorsResolver) {
        $this->hasErrorsResolver = Closure::fromCallable($hasErrorsResolver);
    }

    /**
     * @param TState $state
     */
    #[\Override]
    public function evaluate(object $state): ContinuationEvaluation {
        /** @var TState $state */
        $hasErrors = ($this->hasErrorsResolver)($state);

        $decision = $hasErrors
            ? ContinuationDecision::ForbidContinuation
            : ContinuationDecision::AllowContinuation;

        $reason = $hasErrors
            ? 'Errors present, forbidding continuation'
            : 'No errors, allowing continuation';

        return new ContinuationEvaluation(
            criterionClass: self::class,
            decision: $decision,
            reason: $reason,
            context: ['hasErrors' => $hasErrors],
            stopReason: $hasErrors ? StopReason::ErrorForbade : null,
        );
    }
}
