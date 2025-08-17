<?php declare(strict_types=1);

namespace Cognesy\Dynamic\Traits\StructureFactory;

use Cognesy\Dynamic\FieldFactory;
use Cognesy\Dynamic\Structure;
use Cognesy\Schema\Data\Schema\Schema;

trait CreatesStructureFromSchema
{
    static public function fromSchema(string $name, Schema $schema, string $description = '') : Structure {
        $fields = self::makeSchemaFields($schema);
        $name = $name ?: $schema->name();
        $description = $description ?: $schema->description();
        return Structure::define($name, $fields, $description);
    }

    static private function makeSchemaFields(Schema $schema) : array {
        $fields = [];
        foreach ($schema->getPropertySchemas() as $propertyName => $propertySchema) {
            $typeDetails = $propertySchema->typeDetails();
            $fields[] = FieldFactory::fromTypeDetails($propertyName, $typeDetails, $propertySchema->description())->optional();
        }
        return $fields;
    }
}