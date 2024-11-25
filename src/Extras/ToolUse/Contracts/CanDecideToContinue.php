<?php

namespace Cognesy\Instructor\Extras\ToolUse\Contracts;

use Cognesy\Instructor\Extras\ToolUse\ToolUseContext;

interface CanDecideToContinue
{
    public function canContinue(ToolUseContext $context) : bool;
}
