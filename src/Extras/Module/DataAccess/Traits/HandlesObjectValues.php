<?php

namespace Cognesy\Instructor\Extras\Module\DataAccess\Traits;

use Exception;

trait HandlesObjectValues
{
    private function getObjectPropertyValues(object $object, array $propertyNames) : array {
        $values = [];
        foreach ($propertyNames as $name) {
            $values[$name] = $object->$name ?? null;
        }
        return $values;
    }

    private function setObjectProperties(object $object, array $propertyNames, array $values) : void {
        foreach ($values as $name => $value) {
            if (!in_array($name, $propertyNames)) {
                throw new Exception("No data field '$name'");
            }
            $object->$name = $value;
        }
    }
}