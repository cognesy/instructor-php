<?php

namespace Cognesy\Instructor\Schema\ReflectionBased\Reflection\Attribute;

use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionProperty;

class AttributeUtils
{
    static public function getValues(
        ReflectionClass|ReflectionMethod|ReflectionProperty|ReflectionParameter|ReflectionFunction $element,
        string $attributeClass,
        string $attributeProperty
    ): array {
        $attributes = $element->getAttributes($attributeClass);
        $values = [];
        foreach ($attributes as $attribute) {
            if (property_exists($attribute->newInstance(), $attributeProperty)) {
                $values[] = $attribute->newInstance()->$attributeProperty;
            }
        }
        return $values;
    }
}