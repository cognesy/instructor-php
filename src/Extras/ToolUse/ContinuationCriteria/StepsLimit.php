<?php

namespace Cognesy\Instructor\Extras\ToolUse\ContinuationCriteria;

use Cognesy\Instructor\Extras\ToolUse\Contracts\CanDecideToContinue;
use Cognesy\Instructor\Extras\ToolUse\ToolUseContext;

class StepsLimit implements CanDecideToContinue
{
    private int $maxSteps;
    private int $currentStep = 0;

    public function __construct(int $maxSteps) {
        $this->maxSteps = $maxSteps;
    }

    public function canContinue(ToolUseContext $context): bool {
        $this->currentStep++;
        return ($this->currentStep < $this->maxSteps);
    }
}
