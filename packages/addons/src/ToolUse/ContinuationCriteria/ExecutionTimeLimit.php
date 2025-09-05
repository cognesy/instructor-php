<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\ContinuationCriteria;

use Cognesy\Addons\ToolUse\Contracts\CanDecideToContinue;
use Cognesy\Addons\ToolUse\Data\ToolUseState;
use Cognesy\Utils\Time\ClockInterface;
use Cognesy\Utils\Time\SystemClock;

class ExecutionTimeLimit implements CanDecideToContinue
{
    private int $maxExecutionTime;
    private ClockInterface $clock;

    public function __construct(int $maxExecutionTime, ?ClockInterface $clock = null) {
        $this->maxExecutionTime = $maxExecutionTime;
        $this->clock = $clock ?? new SystemClock();
    }

    public function canContinue(ToolUseState $state): bool {
        $startedAt = $state->startedAt();
        $now = $this->clock->now();
        return (($now->getTimestamp() - $startedAt->getTimestamp()) < $this->maxExecutionTime);
    }
}
