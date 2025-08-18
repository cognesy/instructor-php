<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Processors;

use Cognesy\Addons\ToolUse\Contracts\CanProcessStep;
use Cognesy\Addons\ToolUse\ToolUseState;
use Cognesy\Addons\ToolUse\ToolUseStep;

class AccumulateTokenUsage implements CanProcessStep
{
    public function processStep(ToolUseStep $step, ToolUseState $state): ToolUseStep {
        $state->accumulateUsage($step->usage());
        return $step;
    }
}
