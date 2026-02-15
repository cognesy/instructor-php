<?php declare(strict_types=1);

namespace Cognesy\Agents\Interception;

use Cognesy\Agents\Hook\Data\HookContext;

interface CanInterceptAgentLifecycle
{
    public function intercept(HookContext $context): HookContext;
}