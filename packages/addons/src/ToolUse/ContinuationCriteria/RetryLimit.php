<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\ContinuationCriteria;

use Cognesy\Addons\ToolUse\Contracts\CanDecideToContinue;
use Cognesy\Addons\ToolUse\Data\ToolUseState;

class RetryLimit implements CanDecideToContinue
{
    private int $maxRetries;

    public function __construct(int $maxRetries) {
        $this->maxRetries = $maxRetries;
    }

    public function canContinue(ToolUseState $state): bool {
        // Count consecutive failed steps from the end
        $failedTail = 0;
        foreach (array_reverse($state->steps()) as $step) {
            if ($step->hasErrors()) {
                $failedTail++;
                continue;
            }
            break;
        }
        return ($failedTail < $this->maxRetries);
    }
}
