<?php

namespace Cognesy\Instructor\Extras\Structure\Traits\Structure;

use Cognesy\Instructor\Features\Schema\Data\Schema\ObjectSchema;
use Cognesy\Instructor\Features\Schema\Data\Schema\Schema;
use Cognesy\Instructor\Features\Schema\Data\TypeDetails;
use Cognesy\Instructor\Features\Schema\Visitors\SchemaToJsonSchema;

trait ProvidesSchema
{
    public function schema() : Schema {
        return $this->makeSchema();
    }

    public function toJsonSchema() : array {
        return (new SchemaToJsonSchema)->toArray($this->toSchema());
    }

    public function toSchema(): Schema {
        return $this->makeSchema();
    }

    // INTERNAL //////////////////////////////////////////////////////////////////////

    private function makeSchema() : Schema {
        $properties = [];
        $required = [];
        foreach ($this->fields as $fieldName => $field) {
            $fieldSchema = $field->schema();
            $properties[$fieldName] = $fieldSchema;
            if ($field->isRequired()) {
                $required[] = $fieldName;
            }
        }
        $schema = new ObjectSchema(
            type: TypeDetails::object(static::class),
            name: $this->name(),
            description: $this->description(),
            properties: $properties,
            required: $required,
        );
        return $schema;
    }
}