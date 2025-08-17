<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\ContinuationCriteria;

use Cognesy\Addons\ToolUse\Contracts\CanDecideToContinue;
use Cognesy\Addons\ToolUse\ToolUseContext;

class ErrorPresenceCheck implements CanDecideToContinue
{
    public function canContinue(ToolUseContext $context): bool {
        $hasErrors = $context->currentStep()?->hasErrors() ?? false;
        return !($hasErrors);
    }
}