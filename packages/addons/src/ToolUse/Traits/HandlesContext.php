<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Traits;

use Cognesy\Addons\ToolUse\ToolUseContext;

trait HandlesContext
{
    protected ToolUseContext $context;

    public function withContext(ToolUseContext $context): self {
        $this->context = $context;
        return $this;
    }
}