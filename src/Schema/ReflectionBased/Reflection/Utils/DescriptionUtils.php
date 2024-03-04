<?php

namespace Cognesy\Instructor\Schema\ReflectionBased\Reflection\Utils;

use Cognesy\Instructor\Attributes\Description;
use Cognesy\Instructor\Schema\ReflectionBased\Reflection\Attribute\AttributeUtils;
use Cognesy\Instructor\Schema\ReflectionBased\Reflection\PhpDoc\DocstringUtils;
use Cognesy\Instructor\Utils\Arrays;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionProperty;

class DescriptionUtils
{
    public static function getParameterDescription(ReflectionParameter $parameter): string
    {
        return Arrays::flatten(
            // TODO: extract descriptions from method docstring
            AttributeUtils::getValues($parameter, Description::class, 'text'),
            "\n"
        );
    }

    public static function getPropertyDescription(ReflectionProperty $property): string
    {
        return Arrays::flatten([
            DocstringUtils::descriptionsOnly($property->getDocComment()),
            AttributeUtils::getValues($property, Description::class, 'text')
        ], "\n");
    }

    public static function getClassDescription(ReflectionClass $class): string
    {
        return Arrays::flatten([
            DocstringUtils::descriptionsOnly($class->getDocComment()),
            AttributeUtils::getValues($class, Description::class, 'text')
        ], "\n");
    }

    public static function getFunctionDescription(ReflectionFunction $function): string
    {
        return Arrays::flatten([
            DocstringUtils::descriptionsOnly($function->getDocComment()),
            AttributeUtils::getValues($function, Description::class, 'text')
        ], "\n");
    }

    public static function getMethodDescription(ReflectionMethod $method): string
    {
        return Arrays::flatten([
            DocstringUtils::descriptionsOnly($method->getDocComment()),
            AttributeUtils::getValues($method, Description::class, 'text')
        ], "\n");
    }
}