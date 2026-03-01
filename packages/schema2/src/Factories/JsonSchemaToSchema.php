<?php declare(strict_types=1);

namespace Cognesy\Schema\Factories;

use Cognesy\Schema\Data\Schema\ArraySchema;
use Cognesy\Schema\Data\Schema\ArrayShapeSchema;
use Cognesy\Schema\Data\Schema\CollectionSchema;
use Cognesy\Schema\Data\Schema\EnumSchema;
use Cognesy\Schema\Data\Schema\ObjectSchema;
use Cognesy\Schema\Data\Schema\ScalarSchema;
use Cognesy\Schema\Data\Schema\Schema;
use Cognesy\Schema\Data\TypeDetails;
use Cognesy\Schema\Exceptions\SchemaParsingException;
use Cognesy\Utils\JsonSchema\JsonSchema;

class JsonSchemaToSchema
{
    public function __construct(
        private string $defaultToolName = 'extract_object',
        private string $defaultToolDescription = 'Extract data from chat content',
    ) {}

    public function fromJsonSchema(
        array $jsonSchema,
        string $customName = '',
        string $customDescription = '',
    ) : ObjectSchema {
        $json = JsonSchema::fromArray($jsonSchema);
        if (!$json->isObject()) {
            throw SchemaParsingException::forRootType($json->type()->toString());
        }

        $name = $customName !== '' ? $customName : ($json->title() ?: $this->defaultToolName);
        $description = $customDescription !== '' ? $customDescription : ($json->description() ?: $this->defaultToolDescription);

        if ($json->hasObjectClass()) {
            return new ObjectSchema(
                TypeDetails::fromJson($json),
                $name,
                $description,
                $this->makeProperties($json->properties()),
                $json->requiredProperties(),
            );
        }

        // root without class -> preserve shape, but expose root as ObjectSchema for compatibility
        return new ObjectSchema(
            TypeDetails::array(),
            $name,
            $description,
            $this->makeProperties($json->properties()),
            $json->requiredProperties(),
        );
    }

    /**
     * @param array<string, JsonSchema> $properties
     * @return array<string, Schema>
     */
    private function makeProperties(array $properties) : array {
        $result = [];
        foreach ($properties as $propertyName => $propertySchema) {
            $result[$propertyName] = $this->makePropertySchema($propertyName, $propertySchema);
        }

        return $result;
    }

    private function makePropertySchema(string $name, JsonSchema $json) : Schema {
        return match (true) {
            $json->isEnum() => $this->makeEnumOrOptionProperty($name, $json),
            $json->isObject() && $json->hasObjectClass() => $this->makeObjectProperty($name, $json),
            $json->isObject() => $this->makeObjectAsArrayProperty($name, $json),
            $json->isCollection() => $this->makeCollectionProperty($name, $json),
            $json->isArray() => $this->makeArrayProperty($name, $json),
            $json->isScalar() => $this->makeScalarProperty($name, $json),
            default => throw SchemaParsingException::forRootType($json->type()->toString()),
        };
    }

    private function makeEnumOrOptionProperty(string $name, JsonSchema $json) : Schema {
        if ($json->hasObjectClass()) {
            return new EnumSchema(TypeDetails::fromJson($json), $name, $json->description());
        }

        if ($json->hasEnumValues()) {
            return new ScalarSchema(TypeDetails::fromJson($json), $name, $json->description());
        }

        if ($json->isScalar()) {
            return $this->makeScalarProperty($name, $json);
        }

        throw SchemaParsingException::forRootType($json->type()->toString());
    }

    private function makeScalarProperty(string $name, JsonSchema $json) : ScalarSchema {
        return new ScalarSchema(
            TypeDetails::fromJson($json),
            $name,
            $json->description(),
        );
    }

    private function makeCollectionProperty(string $name, JsonSchema $json) : CollectionSchema {
        $itemSchema = $json->itemSchema();
        if ($itemSchema === null) {
            throw SchemaParsingException::forMissingCollectionItems();
        }

        return new CollectionSchema(
            TypeDetails::fromJson($json),
            $name,
            $json->description(),
            $this->makePropertySchema('', $itemSchema),
        );
    }

    private function makeArrayProperty(string $name, JsonSchema $json) : ArraySchema {
        return new ArraySchema(
            TypeDetails::fromJson($json),
            $name,
            $json->description(),
        );
    }

    private function makeObjectProperty(string $name, JsonSchema $json) : ObjectSchema {
        return new ObjectSchema(
            TypeDetails::fromJson($json),
            $name,
            $json->description(),
            $this->makeProperties($json->properties()),
            $json->requiredProperties(),
        );
    }

    private function makeObjectAsArrayProperty(string $name, JsonSchema $json) : ArrayShapeSchema {
        return new ArrayShapeSchema(
            TypeDetails::array(),
            $name,
            $json->description(),
            $this->makeProperties($json->properties()),
            $json->requiredProperties(),
        );
    }
}
