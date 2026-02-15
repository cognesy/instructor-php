<?php

declare(strict_types=1);

namespace Cognesy\Config\PHPStan;

use ReflectionMethod;
use ShipMonk\PHPStan\DeadCode\Provider\SimpleMethodEntrypointProvider;

/**
 * Treats all public methods as entrypoints for library mode.
 * Public methods may be called by external consumers.
 */
class PublicMethodEntrypointProvider extends SimpleMethodEntrypointProvider
{
    #[\Override]
    public function isEntrypointMethod(ReflectionMethod $method): bool
    {
        return $method->isPublic();
    }
}
