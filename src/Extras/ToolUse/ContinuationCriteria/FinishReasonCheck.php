<?php

namespace Cognesy\Instructor\Extras\ToolUse\ContinuationCriteria;

use Cognesy\Instructor\Extras\ToolUse\Contracts\CanDecideToContinue;
use Cognesy\Instructor\Extras\ToolUse\ToolUseContext;

class FinishReasonCheck implements CanDecideToContinue
{
    private array $finishOnReasons;

    public function __construct(array $finishOnReasons = []) {
        $this->finishOnReasons = $finishOnReasons;
    }

    public function canContinue(ToolUseContext $context): bool {
        if (empty($this->finishOnReasons)) {
            return true;
        }
        return in_array($context->currentStep()?->finishReason(), $this->finishOnReasons);
    }
}