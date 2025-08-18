<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\ContinuationCriteria;

use Cognesy\Addons\ToolUse\Contracts\CanDecideToContinue;
use Cognesy\Addons\ToolUse\ToolUseState;
use Cognesy\Polyglot\Inference\Data\Usage;

class TokenUsageLimit implements CanDecideToContinue
{
    private int $maxTokens;
    private Usage $usage;

    public function __construct(int $maxTokens) {
        $this->maxTokens = $maxTokens;
        $this->usage = new Usage();
    }

    public function canContinue(ToolUseState $state): bool {
        $this->usage->accumulate($state->currentStep()?->usage());
        return ($this->usage->total() < $this->maxTokens);
    }
}
