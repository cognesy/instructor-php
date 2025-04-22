<?php
namespace Cognesy\Instructor\Features\Schema\Factories;

use Cognesy\Instructor\Extras\Structure\Structure;
use Cognesy\Instructor\Features\Schema\Data\Schema\ObjectSchema;
use Exception;

/**
 * Builds Schema object from JSON Schema array
 *
 * Used by ResponseModel to derive schema from raw JSON Schema format,
 * when full processing customization is needed.
 *
 * Requires x-php-class field to contain the target class name for each
 * object and enum type.
 */
class JsonSchemaToSchema
{
    use Traits\JsonSchemaToSchema\HandlesMakers;

    private $defaultToolName = 'extract_object';
    private $defaultToolDescription = 'Extract data from chat content';

    /**
     * Create Schema object from given JSON Schema array
     */
    public function fromJsonSchema(
        array $jsonSchema,
        string $customName = '',
        string $customDescription = '',
    ) : ObjectSchema {
        $class = $jsonSchema['x-php-class'] ?? Structure::class; // throw new Exception('Object must have x-php-class field with the target class name');
        $type = $jsonSchema['type'] ?? null;
        if ($type !== 'object') {
            throw new Exception('JSON Schema must have type: object');
        }
        $factory = new TypeDetailsFactory();
        return new ObjectSchema(
            type: $factory->objectType($class),
            name: $customName ?? ($jsonSchema['x-title'] ?? $this->defaultToolName),
            description: $customDescription ?? ($jsonSchema['description'] ?? $this->defaultToolDescription),
            properties: $this->makeProperties($jsonSchema['properties'] ?? []),
            required: $jsonSchema['required'] ?? [],
        );
    }
}
