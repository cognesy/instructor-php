<?php

namespace Cognesy\Addons\ToolUse\ContinuationCriteria;

use Cognesy\Addons\ToolUse\Contracts\CanDecideToContinue;
use Cognesy\Addons\ToolUse\ToolUseContext;
use Cognesy\LLM\LLM\Data\Usage;

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
