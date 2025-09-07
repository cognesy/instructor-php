<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Processors;

use Cognesy\Addons\ToolUse\Contracts\CanProcessToolStep;
use Cognesy\Addons\ToolUse\Data\ToolUseState;
use Cognesy\Addons\ToolUse\Data\ToolUseStep;

class AccumulateTokenUsage implements CanProcessToolStep
{
    public function processStep(ToolUseStep $step, ToolUseState $state): ToolUseStep {
        $state->accumulateUsage($step->usage());
        return $step;
    }
}
