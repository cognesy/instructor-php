<?php

namespace Cognesy\Instructor\Extras\Tasks\TaskData;

use Cognesy\Instructor\Extras\Tasks\TaskData\Contracts\DataModel;
use Cognesy\Instructor\Schema\Data\Schema\Schema;
use Exception;

class ObjectDataModel implements DataModel
{
    use Traits\HandlesObjectSchema;
    use Traits\HandlesObjectValues;

    private object $data;
    /** @var string[] */
    private array $propertyNames;

    /**
     * @param object $data
     * @param string[] $propertyNames
     */
    public function __construct(
        object $data,
        array $propertyNames,
    ) {
        $this->data = $data;
        $this->propertyNames = $propertyNames;
    }


    /** @return string[] */
    public function getPropertyNames(): array {
        return $this->propertyNames;
    }

    public function getPropertySchema(string $name): Schema {
        if (!in_array($name, $this->propertyNames)) {
            throw new Exception("No input field '$name'");
        }
        return $this->getObjectPropertySchema($this->data, $name);
    }

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

    /** @return Schema[] */
    public function getPropertySchemas() : array {
        return $this->getObjectSchemas($this->data, $this->propertyNames);
    }

    public function setValues(array $values): void {
        $this->setObjectProperties($this->data, $this->propertyNames, $values);
    }

    public function getRef() : object {
        return $this->data;
    }

    public function toSchema() : Schema {
        return $this->getObjectSchema($this->data);
    }
}
