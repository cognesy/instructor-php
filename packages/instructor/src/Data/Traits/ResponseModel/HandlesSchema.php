<?php declare(strict_types=1);

namespace Cognesy\Instructor\Data\Traits\ResponseModel;

use Cognesy\Schema\Data\Schema\Schema;

trait HandlesSchema
{
    public function schemaName() : string {
        return $this->schemaName ?? $this->schema()->name();
    }

    public function schema() : Schema {
        return $this->schema;
    }

    /** @return string[] */
    public function getPropertyNames() : array {
        return $this->schema()->getPropertyNames();
    }

    /** @return array<string, mixed> */
    public function getPropertyValues() : array {
        $values = [];
        foreach ($this->getPropertyNames() as $name) {
            $values[$name] = match(true) {
                isset($this->instance->$name) => $this->instance->$name,
                default => null,
            };
        }
        return $values;
    }

    /** @param array<string, mixed> $values */
    public function setPropertyValues(array $values) : void {
        foreach ($values as $name => $value) {
            if (property_exists($this->instance, $name)) {
                $this->instance->$name = $value;
            }
        }
    }

    public function toJsonSchema() : array {
        // TODO: this can be computed from schema
        return $this->jsonSchema;
    }
}