<?php declare(strict_types=1);
namespace Cognesy\Agents\Hooks\Interceptors;

use Cognesy\Agents\Hooks\Data\HookContext;

interface CanInterceptAgentLifecycle
{
    public function intercept(HookContext $context): HookContext;
}