<?php declare(strict_types=1);

namespace Cognesy\Schema;

use Cognesy\Schema\Contracts\CanParseJsonSchema;
use Cognesy\Schema\Data\ArraySchema;
use Cognesy\Schema\Data\ArrayShapeSchema;
use Cognesy\Schema\Data\CollectionSchema;
use Cognesy\Schema\Data\EnumSchema;
use Cognesy\Schema\Data\ObjectSchema;
use Cognesy\Schema\Data\ScalarSchema;
use Cognesy\Schema\Data\Schema;
use Cognesy\Schema\Exceptions\SchemaParsingException;
use Cognesy\Utils\JsonSchema\JsonSchema;
use Symfony\Component\TypeInfo\Type;

class JsonSchemaParser implements CanParseJsonSchema
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
        $schema = $this->parse(JsonSchema::fromArray($jsonSchema));

        $withMetadata = SchemaFactory::withMetadata(
            $schema,
            name: $customName !== '' ? $customName : null,
            description: $customDescription !== '' ? $customDescription : null,
        );

        if (!$withMetadata instanceof ObjectSchema) {
            $rootType = $jsonSchema['type'] ?? 'unknown';
            throw SchemaParsingException::forRootType(is_string($rootType) ? $rootType : 'unknown');
        }

        return $withMetadata;
    }

    #[\Override]
    public function parse(JsonSchema $jsonSchema) : ObjectSchema {
        if (!$jsonSchema->isObject()) {
            throw SchemaParsingException::forRootType($jsonSchema->type()->toString());
        }

        $name = $jsonSchema->title() ?: $this->defaultToolName;
        $description = $jsonSchema->description() ?: $this->defaultToolDescription;

        if ($jsonSchema->hasObjectClass()) {
            return new ObjectSchema(
                TypeInfo::fromJsonSchema($jsonSchema),
                $name,
                $description,
                $this->makeProperties($jsonSchema->properties()),
                $jsonSchema->requiredProperties(),
            );
        }

        // root without class -> preserve shape, but expose root as ObjectSchema for compatibility
        return new ObjectSchema(
            Type::object(),
            $name,
            $description,
            $this->makeProperties($jsonSchema->properties()),
            $jsonSchema->requiredProperties(),
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
            return new EnumSchema(TypeInfo::fromJsonSchema($json), $name, $json->description(), $json->enumValues());
        }

        if ($json->hasEnumValues()) {
            return new ScalarSchema(TypeInfo::fromJsonSchema($json), $name, $json->description(), $json->enumValues());
        }

        if ($json->isScalar()) {
            return $this->makeScalarProperty($name, $json);
        }

        throw SchemaParsingException::forRootType($json->type()->toString());
    }

    private function makeScalarProperty(string $name, JsonSchema $json) : ScalarSchema {
        return new ScalarSchema(
            TypeInfo::fromJsonSchema($json),
            $name,
            $json->description(),
            $json->enumValues(),
        );
    }

    private function makeCollectionProperty(string $name, JsonSchema $json) : CollectionSchema {
        $itemSchema = $json->itemSchema();
        if ($itemSchema === null) {
            throw SchemaParsingException::forMissingCollectionItems();
        }

        return new CollectionSchema(
            TypeInfo::fromJsonSchema($json),
            $name,
            $json->description(),
            $this->makePropertySchema('', $itemSchema),
        );
    }

    private function makeArrayProperty(string $name, JsonSchema $json) : ArraySchema {
        return new ArraySchema(
            TypeInfo::fromJsonSchema($json),
            $name,
            $json->description(),
        );
    }

    private function makeObjectProperty(string $name, JsonSchema $json) : ObjectSchema {
        return new ObjectSchema(
            TypeInfo::fromJsonSchema($json),
            $name,
            $json->description(),
            $this->makeProperties($json->properties()),
            $json->requiredProperties(),
        );
    }

    private function makeObjectAsArrayProperty(string $name, JsonSchema $json) : ArrayShapeSchema {
        return new ArrayShapeSchema(
            Type::array(),
            $name,
            $json->description(),
            $this->makeProperties($json->properties()),
            $json->requiredProperties(),
        );
    }
}
