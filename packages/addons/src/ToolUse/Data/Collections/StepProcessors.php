<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Data\Collections;

use Cognesy\Addons\ToolUse\Contracts\CanProcessToolStep;
use Cognesy\Addons\ToolUse\Data\ToolUseState;
use Cognesy\Addons\ToolUse\Data\ToolUseStep;

final class StepProcessors
{
    /** @var CanProcessToolStep[] */
    private array $items = [];

    public function add(CanProcessToolStep ...$processors) : self {
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

