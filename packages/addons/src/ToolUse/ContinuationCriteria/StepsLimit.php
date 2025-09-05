<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\ContinuationCriteria;

use Cognesy\Addons\ToolUse\Contracts\CanDecideToContinue;
use Cognesy\Addons\ToolUse\Data\ToolUseState;

class StepsLimit implements CanDecideToContinue
{
    private int $maxSteps;

    public function __construct(int $maxSteps) {
        $this->maxSteps = $maxSteps;
    }

    public function canContinue(ToolUseState $state): bool {
        return ($state->stepCount() < $this->maxSteps);
    }
}
