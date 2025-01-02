<?php
namespace Cognesy\Instructor\Experimental\Module\Core\Traits\Module;

use Cognesy\Instructor\Experimental\Module\Core\Module;
use InvalidArgumentException;

trait HandlesCreation
{
    public static function factory(string $class, mixed ...$constructorArgs) : static {
        if (!class_exists($class)) {
            throw new InvalidArgumentException("Class `$class` not found");
        }
        if (!is_subclass_of($class, Module::class)) {
            throw new InvalidArgumentException("Class `$class` is not a subclass of " . Module::class);
        }
        return $class::with(...$constructorArgs);
    }
}