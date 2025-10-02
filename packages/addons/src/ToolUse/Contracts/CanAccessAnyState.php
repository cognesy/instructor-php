<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Contracts;

interface CanAccessAnyState extends ToolInterface
{
    public function withState(object $state): self;
}
