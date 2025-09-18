<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\ContinuationCriteria;

use Cognesy\Addons\ToolUse\Contracts\CanDecideToContinueToolUse;
use Cognesy\Addons\ToolUse\Data\ToolUseState;

class RetryLimit implements CanDecideToContinueToolUse
{
    private int $maxRetries;

    public function __construct(int $maxRetries) {
        $this->maxRetries = $maxRetries;
    }

    public function canContinue(ToolUseState $state): bool {
        // Count consecutive failed steps from the end
        $failedTail = 0;
        foreach ($state->steps()->reversed() as $step) {
            if ($step->hasErrors()) {
                $failedTail++;
                continue;
            }
            break;
        }
        return ($failedTail < $this->maxRetries);
    }
}
