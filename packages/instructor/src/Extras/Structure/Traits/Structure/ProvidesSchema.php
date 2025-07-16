<?php declare(strict_types=1);

namespace Cognesy\Instructor\Extras\Structure\Traits\Structure;

use Cognesy\Schema\Data\Schema\ObjectSchema;
use Cognesy\Schema\Data\Schema\Schema;
use Cognesy\Schema\Data\TypeDetails;
use Cognesy\Schema\Visitors\SchemaToJsonSchema;

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