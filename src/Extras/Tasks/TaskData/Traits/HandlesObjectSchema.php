<?php

namespace Cognesy\Instructor\Extras\Tasks\TaskData\Traits;

use Cognesy\Instructor\Contracts\CanProvideSchema;
use Cognesy\Instructor\Schema\Data\Schema\ObjectSchema;
use Cognesy\Instructor\Schema\Data\Schema\Schema;
use Cognesy\Instructor\Schema\Factories\SchemaFactory;
use Exception;

trait HandlesObjectSchema
{
    private function getObjectPropertySchema(object $object, string $name) : Schema {
        $schema = $this->getObjectSchema($object);
        if (!isset($schema->properties[$name])) {
            throw new Exception("Property '$name' not found");
        }
        return $schema->properties[$name];
    }

    /** @return Schema[] */
    private function getObjectSchemas(object $object, array $allowedNames) : array {
        return array_filter(
            $this->getObjectSchema($object)->properties,
            fn(Schema $schema) => in_array($schema->name(), $allowedNames)
        );
    }

    private function getObjectSchema(object $object) : ObjectSchema {
        // Objects are we expecting here: Structure, AutoSignature, or
        // any object generated for ResponseModel (e.g. Scalar, Sequence).
        //
        // We want to use CanProvideSchema if it's available, as it allows
        // the object to shape its own schema (see: Structure, Sequence,
        // Scalar, etc.).
        $schema = match(true) {
            $object instanceof CanProvideSchema => $object->toSchema(),
            default => (new SchemaFactory)->schema($object),
        };
        if (!$schema instanceof ObjectSchema) {
            throw new Exception("Expected an object schema, got a " . get_class($schema));
        }
        return $schema;
    }
}