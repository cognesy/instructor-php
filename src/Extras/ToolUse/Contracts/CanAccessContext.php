<?php

namespace Cognesy\Instructor\Extras\ToolUse\Contracts;

use Cognesy\Instructor\Extras\ToolUse\ToolUseContext;

interface CanAccessContext
{
    public function withContext(ToolUseContext $context): self;
}