<?php declare(strict_types=1);

namespace Cognesy\Agents\Interception;

use Cognesy\Agents\Hook\Data\HookContext;

class PassThroughInterceptor implements CanInterceptAgentLifecycle
{
    public static function default() : self {
        return new self();
    }

    #[\Override]
    public function intercept(HookContext $context): HookContext {
        return $context;
    }
}