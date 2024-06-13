<?php
namespace Cognesy\Instructor\Traits;

use Cognesy\Instructor\Attributes\FacadeAccessor;
use BadMethodCallException;
use ReflectionClass;

trait HasFacade
{
    /**
     * can be specified in the class that uses this trait
     * @var array<string>
     */
    protected static array $facadeMethods = [];

    /**
     * can be overridden to provide custom way to get an instance
     * @return static
     */
    public static function instance() : static {
        return new static();
    }

    /**
     * Can be overridden to provide custom list of facade methods.
     * @return array<string>
     */
    protected static function facadeMethods() : array {
        return static::$facadeMethods;
    }

    public static function __callStatic(string $method, array $args) {
        if (!self::instance()->hasMethod($method)) {
            throw new BadMethodCallException("Method $method does not exist or not exposed as a facade.");
        }
        return self::instance()->$method(...$args);
    }

    protected function hasMethod(string $method) : bool {
        return (
                in_array($method, self::facadeMethods())
                || in_array($method, self::methodsWithAttribute(FacadeAccessor::class))
            )
            && method_exists($this, $method);
    }

    protected static function methodsWithAttribute(string $attribute) : array {
        $methods = [];
        $reflection = new ReflectionClass(static::class);
        foreach ($reflection->getMethods() as $method) {
            if ($method->getAttributes($attribute)) {
                $methods[] = $method->getName();
            }
        }
        return $methods;
    }
}