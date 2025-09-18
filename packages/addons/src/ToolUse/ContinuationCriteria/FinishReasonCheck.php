<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\ContinuationCriteria;

use Cognesy\Addons\ToolUse\Contracts\CanDecideToContinueToolUse;
use Cognesy\Addons\ToolUse\Data\ToolUseState;

class FinishReasonCheck implements CanDecideToContinueToolUse
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
