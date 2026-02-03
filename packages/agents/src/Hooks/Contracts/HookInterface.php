<?php declare(strict_types=1);

namespace Cognesy\Agents\Hooks\Contracts;

use Cognesy\Agents\Hooks\Data\HookContext;

interface HookInterface
{
    public function handle(HookContext $context) : HookContext;
}