<?php

namespace Cognesy\Instructor\Extras\Structure\Traits\Factory;

use Cognesy\Instructor\Extras\Structure\Structure;
use Cognesy\Instructor\Features\Schema\Factories\JsonSchemaToSchema;

trait CreatesStructureFromJsonSchema
{
    static public function fromJsonSchema(array $jsonSchema): Structure {
        $name = $jsonSchema['x-title'] ?? '';
        $description = $jsonSchema['description'] ?? '';
        $schema = (new JsonSchemaToSchema)->fromJsonSchema($jsonSchema);
        return self::fromSchema($name, $schema, $description);
    }
}