<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Traits\ToolUse;

use Cognesy\Addons\ToolUse\Contracts\CanProcessStep;
use Cognesy\Addons\ToolUse\Processors\AccumulateTokenUsage;
use Cognesy\Addons\ToolUse\Processors\AppendContextVariables;
use Cognesy\Addons\ToolUse\Processors\AppendStepMessages;
use Cognesy\Addons\ToolUse\Processors\UpdateStep;
use Cognesy\Addons\ToolUse\ToolUseContext;
use Cognesy\Addons\ToolUse\ToolUseStep;

trait HandlesStepProcessors
{
    public function withProcessors(CanProcessStep ...$processors): self {
        foreach ($processors as $processor) {
            $this->processors[] = $processor;
        }
        return $this;
    }

    public function withDefaultProcessors(): self {
        $this->withProcessors(
            new AccumulateTokenUsage(),
            new UpdateStep(),
            new AppendContextVariables(),
            new AppendStepMessages(),
        );
        return $this;
    }

    // INTERNAL /////////////////////////////////////////////

    protected function processStep(\Cognesy\Addons\ToolUse\ToolUseStep $step, ToolUseContext $context): ToolUseStep {
        foreach ($this->processors as $processor) {
            $step = $processor->processStep($step, $context);
        }
        return $step;
    }
}