<?php

namespace Cognesy\Instructor\Extras\ToolUse\Traits\ToolUse;

use Cognesy\Instructor\Extras\ToolUse\Contracts\CanProcessStep;
use Cognesy\Instructor\Extras\ToolUse\Processors\AppendStepMessages;
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
            new AppendStepMessages(),
        );
        return $this;
    }

    public function processStep(ToolUseContext $context, ToolUseStep $step): void {
        foreach ($this->processors as $processor) {
            $processor->processStep($context, $step);
        }
    }
}