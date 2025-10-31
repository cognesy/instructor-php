<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Contracts;

interface CanAccessAnyState extends ToolInterface
{
    public function withState(object $state): self;
}
