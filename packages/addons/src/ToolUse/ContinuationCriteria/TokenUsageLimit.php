<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\ContinuationCriteria;

use Cognesy\Addons\ToolUse\Contracts\CanDecideToContinue;
use Cognesy\Addons\ToolUse\Data\ToolUseState;

class TokenUsageLimit implements CanDecideToContinue
{
    private int $maxTokens;

    public function __construct(int $maxTokens) {
        $this->maxTokens = $maxTokens;
    }

    public function canContinue(ToolUseState $state): bool {
        return ($state->usage()->total() < $this->maxTokens);
    }
}
