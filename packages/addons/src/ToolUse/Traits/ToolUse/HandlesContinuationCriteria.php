<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Traits\ToolUse;

use Cognesy\Addons\ToolUse\ContinuationCriteria\ErrorPresenceCheck;
use Cognesy\Addons\ToolUse\ContinuationCriteria\ExecutionTimeLimit;
use Cognesy\Addons\ToolUse\ContinuationCriteria\FinishReasonCheck;
use Cognesy\Addons\ToolUse\ContinuationCriteria\RetryLimit;
use Cognesy\Addons\ToolUse\ContinuationCriteria\StepsLimit;
use Cognesy\Addons\ToolUse\ContinuationCriteria\TokenUsageLimit;
use Cognesy\Addons\ToolUse\ContinuationCriteria\ToolCallPresenceCheck;
use Cognesy\Addons\ToolUse\Contracts\CanDecideToContinue;
use Cognesy\Addons\ToolUse\Enums\ToolUseStatus;
use Cognesy\Addons\ToolUse\ToolUseState;

trait HandlesContinuationCriteria
{
    public function withContinuationCriteria(CanDecideToContinue ...$continuationCriteria) : self {
        foreach ($continuationCriteria as $criterion) {
            $this->continuationCriteria[] = $criterion;
        }
        return $this;
    }

    public function withDefaultContinuationCriteria(
        int $maxSteps = 3,
        int $maxTokens = 8192,
        int $maxExecutionTime = 30,
        int $maxRetries = 3,
        array $finishReasons = [],
    ) : self {
        $this->withContinuationCriteria(
            new StepsLimit($maxSteps),
            new TokenUsageLimit($maxTokens),
            new ExecutionTimeLimit($maxExecutionTime),
            new RetryLimit($maxRetries),
            new ErrorPresenceCheck(),
            new ToolCallPresenceCheck(),
            new FinishReasonCheck($finishReasons),
        );
        return $this;
    }

    public function hasNextStep() : bool {
        // if there are no steps defined, we can continue
        if ($this->state->currentStep() === null) {
            return true;
        }
        // otherwise, check if we can continue based on the criteria
        return $this->canContinue($this->state);
    }

    // INTERNAL /////////////////////////////////////////////

    protected function canContinue(ToolUseState $state) : bool {
        foreach ($this->continuationCriteria as $criterion) {
            if (!$criterion->canContinue($state)) {
                $state->withStatus(match(true) {
                    $state->currentStep()?->hasErrors() => ToolUseStatus::Failed,
                    default => ToolUseStatus::Completed,
                });
                return false;
            }
        }
        return true;
    }
}
