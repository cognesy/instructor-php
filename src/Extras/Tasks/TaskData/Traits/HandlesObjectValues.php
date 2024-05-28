<?php

namespace Cognesy\Instructor\Extras\Tasks\TaskData\Traits;

use Exception;

trait HandlesObjectValues
{
    private function getPropertyValues(object $object, array $propertyNames) : array {
        $values = [];
        foreach ($propertyNames as $name) {
            $values[$name] = $object->$name;
        }
        return $values;
    }

    private function setProperties(object $inputs, array $inputNames, array $values) : void {
        foreach ($values as $name => $value) {
            if (!in_array($name, $inputNames)) {
                throw new Exception("No input field '$name'");
            }
            $inputs->$name = $value;
        }
    }
}