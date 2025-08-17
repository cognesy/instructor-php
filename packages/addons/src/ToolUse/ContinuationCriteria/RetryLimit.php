<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\ContinuationCriteria;

use Cognesy\Addons\ToolUse\Contracts\CanDecideToContinue;
use Cognesy\Addons\ToolUse\ToolUseContext;

class RetryLimit implements CanDecideToContinue
{
    private int $maxRetries;
    private int $currentRetries = 0;

    public function __construct(int $maxRetries) {
        $this->maxRetries = $maxRetries;
    }

    public function canContinue(ToolUseContext $context): bool {
        if ($context->currentStep()?->hasErrors() ?? false) {
            $this->currentRetries++;
            return ($this->currentRetries < $this->maxRetries);
        }
        return true;
    }
}
