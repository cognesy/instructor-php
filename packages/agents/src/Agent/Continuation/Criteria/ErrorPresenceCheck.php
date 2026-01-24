<?php declare(strict_types=1);

namespace Cognesy\Agents\Agent\Continuation\Criteria;

use Closure;
use Cognesy\Agents\Agent\Continuation\CanEvaluateContinuation;
use Cognesy\Agents\Agent\Continuation\ContinuationDecision;
use Cognesy\Agents\Agent\Continuation\ContinuationEvaluation;
use Cognesy\Agents\Agent\Continuation\StopReason;
use Cognesy\Agents\Agent\Data\AgentState;

/**
 * Guard: Forbids continuation when the current step reports execution errors.
 *
 * Returns ForbidContinuation when errors present (guard denial),
 * AllowContinuation otherwise (guard approval - permits continuation).
 */
final readonly class ErrorPresenceCheck implements CanEvaluateContinuation
{
    /** @var Closure(AgentState): bool */
    private Closure $hasErrorsResolver;

    /**
     * @param Closure(AgentState): bool $hasErrorsResolver
     */
    public function __construct(callable $hasErrorsResolver) {
        $this->hasErrorsResolver = Closure::fromCallable($hasErrorsResolver);
    }

    #[\Override]
    public function evaluate(AgentState $state): ContinuationEvaluation {
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
