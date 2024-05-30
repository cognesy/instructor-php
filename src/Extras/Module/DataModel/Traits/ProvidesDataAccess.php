<?php

namespace Cognesy\Instructor\Extras\Module\DataModel\Traits;

use Exception;

trait ProvidesDataAccess
{
    use HandlesObjectValues;

    public function getPropertyValue(string $name): mixed {
        if (!in_array($name, $this->propertyNames)) {
            throw new Exception("No input field '$name'");
        }
        return $this->data->$name;
    }

    public function setPropertyValue(string $name, mixed $value): void {
        if (!in_array($name, $this->propertyNames)) {
            throw new Exception("No input field '$name'");
        }
        $this->data->$name = $value;
    }

    /** @return array<string, mixed> */
    public function getValues() : array {
        return $this->getObjectPropertyValues($this->data, $this->propertyNames);
    }

    public function setValues(array $values): void {
        $this->setObjectProperties($this->data, $this->propertyNames, $values);
    }
}