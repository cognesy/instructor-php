<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\ContinuationCriteria;

use Cognesy\Addons\ToolUse\Contracts\CanDecideToContinue;
use Cognesy\Addons\ToolUse\ToolUseState;

class ExecutionTimeLimit implements CanDecideToContinue
{
    private int $maxExecutionTime;

    public function __construct(int $maxExecutionTime) {
        $this->maxExecutionTime = $maxExecutionTime;
    }

    public function canContinue(ToolUseState $state): bool {
        $startedAt = $state->startedAt();
        return ((time() - $startedAt->getTimestamp()) < $this->maxExecutionTime);
    }
}
