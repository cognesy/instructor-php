<?php

namespace Cognesy\Instructor\Features\Schema\Utils;

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
            $instance = $attribute->newInstance();
            if (property_exists($instance, $attributeProperty)) {
                $values[] = $instance->$attributeProperty;
            }
        }
        return $values;
    }
}
