<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\ContinuationCriteria;

use Cognesy\Addons\ToolUse\Contracts\CanDecideToContinue;
use Cognesy\Addons\ToolUse\ToolUseState;

class RetryLimit implements CanDecideToContinue
{
    private int $maxRetries;
    private int $currentRetries = 0;

    public function __construct(int $maxRetries) {
        $this->maxRetries = $maxRetries;
    }

    public function canContinue(ToolUseState $state): bool {
        if ($state->currentStep()?->hasErrors() ?? false) {
            $this->currentRetries++;
            return ($this->currentRetries < $this->maxRetries);
        }
        return true;
    }
}
