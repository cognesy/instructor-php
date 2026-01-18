<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\Continuation\Criteria;

use Cognesy\Addons\Agent\Core\Continuation\AgentErrorContextResolver;
use Cognesy\Addons\StepByStep\Continuation\CanDecideToContinue;
use Cognesy\Addons\StepByStep\Continuation\CanExplainContinuation;
use Cognesy\Addons\StepByStep\Continuation\CanProvideStopReason;
use Cognesy\Addons\StepByStep\Continuation\CanResolveErrorContext;
use Cognesy\Addons\StepByStep\Continuation\ContinuationDecision;
use Cognesy\Addons\StepByStep\Continuation\ContinuationEvaluation;
use Cognesy\Addons\StepByStep\Continuation\ErrorContext;
use Cognesy\Addons\StepByStep\Continuation\ErrorHandlingDecision;
use Cognesy\Addons\StepByStep\Continuation\ErrorPolicy;
use Cognesy\Addons\StepByStep\Continuation\StopReason;

/**
 * Guard: Evaluates error policy and decides whether to continue.
 *
 * @template TState of object
 * @implements CanDecideToContinue<TState>
 */
final readonly class ErrorPolicyCriterion implements CanDecideToContinue, CanExplainContinuation, CanProvideStopReason
{
    public function __construct(
        private ErrorPolicy $policy,
        private CanResolveErrorContext $contextResolver,
    ) {}

    public static function withPolicy(ErrorPolicy $policy): self {
        return new self($policy, new AgentErrorContextResolver());
    }

    /**
     * @param TState $state
     */
    #[\Override]
    public function decide(object $state): ContinuationDecision {
        return $this->explain($state)->decision;
    }

    /**
     * @param TState $state
     */
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
            stopReason: $this->stopReason($state, $decision),
        );
    }

    #[\Override]
    public function stopReason(object $state, ContinuationDecision $decision): ?StopReason {
        return match ($decision) {
            ContinuationDecision::ForbidContinuation => StopReason::ErrorForbade,
            default => null,
        };
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
