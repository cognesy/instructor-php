<?php

namespace Cognesy\Schema\Factories;

use Cognesy\Schema\Data\Schema\ObjectSchema;
use Exception;

/**
 * Builds Schema object from JSON Schema array
 *
 * Used by ResponseModel to derive schema from raw JSON Schema format,
 * when full processing customization is needed.
 *
 * Requires x-php-class field to contain the target class name for each
 * object and enum type, otherwise it will use the default class.
 */
class JsonSchemaToSchema
{
    use \Cognesy\Schema\Factories\Traits\JsonSchemaToSchema\HandlesMakers;

    private $defaultToolName;
    private $defaultToolDescription;
    private $defaultOutputClass;

    public function __construct(
        string $defaultToolName = 'extract_object',
        string $defaultToolDescription = 'Extract data from chat content',
        string $defaultOutputClass = '',
    ) {
        $this->defaultToolName = $defaultToolName;
        $this->defaultToolDescription = $defaultToolDescription;
        $this->defaultOutputClass = $defaultOutputClass;
    }

    /**
     * Create Schema object from given JSON Schema array
     */
    public function fromJsonSchema(
        array $jsonSchema,
        string $customName = '',
        string $customDescription = '',
    ) : ObjectSchema {
        $class = $jsonSchema['x-php-class'] ?? $this->defaultOutputClass;
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
