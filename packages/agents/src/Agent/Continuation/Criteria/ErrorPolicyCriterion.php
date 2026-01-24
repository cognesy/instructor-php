<?php declare(strict_types=1);

namespace Cognesy\Agents\Agent\Continuation\Criteria;

use Cognesy\Agents\Agent\Continuation\AgentErrorContextResolver;
use Cognesy\Agents\Agent\Continuation\CanEvaluateContinuation;
use Cognesy\Agents\Agent\Continuation\ContinuationDecision;
use Cognesy\Agents\Agent\Continuation\ContinuationEvaluation;
use Cognesy\Agents\Agent\Continuation\StopReason;
use Cognesy\Agents\Agent\ErrorHandling\CanResolveErrorContext;
use Cognesy\Agents\Agent\ErrorHandling\ErrorContext;
use Cognesy\Agents\Agent\ErrorHandling\ErrorHandlingDecision;
use Cognesy\Agents\Agent\ErrorHandling\ErrorPolicy;
use Cognesy\Agents\Agent\Data\AgentState;

/**
 * Guard: Evaluates error policy and decides whether to continue.
 */
final readonly class ErrorPolicyCriterion implements CanEvaluateContinuation
{
    public function __construct(
        private ErrorPolicy $policy,
        private CanResolveErrorContext $contextResolver,
    ) {}

    public static function withPolicy(ErrorPolicy $policy): self {
        return new self($policy, new AgentErrorContextResolver());
    }

    #[\Override]
    public function evaluate(AgentState $state): ContinuationEvaluation {
        $context = $this->contextResolver->resolve($state);
        $handling = $this->policy->evaluate($context);

        $decision = match ($handling) {
            ErrorHandlingDecision::Stop => ContinuationDecision::ForbidContinuation,
            ErrorHandlingDecision::Retry => ContinuationDecision::AllowContinuation,
            ErrorHandlingDecision::Ignore => ContinuationDecision::AllowContinuation,
        };

        $reason = $this->buildReason($context, $handling);
        $stopReason = $decision === ContinuationDecision::ForbidContinuation
            ? StopReason::ErrorForbade
            : null;

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
            stopReason: $stopReason,
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
