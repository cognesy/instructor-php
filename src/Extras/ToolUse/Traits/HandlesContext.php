<?php

namespace Cognesy\Instructor\Extras\ToolUse\Traits;

use Cognesy\Instructor\Extras\ToolUse\ToolUseContext;

trait HandlesContext
{
    protected ToolUseContext $context;

    public function withContext(ToolUseContext $context): self {
        $this->context = $context;
        return $this;
    }
}