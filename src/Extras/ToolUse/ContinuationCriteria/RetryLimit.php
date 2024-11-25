<?php

namespace Cognesy\Instructor\Extras\ToolUse\ContinuationCriteria;

use Cognesy\Instructor\Extras\ToolUse\Contracts\CanDecideToContinue;
use Cognesy\Instructor\Extras\ToolUse\ToolUseContext;

class RetryLimit implements CanDecideToContinue
{
    private int $maxRetries;
    private int $currentRetries = 0;

    public function __construct(int $maxRetries) {
        $this->maxRetries = $maxRetries;
    }

    public function canContinue(ToolUseContext $context): bool {
        if ($context->currentStep()?->hasErrors() ?? false) {
            $this->currentRetries++;
            return ($this->currentRetries < $this->maxRetries);
        }
        return true;
    }
}
