<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentHooks\Hooks;

use Cognesy\Agents\AgentHooks\Contracts\Hook;
use Cognesy\Agents\AgentHooks\Enums\HookType;
use Cognesy\Agents\Core\Continuation\AgentErrorContextResolver;
use Cognesy\Agents\Core\Continuation\Data\ContinuationEvaluation;
use Cognesy\Agents\Core\Continuation\Enums\ContinuationDecision;
use Cognesy\Agents\Core\Continuation\Enums\StopReason;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\ErrorHandling\Data\ErrorContext;
use Cognesy\Agents\Core\ErrorHandling\Enums\ErrorHandlingDecision;
use Cognesy\Agents\Core\ErrorHandling\ErrorPolicy;

/**
 * Hook adapter for error policy continuation checks.
 */
final readonly class ErrorPolicyHook implements Hook
{
    private ErrorPolicy $policy;
    private AgentErrorContextResolver $contextResolver;

    public function __construct(ErrorPolicy $policy)
    {
        $this->policy = $policy;
        $this->contextResolver = new AgentErrorContextResolver();
    }

    #[\Override]
    public function appliesTo(): array
    {
        return [HookType::AfterStep];
    }

    #[\Override]
    public function process(AgentState $state, HookType $event): AgentState
    {
        $context = $this->contextResolver->resolve($state);
        $handling = $this->policy->evaluate($context);

        $decision = match ($handling) {
            ErrorHandlingDecision::Stop => ContinuationDecision::ForbidContinuation,
            ErrorHandlingDecision::Retry => ContinuationDecision::RequestContinuation,
            ErrorHandlingDecision::Ignore => ContinuationDecision::AllowContinuation,
        };

        $stopReason = $decision === ContinuationDecision::ForbidContinuation
            ? StopReason::ErrorForbade
            : null;

        $evaluation = new ContinuationEvaluation(
            criterionClass: self::class,
            decision: $decision,
            reason: $this->buildReason($context, $handling),
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

        return $state->withEvaluation($evaluation);
    }

    private function buildReason(ErrorContext $context, ErrorHandlingDecision $handling): string
    {
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
