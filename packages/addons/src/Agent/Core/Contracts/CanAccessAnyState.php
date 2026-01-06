<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Core\Contracts;

use Cognesy\Addons\Agent\Contracts\ToolInterface;

interface CanAccessAnyState extends ToolInterface
{
    public function withState(object $state): self;
}
