<?php declare(strict_types=1);

namespace Cognesy\Agents\Hooks\Defaults;

use Cognesy\Agents\Core\Stop\StopReason;
use Cognesy\Agents\Core\Stop\StopSignal;
use Cognesy\Agents\Hooks\HookContext;
use Cognesy\Agents\Hooks\HookInterface;
use tmp\ErrorHandling\AgentErrorContextResolver;
use tmp\ErrorHandling\Data\ErrorContext;
use tmp\ErrorHandling\Enums\ErrorHandlingDecision;
use tmp\ErrorHandling\ErrorPolicy;

final readonly class ErrorPolicyHook implements HookInterface
{
    private AgentErrorContextResolver $contextResolver;

    public function __construct(
        private ErrorPolicy $policy,
    ) {
        $this->contextResolver = new AgentErrorContextResolver();
    }

    #[\Override]
    public function handle(HookContext $context): HookContext
    {
        $state = $context->state();
        $errorContext = $this->contextResolver->resolve($state);
        $handling = $this->policy->evaluate($errorContext);

        $nextState = match ($handling) {
            ErrorHandlingDecision::Stop => $state->withStopSignal(new StopSignal(
                reason: StopReason::ErrorForbade,
                message: $this->buildReason($errorContext, $handling),
                context: $this->contextData($errorContext, $handling),
                source: self::class,
            )),
            ErrorHandlingDecision::Retry => $state->withExecutionContinued(),
            ErrorHandlingDecision::Ignore => $state,
        };

        return $context->withState($nextState);
    }

    private function contextData(ErrorContext $context, ErrorHandlingDecision $handling): array
    {
        return [
            'errorType' => $context->type->value,
            'consecutiveFailures' => $context->consecutiveFailures,
            'totalFailures' => $context->totalFailures,
            'maxRetries' => $this->policy->maxRetries,
            'handling' => $handling->value,
            'toolName' => $context->toolName,
        ];
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
