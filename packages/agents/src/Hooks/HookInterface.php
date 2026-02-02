<?php declare(strict_types=1);

namespace Cognesy\Agents\Hooks;

interface HookInterface
{
    public function handle(HookContext $context) : HookContext;
}