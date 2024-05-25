<?php

namespace Cognesy\Instructor\Schema\Utils;

use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionProperty;

class AttributeUtils
{
    static public function hasAttribute(
        ReflectionClass|ReflectionMethod|ReflectionProperty|ReflectionParameter|ReflectionFunction $element,
        string $attributeClass
    ) : bool {
        return count($element->getAttributes($attributeClass)) > 0;
    }

    static public function getValues(
        ReflectionClass|ReflectionMethod|ReflectionProperty|ReflectionParameter|ReflectionFunction $element,
        string $attributeClass,
        string $attributeProperty
    ) : array {
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
