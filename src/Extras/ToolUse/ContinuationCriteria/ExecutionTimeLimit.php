<?php

namespace Cognesy\Instructor\Extras\ToolUse\ContinuationCriteria;

use Cognesy\Instructor\Extras\ToolUse\Contracts\CanDecideToContinue;
use Cognesy\Instructor\Extras\ToolUse\ToolUseContext;

class ExecutionTimeLimit implements CanDecideToContinue
{
    private int $maxExecutionTime;
    private int $executionStartTime;

    public function __construct(int $maxExecutionTime) {
        $this->maxExecutionTime = $maxExecutionTime;
        $this->executionStartTime = time();
    }

    public function canContinue(ToolUseContext $context): bool {
        return ((time() - $this->executionStartTime) < $this->maxExecutionTime);
    }
}
