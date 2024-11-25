<?php

namespace Cognesy\Instructor\Extras\ToolUse\Traits\ToolUse;

use Cognesy\Instructor\Extras\ToolUse\Contracts\CanProcessStep;
use Cognesy\Instructor\Extras\ToolUse\Processors\AccumulateTokenUsage;
use Cognesy\Instructor\Extras\ToolUse\Processors\AppendStepMessages;
use Cognesy\Instructor\Extras\ToolUse\Processors\UpdateStep;
use Cognesy\Instructor\Extras\ToolUse\ToolUseContext;
use Cognesy\Instructor\Extras\ToolUse\ToolUseStep;

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
            new AppendStepMessages(),
        );
        return $this;
    }

    public function processStep(ToolUseStep $step, ToolUseContext $context): ToolUseStep {
        foreach ($this->processors as $processor) {
            $step = $processor->processStep($step, $context);
        }
        return $step;
    }
}