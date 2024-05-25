<?php

namespace Cognesy\Instructor\Extras\Structure\Traits;

use Cognesy\Instructor\Extras\Field\Field;
use Cognesy\Instructor\Extras\Structure\Structure;
use Cognesy\Instructor\Schema\Data\TypeDetails;

trait CreatesFromJsonSchema
{
    static public function fromJsonSchema(array $jsonSchema) : self {
        $name = $jsonSchema['title'] ?? '';
        $description = $jsonSchema['description'] ?? '';
        $fields = self::makeJsonSchemaFields($jsonSchema);
        return Structure::define($name, $fields, $description);
    }

    static private function makeJsonSchemaFields(array $jsonSchema) : array {
        $fields = [];
        foreach ($jsonSchema['properties'] as $name => $value) {
            $typeName = TypeDetails::fromJsonType($value['type']);
            $fields[] = Field::fromTypeName($name, $typeName, $value['description'] ?? '');
        }
        return $fields;
    }
}