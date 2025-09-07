<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Traits\ToolUse;

use Cognesy\Addons\ToolUse\Contracts\CanProcessToolStep;
use Cognesy\Addons\ToolUse\Data\Collections\StepProcessors;
use Cognesy\Addons\ToolUse\Data\ToolUseState;
use Cognesy\Addons\ToolUse\Data\ToolUseStep;
use Cognesy\Addons\ToolUse\Processors\AccumulateTokenUsage;
use Cognesy\Addons\ToolUse\Processors\AppendContextVariables;
use Cognesy\Addons\ToolUse\Processors\AppendToolStepMessages;
use Cognesy\Addons\ToolUse\Processors\UpdateToolStep;

trait HandlesStepProcessors
{
    public function withProcessors(CanProcessToolStep ...$processors): self {
        if (!($this->processors instanceof StepProcessors)) {
            $this->processors = new StepProcessors();
        }
        $this->processors->add(...$processors);
        return $this;
    }

    public function withDefaultProcessors(): self {
        $this->withProcessors(
            new AccumulateTokenUsage(),
            new UpdateToolStep(),
            new AppendContextVariables(),
            new AppendToolStepMessages(),
        );
        return $this;
    }

    // INTERNAL /////////////////////////////////////////////

    protected function processStep(ToolUseStep $step, ToolUseState $state): ToolUseStep {
        return $this->processors->apply($step, $state);
    }
}
