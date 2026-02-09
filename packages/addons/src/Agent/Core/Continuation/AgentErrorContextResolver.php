<?php declare(strict_types=1);

/**
 * @deprecated Use cognesy/agents package instead. This class will be removed in a future version.
 */
namespace Cognesy\Addons\Agent\Core\Continuation;

use Cognesy\Addons\Agent\Core\Collections\ToolExecutions;
use Cognesy\Addons\Agent\Core\Data\AgentExecution;
use Cognesy\Addons\Agent\Core\Data\AgentState;
use Cognesy\Addons\Agent\Core\Data\AgentStep;
use Cognesy\Addons\StepByStep\ErrorHandling\CanResolveErrorContext;
use Cognesy\Addons\StepByStep\ErrorHandling\ErrorContext;
use Cognesy\Addons\StepByStep\ErrorHandling\ErrorType;
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
        AgentStep $step,
        ToolExecutions $executions,
        ?Throwable $error,
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

    private function firstErrorExecution(ToolExecutions $executions): ?AgentExecution {
        foreach ($executions->all() as $execution) {
            if ($execution->hasError()) {
                return $execution;
            }
        }
        return null;
    }

    private function firstError(AgentStep $step, ?AgentExecution $execution): ?Throwable {
        if ($execution !== null && $execution->hasError()) {
            return $execution->error();
        }

        $errors = $step->errors();
        return $errors[0] ?? null;
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
            fn(AgentStep $step) => $step->hasErrors(),
        ));
    }
}
