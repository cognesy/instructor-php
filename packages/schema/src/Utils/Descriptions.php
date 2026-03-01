<?php declare(strict_types=1);

namespace Cognesy\Schema\Utils;

use Cognesy\Schema\Attributes\Description;
use Cognesy\Schema\Attributes\Instructions;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionProperty;

class Descriptions
{
    /** @param class-string $class */
    public static function forClass(string $class) : string {
        return self::join([
            ...AttributeUtils::getValues(new ReflectionClass($class), Description::class, 'text'),
            ...AttributeUtils::getValues(new ReflectionClass($class), Instructions::class, 'text'),
            DocstringUtils::descriptionsOnly((new ReflectionClass($class))->getDocComment() ?: ''),
        ]);
    }

    /** @param class-string $class */
    public static function forProperty(string $class, string $propertyName) : string {
        $reflection = new ReflectionProperty($class, $propertyName);

        return self::join([
            ...AttributeUtils::getValues($reflection, Description::class, 'text'),
            ...AttributeUtils::getValues($reflection, Instructions::class, 'text'),
            DocstringUtils::descriptionsOnly($reflection->getDocComment() ?: ''),
        ]);
    }

    public static function forFunction(string $functionName) : string {
        $reflection = new ReflectionFunction($functionName);

        return self::join([
            ...AttributeUtils::getValues($reflection, Description::class, 'text'),
            ...AttributeUtils::getValues($reflection, Instructions::class, 'text'),
            DocstringUtils::descriptionsOnly($reflection->getDocComment() ?: ''),
        ]);
    }

    /** @param class-string $class */
    public static function forMethod(string $class, string $methodName) : string {
        $reflection = new ReflectionMethod($class, $methodName);

        return self::join([
            ...AttributeUtils::getValues($reflection, Description::class, 'text'),
            ...AttributeUtils::getValues($reflection, Instructions::class, 'text'),
            DocstringUtils::descriptionsOnly($reflection->getDocComment() ?: ''),
        ]);
    }

    /** @param class-string $class */
    public static function forMethodParameter(string $class, string $methodName, string $parameterName) : string {
        return self::forParameter(new ReflectionParameter([$class, $methodName], $parameterName), $parameterName);
    }

    public static function forFunctionParameter(string $functionName, string $parameterName) : string {
        return self::forParameter(new ReflectionParameter($functionName, $parameterName), $parameterName);
    }

    private static function forParameter(ReflectionParameter $parameter, string $parameterName) : string {
        $function = $parameter->getDeclaringFunction();

        return self::join([
            ...AttributeUtils::getValues($parameter, Description::class, 'text'),
            ...AttributeUtils::getValues($parameter, Instructions::class, 'text'),
            DocstringUtils::getParameterDescription($parameterName, $function->getDocComment() ?: ''),
        ]);
    }

    /** @param array<int, mixed> $values */
    private static function join(array $values) : string {
        $values = array_values(array_filter(array_map(
            static fn(mixed $value) : string => is_string($value) ? trim($value) : '',
            $values,
        ), static fn(string $value) : bool => $value !== ''));

        return trim(implode("\n", $values));
    }
}
