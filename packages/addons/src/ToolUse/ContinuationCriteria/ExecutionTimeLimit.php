<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\ContinuationCriteria;

use Cognesy\Addons\ToolUse\Contracts\CanDecideToContinue;
use Cognesy\Addons\ToolUse\ToolUseState;

class ExecutionTimeLimit implements CanDecideToContinue
{
    private int $maxExecutionTime;
    private int $executionStartTime;

    public function __construct(int $maxExecutionTime) {
        $this->maxExecutionTime = $maxExecutionTime;
        $this->executionStartTime = time();
    }

    public function canContinue(ToolUseState $state): bool {
        return ((time() - $this->executionStartTime) < $this->maxExecutionTime);
    }
}
