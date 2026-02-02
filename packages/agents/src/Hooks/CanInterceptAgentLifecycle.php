<?php declare(strict_types=1);
namespace Cognesy\Agents\Hooks;

interface CanInterceptAgentLifecycle
{
    public function intercept(HookContext $context): HookContext;
}