<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\ContinuationCriteria;

use Cognesy\Addons\ToolUse\Contracts\CanDecideToContinue;
use Cognesy\Addons\ToolUse\ToolUseState;

class FinishReasonCheck implements CanDecideToContinue
{
    private array $finishOnReasons;

    public function __construct(array $finishOnReasons = []) {
        $this->finishOnReasons = $finishOnReasons;
    }

    public function canContinue(ToolUseState $state): bool {
        if (empty($this->finishOnReasons)) {
            return true;
        }
        // Stop when finish reason is one of the configured reasons
        return !in_array($state->currentStep()?->finishReason(), $this->finishOnReasons, true);
    }
}
