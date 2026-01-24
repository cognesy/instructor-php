<?php declare(strict_types=1);

namespace Cognesy\Agents\Agent\ErrorHandling;

interface CanResolveErrorContext
{
    public function resolve(object $state): ErrorContext;
}
