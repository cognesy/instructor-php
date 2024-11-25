<?php

namespace Cognesy\Instructor\Extras\ToolUse\ContinuationCriteria;

use Cognesy\Instructor\Extras\ToolUse\Contracts\CanDecideToContinue;
use Cognesy\Instructor\Extras\ToolUse\ToolUseContext;

class ToolCallPresenceCheck implements CanDecideToContinue
{
    public function canContinue(ToolUseContext $context): bool {
        return $context->currentStep()?->hasToolCalls() ?? false;
    }
}