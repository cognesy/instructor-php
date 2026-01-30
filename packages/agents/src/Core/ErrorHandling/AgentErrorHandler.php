<?php declare(strict_types=1);

namespace Cognesy\Agents\Core\ErrorHandling;

use Cognesy\Agents\Core\Continuation\AgentErrorContextResolver;
use Cognesy\Agents\Core\Continuation\Data\ContinuationEvaluation;
use Cognesy\Agents\Core\Continuation\Data\ContinuationOutcome;
use Cognesy\Agents\Core\Continuation\Enums\ContinuationDecision;
use Cognesy\Agents\Core\Continuation\Enums\StopReason;
use Cognesy\Agents\Core\Contracts\CanHandleAgentErrors;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Data\AgentStep;
use Cognesy\Agents\Core\Enums\AgentStatus;
use Cognesy\Agents\Core\ErrorHandling\Contracts\CanResolveErrorContext;
use Cognesy\Agents\Core\ErrorHandling\Data\ErrorContext;
use Cognesy\Agents\Core\ErrorHandling\Data\ErrorHandlingResult;
use Cognesy\Agents\Core\ErrorHandling\Enums\ErrorHandlingDecision;
use Cognesy\Agents\Core\Exceptions\AgentException;
use Throwable;

/**
 * Default error handler for agent execution.
 *
 * Uses ErrorPolicy to decide how to handle errors:
 * - Stop: Marks execution as failed
 * - Retry: Allows continuation
 * - Ignore: Allows continuation
 */
final class AgentErrorHandler implements CanHandleAgentErrors
{
    public function __construct(
        private readonly ErrorPolicy $policy,
        private readonly CanResolveErrorContext $contextResolver,
    ) {}

    public static function default(): self {
        return new self(
            policy: ErrorPolicy::stopOnAnyError(),
            contextResolver: new AgentErrorContextResolver(),
        );
    }

    public static function withPolicy(ErrorPolicy $policy): self {
        return new self(
            policy: $policy,
            contextResolver: new AgentErrorContextResolver(),
        );
    }

    #[\Override]
    public function handleError(Throwable $error, AgentState $state): ErrorHandlingResult {
        // Wrap error in AgentException
        $agentException = $error instanceof AgentException
            ? $error
            : AgentException::fromThrowable($error);

        // Create failure step
        $failureStep = AgentStep::failure(
            inputMessages: $state->messages(),
            error: $agentException,
        );

        // Create transition state with failure recorded (for error context resolution)
        $transitionState = $state
            ->withStatus(AgentStatus::Failed)
            ->withCurrentStep($failureStep);

        // Evaluate error policy
        $errorContext = $this->contextResolver->resolve($transitionState);
        $decision = $this->policy->evaluate($errorContext);

        // Build continuation outcome based on policy decision
        $outcome = $this->buildOutcome($decision, $errorContext);

        // Determine final status based on policy decision
        $finalStatus = match ($decision) {
            ErrorHandlingDecision::Stop => AgentStatus::Failed,
            ErrorHandlingDecision::Retry => AgentStatus::InProgress,
            ErrorHandlingDecision::Ignore => AgentStatus::InProgress,
        };

        return new ErrorHandlingResult(
            failureStep: $failureStep,
            outcome: $outcome,
            finalStatus: $finalStatus,
            exception: $agentException,
        );
    }

    private function buildOutcome(
        ErrorHandlingDecision $decision,
        ErrorContext $errorContext,
    ): ContinuationOutcome {
        $shouldContinue = match ($decision) {
            ErrorHandlingDecision::Stop => false,
            ErrorHandlingDecision::Retry => true,
            ErrorHandlingDecision::Ignore => true,
        };

        $continuationDecision = match ($decision) {
            ErrorHandlingDecision::Stop => ContinuationDecision::ForbidContinuation,
            ErrorHandlingDecision::Retry => ContinuationDecision::AllowContinuation,
            ErrorHandlingDecision::Ignore => ContinuationDecision::AllowContinuation,
        };

        $stopReason = $decision === ErrorHandlingDecision::Stop
            ? StopReason::ErrorForbade
            : null;

        $evaluation = new ContinuationEvaluation(
            criterionClass: self::class,
            decision: $continuationDecision,
            reason: $this->buildReason($decision, $errorContext),
            context: [
                'errorType' => $errorContext->type->value,
                'consecutiveFailures' => $errorContext->consecutiveFailures,
                'totalFailures' => $errorContext->totalFailures,
                'maxRetries' => $this->policy->maxRetries,
                'handling' => $decision->value,
                'toolName' => $errorContext->toolName,
                'message' => $errorContext->message,
            ],
            stopReason: $stopReason,
        );

        return new ContinuationOutcome(
            shouldContinue: $shouldContinue,
            evaluations: [$evaluation],
        );
    }

    private function buildReason(
        ErrorHandlingDecision $decision,
        ErrorContext $errorContext,
    ): string {
        $typeLabel = ucfirst($errorContext->type->value);

        return match ($decision) {
            ErrorHandlingDecision::Stop => sprintf(
                '%s error after %d consecutive failures (max: %d) - stopping',
                $typeLabel,
                $errorContext->consecutiveFailures,
                $this->policy->maxRetries
            ),
            ErrorHandlingDecision::Retry => sprintf(
                '%s error, retrying (%d/%d)',
                $typeLabel,
                $errorContext->consecutiveFailures,
                $this->policy->maxRetries
            ),
            ErrorHandlingDecision::Ignore => sprintf(
                '%s error ignored by policy - continuing',
                $typeLabel
            ),
        };
    }

}
