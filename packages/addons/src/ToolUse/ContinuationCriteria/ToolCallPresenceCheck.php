<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\ContinuationCriteria;

use Cognesy\Addons\ToolUse\Contracts\CanDecideToContinue;
use Cognesy\Addons\ToolUse\ToolUseContext;

class ToolCallPresenceCheck implements CanDecideToContinue
{
    public function canContinue(ToolUseContext $context): bool {
        return $context->currentStep()?->hasToolCalls() ?? false;
    }
}