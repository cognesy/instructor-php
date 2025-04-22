<?php

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
use Cognesy\Addons\ToolUse\ToolUseContext;

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
        if ($this->context->currentStep() === null) {
            return true;
        }
        return $this->canContinue($this->context);
    }

    // INTERNAL /////////////////////////////////////////////

    protected function canContinue(ToolUseContext $context) : bool {
        foreach ($this->continuationCriteria as $criterion) {
            if (!$criterion->canContinue($context)) {
                $context->withStatus(match(true) {
                    $context->currentStep()?->hasErrors() => ToolUseStatus::Failed,
                    default => ToolUseStatus::Completed,
                });
                return false;
            }
        }
        return true;
    }
}
