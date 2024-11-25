<?php

namespace Cognesy\Instructor\Extras\ToolUse\Traits\ToolUse;

use Cognesy\Instructor\Extras\ToolUse\ContinuationCriteria\ErrorPresenceCheck;
use Cognesy\Instructor\Extras\ToolUse\ContinuationCriteria\ExecutionTimeLimit;
use Cognesy\Instructor\Extras\ToolUse\ContinuationCriteria\RetryLimit;
use Cognesy\Instructor\Extras\ToolUse\ContinuationCriteria\StepsLimit;
use Cognesy\Instructor\Extras\ToolUse\ContinuationCriteria\TokenUsageLimit;
use Cognesy\Instructor\Extras\ToolUse\ContinuationCriteria\ToolCallPresenceCheck;
use Cognesy\Instructor\Extras\ToolUse\Contracts\CanDecideToContinue;
use Cognesy\Instructor\Extras\ToolUse\ToolUseContext;

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
        int $maxRetries = 3
    ) : self {
        $this->withContinuationCriteria(
            new StepsLimit($maxSteps),
            new TokenUsageLimit($maxTokens),
            new ExecutionTimeLimit($maxExecutionTime),
            new RetryLimit($maxRetries),
            new ErrorPresenceCheck(),
            new ToolCallPresenceCheck(),
        );
        return $this;
    }

    public function hasNextStep() : bool {
        if ($this->context->currentStep() === null) {
            return true;
        }
        return $this->canContinue($this->context);
    }

    // INTERNAL /////////////////////////////////////////////

    protected function canContinue(ToolUseContext $context) : bool {
        foreach ($this->continuationCriteria as $criterion) {
            if (!$criterion->canContinue($context)) {
                return false;
            }
        }
        return true;
    }
}
