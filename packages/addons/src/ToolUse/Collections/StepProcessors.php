<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Collections;

use Cognesy\Addons\ToolUse\Contracts\CanProcessStep;
use Cognesy\Addons\ToolUse\ToolUseState;
use Cognesy\Addons\ToolUse\ToolUseStep;

final class StepProcessors
{
    /** @var CanProcessStep[] */
    private array $items = [];

    public function add(CanProcessStep ...$processors) : self {
        foreach ($processors as $processor) {
            $this->items[] = $processor;
        }
        return $this;
    }

    public function isEmpty() : bool {
        return $this->items === [];
    }

    public function apply(ToolUseStep $step, ToolUseState $state) : ToolUseStep {
        foreach ($this->items as $processor) {
            $step = $processor->processStep($step, $state);
        }
        return $step;
    }
}

