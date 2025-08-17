<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\ContinuationCriteria;

use Cognesy\Addons\ToolUse\Contracts\CanDecideToContinue;
use Cognesy\Addons\ToolUse\ToolUseContext;

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
