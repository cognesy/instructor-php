<?php declare(strict_types=1);

namespace Cognesy\Agents\Hooks;

class PassThroughInterceptor implements CanInterceptAgentLifecycle
{
    public static function default() : self {
        return new self();
    }

    public function intercept(HookContext $context): HookContext {
        return $context;
    }
}