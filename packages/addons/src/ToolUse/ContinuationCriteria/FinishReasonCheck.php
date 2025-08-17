<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\ContinuationCriteria;

use Cognesy\Addons\ToolUse\Contracts\CanDecideToContinue;
use Cognesy\Addons\ToolUse\ToolUseContext;

class FinishReasonCheck implements CanDecideToContinue
{
    private array $finishOnReasons;

    public function __construct(array $finishOnReasons = []) {
        $this->finishOnReasons = $finishOnReasons;
    }

    public function canContinue(ToolUseContext $context): bool {
        if (empty($this->finishOnReasons)) {
            return true;
        }
        return in_array($context->currentStep()?->finishReason(), $this->finishOnReasons);
    }
}