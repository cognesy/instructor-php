<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Contracts;

interface CanAccessAnyState
{
    public function withState(object $state): self;
}