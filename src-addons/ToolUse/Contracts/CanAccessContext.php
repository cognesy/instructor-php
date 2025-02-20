<?php

namespace Cognesy\Addons\ToolUse\Contracts;

use Cognesy\Addons\ToolUse\ToolUseContext;

interface CanAccessContext
{
    public function withContext(ToolUseContext $context): self;
}