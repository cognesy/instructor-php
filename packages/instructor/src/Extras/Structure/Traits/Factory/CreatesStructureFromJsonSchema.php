<?php

namespace Cognesy\Instructor\Extras\Structure\Traits\Factory;

use Cognesy\Instructor\Extras\Structure\Structure;
use Cognesy\Schema\Factories\JsonSchemaToSchema;

trait CreatesStructureFromJsonSchema
{
    static public function fromJsonSchema(array $jsonSchema): Structure {
        $name = $jsonSchema['x-title'] ?? 'default_schema';
        $description = $jsonSchema['description'] ?? '';
        $schemaConverter = new JsonSchemaToSchema(
            defaultToolName: $name,
            defaultToolDescription: $description,
            defaultOutputClass: Structure::class,
        );
        $schema = $schemaConverter->fromJsonSchema($jsonSchema);
        return self::fromSchema($name, $schema, $description);
    }
}