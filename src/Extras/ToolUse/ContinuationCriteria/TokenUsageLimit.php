<?php

namespace Cognesy\Instructor\Extras\ToolUse\ContinuationCriteria;

use Cognesy\Instructor\Extras\ToolUse\Contracts\CanDecideToContinue;
use Cognesy\Instructor\Extras\ToolUse\ToolUseContext;
use Cognesy\Instructor\Features\LLM\Data\Usage;

class TokenUsageLimit implements CanDecideToContinue
{
    private int $maxTokens;
    private Usage $usage;

    public function __construct(int $maxTokens) {
        $this->maxTokens = $maxTokens;
        $this->usage = new Usage();
    }

    public function canContinue(ToolUseContext $context): bool {
        $this->usage->accumulate($context->currentStep()?->usage());
        return ($this->usage->total() < $this->maxTokens);
    }
}
