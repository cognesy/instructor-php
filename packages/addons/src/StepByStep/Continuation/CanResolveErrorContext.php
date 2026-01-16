<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\Continuation;

interface CanResolveErrorContext
{
    public function resolve(object $state): ErrorContext;
}
