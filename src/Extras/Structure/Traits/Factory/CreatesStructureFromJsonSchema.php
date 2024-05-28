<?php

namespace Cognesy\Instructor\Extras\Structure\Traits\Factory;

use Cognesy\Instructor\Extras\Structure\Field;
use Cognesy\Instructor\Extras\Structure\FieldFactory;
use Cognesy\Instructor\Extras\Structure\Structure;
use Cognesy\Instructor\Schema\Data\TypeDetails;

trait CreatesStructureFromJsonSchema
{
    static public function fromJsonSchema(array $jsonSchema) : Structure {
        $name = $jsonSchema['title'] ?? '';
        $description = $jsonSchema['description'] ?? '';
        $fields = self::makeJsonSchemaFields($jsonSchema);
        return Structure::define($name, $fields, $description);
    }

    /**
     * @param array $jsonSchema
     * @return Field[]
     */
    static private function makeJsonSchemaFields(array $jsonSchema) : array {
        $fields = [];
        foreach ($jsonSchema['properties'] as $name => $value) {
            $typeName = TypeDetails::fromJsonType($value['type']);
            $fields[] = FieldFactory::fromTypeName($name, $typeName, $value['description'] ?? '');
        }
        return $fields;
    }
}