<?php

namespace Cognesy\Instructor\Extras\Structure\Traits;

use Cognesy\Instructor\Extras\Structure\Field;
use Cognesy\Instructor\Extras\Structure\Structure;
use Cognesy\Instructor\Schema\Data\Schema\ArraySchema;
use Cognesy\Instructor\Schema\Data\Schema\ObjectSchema;
use Cognesy\Instructor\Schema\Data\Schema\Schema;
use Cognesy\Instructor\Schema\Factories\SchemaFactory;
use Cognesy\Instructor\Schema\Factories\TypeDetailsFactory;

trait ProvidesSchema
{
    private TypeDetailsFactory $typeDetailsFactory;
    private SchemaFactory $schemaFactory;

    public function toSchema(): Schema {
        $properties = [];
        $required = [];
        foreach ($this->fields as $fieldName => $field) {
            $fieldSchema = $this->makeSchema($field);
            $properties[$fieldName] = $fieldSchema;
            if ($field->isRequired()) {
                $required[] = $fieldName;
            }
        }
        $typeDetails = $this->typeDetailsFactory->objectType(static::class);
        $schema = new ObjectSchema(
            type: $typeDetails,
            name: $this->name,
            description: $this->info(),
            properties: $properties,
            required: $required,
        );
        return $schema;
    }

    public function toJsonSchema() : array {
        return $this->toSchema()->toArray();
    }

    private function makeSchema(Field $field) : Schema {
        $fieldType = $field->typeDetails();
        $fieldSchema = match($fieldType->type) {
            'object' => match($fieldType->class) {
                Structure::class => $field->get()->toSchema(),
                default => $this->schemaFactory->makePropertySchema($fieldType, $field->name(), $field->info()),
            },
            'array' => match($fieldType->nestedType->class) {
                Structure::class => $this->makeArraySchema($field),
                default => $this->schemaFactory->makePropertySchema($fieldType, $field->name(), $field->info()),
            },
            default => $this->schemaFactory->makePropertySchema($fieldType, $field->name(), $field->info()),
        };
        return $fieldSchema;
    }

    private function makeArraySchema(Field $field) : Schema {
        $nestedField = $field->get()->withName('items');
        return new ArraySchema(
            $field->typeDetails(),
            $field->name(),
            $field->info(),
            $nestedField->toSchema(),
        );
    }
}