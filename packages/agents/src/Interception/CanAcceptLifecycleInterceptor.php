<?php declare(strict_types=1);

namespace Cognesy\Agents\Interception;

interface CanAcceptLifecycleInterceptor
{
    public function withInterceptor(CanInterceptAgentLifecycle $interceptor): static;
}
