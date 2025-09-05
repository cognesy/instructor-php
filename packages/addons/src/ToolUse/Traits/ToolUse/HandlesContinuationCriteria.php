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
use Cognesy\Addons\ToolUse\Data\ContinuationCriteria as ContinuationCriteriaCollection;
use Cognesy\Addons\ToolUse\Data\ToolUseState;
use Cognesy\Addons\ToolUse\Enums\ToolUseStatus;
use Cognesy\Addons\ToolUse\Events\ToolUseFinished;

trait HandlesContinuationCriteria
{
    public function withContinuationCriteria(CanDecideToContinue ...$continuationCriteria) : self {
        if (!($this->continuationCriteria instanceof ContinuationCriteriaCollection)) {
            $this->continuationCriteria = new ContinuationCriteriaCollection();
        }
        $this->continuationCriteria->add(...$continuationCriteria);
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
        $can = $this->continuationCriteria->canContinue($state);
        if (!$can) {
            $state->withStatus(match(true) {
                $state->currentStep()?->hasErrors() => ToolUseStatus::Failed,
                default => ToolUseStatus::Completed,
            });
            // emit finished event with status and summary
            $this->dispatch(new ToolUseFinished([
                'status' => $state->status()->value,
                'steps' => $state->stepCount(),
                'usage' => $state->usage()->toArray(),
                'errors' => $state->currentStep()?->errorsAsString(),
            ]));
        }
        return $can;
    }
}
