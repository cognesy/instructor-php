<?php

namespace Cognesy\Instructor\Extras\Module\DataModel\Traits;

use Cognesy\Instructor\Schema\Data\Schema\Schema;
use Exception;

trait ProvidesSchemaAccess
{
    use HandlesObjectSchema;

    public function getPropertySchema(string $name): Schema {
        if (!in_array($name, $this->propertyNames)) {
            throw new Exception("No input field '$name'");
        }
        return $this->getObjectPropertySchema($this->data, $name);
    }

    /** @return Schema[] */
    public function getPropertySchemas() : array {
        return $this->getObjectSchemas($this->data, $this->propertyNames);
    }

    public function toSchema() : Schema {
        return $this->getObjectSchema($this->data);
    }
}