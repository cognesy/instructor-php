<?php declare(strict_types=1);

namespace Cognesy\Agents\Agent\Continuation;

use Cognesy\Agents\Agent\Collections\ToolExecutions;
use Cognesy\Agents\Agent\Data\ToolExecution;
use Cognesy\Agents\Agent\Data\AgentState;
use Cognesy\Agents\Agent\Data\AgentStep;
use Cognesy\Agents\Agent\ErrorHandling\CanResolveErrorContext;
use Cognesy\Agents\Agent\ErrorHandling\ErrorContext;
use Cognesy\Agents\Agent\ErrorHandling\ErrorType;
use Cognesy\Http\Exceptions\TimeoutException;
use Cognesy\Polyglot\Inference\Exceptions\ProviderRateLimitException;
use Throwable;

final readonly class AgentErrorContextResolver implements CanResolveErrorContext
{
    #[\Override]
    public function resolve(object $state): ErrorContext {
        /** @var AgentState $state */
        $currentStep = $state->currentStep();
        if ($currentStep === null || !$currentStep->hasErrors()) {
            return ErrorContext::none();
        }

        $executions = $currentStep->toolExecutions();
        $errorExecution = $this->firstErrorExecution($executions);
        $error = $this->firstError($currentStep, $errorExecution);

        return new ErrorContext(
            type: $this->classifyError($currentStep, $executions, $error),
            consecutiveFailures: $this->countConsecutiveFailures($state),
            totalFailures: $this->countTotalFailures($state),
            message: $error?->getMessage(),
            toolName: $errorExecution?->toolCall()->name(),
        );
    }

    private function classifyError(
        AgentStep       $step,
        ToolExecutions $executions,
        ?Throwable      $error,
    ): ErrorType {
        if ($error instanceof ProviderRateLimitException) {
            return ErrorType::RateLimit;
        }
        if ($error instanceof TimeoutException) {
            return ErrorType::Timeout;
        }
        if ($executions->hasErrors()) {
            return ErrorType::Tool;
        }
        if ($step->hasErrors()) {
            return ErrorType::Model;
        }

        return ErrorType::Unknown;
    }

    private function firstErrorExecution(ToolExecutions $executions): ?ToolExecution {
        foreach ($executions->all() as $execution) {
            if ($execution->hasError()) {
                return $execution;
            }
        }
        return null;
    }

    private function firstError(AgentStep $step, ?ToolExecution $execution): ?Throwable {
        if ($execution !== null && $execution->hasError()) {
            return $execution->error();
        }

        return $step->errors()->first();
    }

    private function countConsecutiveFailures(AgentState $state): int {
        $count = 0;

        // Include current step if it has errors AND is not already in stepExecutions
        // (during error handling, currentStep is set before stepExecution is recorded)
        $currentStep = $state->currentStep();
        $lastResultStep = $state->stepExecutions()->last()?->step;
        $currentStepAlreadyCounted = false;
        if ($currentStep !== null && $lastResultStep !== null) {
            $currentStepAlreadyCounted = $currentStep->id() === $lastResultStep->id();
        }

        if ($currentStep !== null && $currentStep->hasErrors() && !$currentStepAlreadyCounted) {
            $count++;
        }

        // Count consecutive failures from recorded steps (most recent first)
        foreach (array_reverse($state->steps()->all()) as $step) {
            if (!$step->hasErrors()) {
                break;
            }
            $count++;
        }
        return $count;
    }

    private function countTotalFailures(AgentState $state): int {
        $count = count(array_filter(
            $state->steps()->all(),
            fn(AgentStep $step) => $step->hasErrors(),
        ));

        // Include current step if it has errors AND is not already in stepExecutions
        $currentStep = $state->currentStep();
        $lastResultStep = $state->stepExecutions()->last()?->step;
        $currentStepAlreadyCounted = false;
        if ($currentStep !== null && $lastResultStep !== null) {
            $currentStepAlreadyCounted = $currentStep->id() === $lastResultStep->id();
        }

        if ($currentStep !== null && $currentStep->hasErrors() && !$currentStepAlreadyCounted) {
            $count++;
        }

        return $count;
    }
}
