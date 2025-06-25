<?php

namespace Cognesy\Schema\Factories;

use Cognesy\Schema\Contracts\CanProvideSchema;
use Cognesy\Schema\Data\Schema\Schema;
use Cognesy\Schema\Data\TypeDetails;
use Cognesy\Schema\PropertyMap;
use Cognesy\Schema\SchemaMap;
use Cognesy\Utils\JsonSchema\Contracts\CanProvideJsonSchema;

/**
 * Factory for creating schema objects from class names
 *
 * NOTE: Currently, OpenAI models do not comprehend well object references for
 * complex structures, so it's safer to return the full object schema with all
 * properties inlined.
 *
 */
class SchemaFactory
{
    use Traits\SchemaFactory\HandlesClassInfo;
    use Traits\SchemaFactory\HandlesFactoryMethods;
    use Traits\SchemaFactory\HandlesTypeDetails;

    /** @var bool switches schema rendering between inlined or referenced object properties */
    protected bool $useObjectReferences;

    protected SchemaMap $schemaMap;
    protected PropertyMap $propertyMap;
    protected JsonSchemaToSchema $schemaConverter;

    public function __construct(
        bool $useObjectReferences = false,
        ?JsonSchemaToSchema $schemaConverter = null,
    ) {
        $this->useObjectReferences = $useObjectReferences;
        //
        $this->schemaMap = new SchemaMap;
        $this->propertyMap = new PropertyMap;
        $this->schemaConverter = $schemaConverter ?? new JsonSchemaToSchema;
    }

    /**
     * Extracts the schema from a class and constructs a function call
     *
     * @param string $anyType - class name, enum name or type name string OR TypeDetails object OR any object instance
     */
    public function schema(string|object $anyType) : Schema
    {
        if ($anyType instanceof Schema) {
            // if schema is already provided, return it
            return $anyType;
        }

        // if anyType is a dynamic schema provider, throw an exception
        // to prevent using it directly, as it should be used via its own schema method
        if ($anyType instanceof CanProvideSchema) {
            return $anyType->schema();
        }

        // if anyType is a JSON schema provider, convert it to Schema
        if ($anyType instanceof CanProvideJsonSchema) {
            // if anyType is a JSON schema provider, convert it to Schema
            return $this->schemaConverter->fromJsonSchema($anyType->toJsonSchema());
        }

        $type = match(true) {
            $anyType instanceof TypeDetails => $anyType,
            is_string($anyType) => TypeDetails::fromTypeName($anyType),
            is_object($anyType) => TypeDetails::fromTypeName(get_class($anyType)),
            default => throw new \Exception('Unknown input type: '.gettype($anyType)),
        };

        $typeString = (string) $type;

        // if schema is not registered, create it, register it and return it
        if (!$this->schemaMap->has($type)) {
            $this->schemaMap->register(
                typeName: $typeString,
                schema: $this->makeSchema($type));
        }

        return $this->schemaMap->get($anyType);
    }

    /**
     * Creates schema for a property with provided parameters
     *
     * @param TypeDetails $type
     * @param string $name
     * @param string $description
     * @return Schema
     */
    public function propertySchema(TypeDetails $type, string $name, string $description) : Schema {
        return $this->makePropertySchema($type, $name, $description);
    }
}
