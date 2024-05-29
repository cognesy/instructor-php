<?php

namespace Cognesy\Instructor\Extras\Structure\Traits\Structure;

use Cognesy\Instructor\Schema\Data\Schema\ObjectSchema;
use Cognesy\Instructor\Schema\Data\Schema\Schema;
use Cognesy\Instructor\Schema\Factories\SchemaFactory;
use Cognesy\Instructor\Schema\Factories\TypeDetailsFactory;
use Cognesy\Instructor\Schema\Visitors\SchemaToJsonSchema;

trait ProvidesSchema
{
    private TypeDetailsFactory $typeDetailsFactory;
    private SchemaFactory $schemaFactory;

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
        $typeDetails = $this->typeDetailsFactory->objectType(static::class);
        $schema = new ObjectSchema(
            type: $typeDetails,
            name: $this->name(),
            description: $this->description(),
            properties: $properties,
            required: $required,
        );
        return $schema;
    }
}