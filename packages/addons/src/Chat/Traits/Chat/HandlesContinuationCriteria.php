<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Traits\Chat;

use Cognesy\Addons\Chat\Contracts\CanDecideToContinue;
use Cognesy\Addons\Chat\ContinuationCriteria\ExecutionTimeLimit;
use Cognesy\Addons\Chat\ContinuationCriteria\FinishReasonCheck;
use Cognesy\Addons\Chat\ContinuationCriteria\StepsLimit;
use Cognesy\Addons\Chat\ContinuationCriteria\TokenUsageLimit;
use Cognesy\Addons\Chat\Data\ContinuationCriteria as ContinuationCriteriaCollection;
use Cognesy\Addons\Chat\Data\ChatState;

trait HandlesContinuationCriteria
{
    public function withContinuationCriteria(CanDecideToContinue ...$continuationCriteria) : self {
        if (!($this->continuationCriteria instanceof ContinuationCriteriaCollection)) {
            $this->continuationCriteria = new ContinuationCriteriaCollection();
        }
        $this->continuationCriteria->add(...$continuationCriteria);
        return $this;
    }

    public function hasNextTurn() : bool {
        if ($this->state->currentStep() === null) { return true; }
        return $this->canContinue($this->state);
    }

    protected function canContinue(ChatState $state) : bool {
        return $this->continuationCriteria->canContinue($state);
    }

    public function withDefaultContinuationCriteria(
        int $maxSteps = 1,
        int $maxTokens = 8192,
        int $maxExecutionTime = 30,
        array $finishReasons = [],
    ) : self {
        $this->withContinuationCriteria(
            new StepsLimit($maxSteps),
            new TokenUsageLimit($maxTokens),
            new ExecutionTimeLimit($maxExecutionTime),
            new FinishReasonCheck($finishReasons),
        );
        return $this;
    }
}
