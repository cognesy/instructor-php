<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Traits;

use Cognesy\Addons\ToolUse\Data\ToolUseState;

trait HandlesState
{
    protected ToolUseState $state;

    public function withState(ToolUseState $state): self {
        $this->state = $state;
        return $this;
    }
}