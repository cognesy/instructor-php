<?php declare(strict_types=1);

namespace Cognesy\Schema\Factories;

use Cognesy\Schema\Contracts\CanProvideSchema;
use Cognesy\Schema\Data\Schema\ArraySchema;
use Cognesy\Schema\Data\Schema\CollectionSchema;
use Cognesy\Schema\Data\Schema\EnumSchema;
use Cognesy\Schema\Data\Schema\ObjectRefSchema;
use Cognesy\Schema\Data\Schema\ObjectSchema;
use Cognesy\Schema\Data\Schema\ScalarSchema;
use Cognesy\Schema\Data\Schema\Schema;
use Cognesy\Schema\Data\TypeDetails;
use Cognesy\Schema\Exceptions\SchemaMappingException;
use Cognesy\Schema\Reflection\ClassInfo;
use Cognesy\Schema\Reflection\PropertyInfo;
use Cognesy\Utils\JsonSchema\Contracts\CanProvideJsonSchema;

class SchemaFactory
{
    /** @var array<string, Schema> */
    private array $schemaCache = [];

    /** @var array<string, Schema> */
    private array $propertyCache = [];

    /** @var array<class-string, bool> */
    private array $inlineObjectExpansionStack = [];

    public function __construct(
        private bool $useObjectReferences = false,
        private ?JsonSchemaToSchema $schemaConverter = null,
    ) {
        $this->schemaConverter = $schemaConverter ?? new JsonSchemaToSchema();
    }

    public function schema(string|object $anyType) : Schema
    {
        if ($anyType instanceof Schema) {
            return $anyType;
        }

        if ($anyType instanceof CanProvideSchema) {
            return $anyType->toSchema();
        }

        if ($anyType instanceof CanProvideJsonSchema) {
            return $this->schemaConverter->fromJsonSchema($anyType->toJsonSchema());
        }

        $type = match (true) {
            $anyType instanceof TypeDetails => $anyType,
            is_string($anyType) => TypeDetails::fromTypeName($anyType),
            default => TypeDetails::fromTypeName($anyType::class),
        };

        $cacheKey = (string) $type;
        if (!isset($this->schemaCache[$cacheKey])) {
            $this->schemaCache[$cacheKey] = $this->makeSchema($type);
        }

        return $this->schemaCache[$cacheKey];
    }

    public function propertySchema(TypeDetails $type, string $name, string $description) : Schema {
        return $this->makePropertySchema($type, $name, $description);
    }

    public function string(string $name = '', string $description = '') : ScalarSchema {
        return new ScalarSchema(TypeDetails::string(), $name, $description);
    }

    public function int(string $name = '', string $description = '') : ScalarSchema {
        return new ScalarSchema(TypeDetails::int(), $name, $description);
    }

    public function float(string $name = '', string $description = '') : ScalarSchema {
        return new ScalarSchema(TypeDetails::float(), $name, $description);
    }

    public function bool(string $name = '', string $description = '') : ScalarSchema {
        return new ScalarSchema(TypeDetails::bool(), $name, $description);
    }

    public function array(string $name = '', string $description = '') : ArraySchema {
        return new ArraySchema(TypeDetails::array(), $name, $description);
    }

    /**
     * @param class-string $class
     * @param array<string, Schema> $properties
     * @param array<string> $required
     */
    public function object(string $class, string $name = '', string $description = '', array $properties = [], array $required = []) : ObjectSchema {
        $classInfo = ClassInfo::fromString($class);
        $resolvedProperties = $properties !== [] ? $properties : $this->getPropertySchemas($classInfo);
        $resolvedRequired = $required !== [] ? $required : $classInfo->getRequiredProperties();

        return new ObjectSchema(
            TypeDetails::object($class),
            $name !== '' ? $name : $classInfo->getClass(),
            $description !== '' ? $description : $classInfo->getClassDescription(),
            $resolvedProperties,
            $resolvedRequired,
        );
    }

    /**
     * @param class-string $class
     */
    public function enum(string $class, string $name = '', string $description = '') : EnumSchema {
        return new EnumSchema(TypeDetails::enum($class), $name, $description);
    }

    public function collection(string $nestedType, string $name = '', string $description = '', ?Schema $nestedTypeSchema = null) : CollectionSchema {
        $nestedTypeDetails = TypeDetails::fromTypeName($nestedType);
        $nestedSchema = $nestedTypeSchema ?? $this->makeSchema($nestedTypeDetails);

        return new CollectionSchema(
            TypeDetails::collection($nestedTypeDetails->toString()),
            $name,
            $description,
            $nestedSchema,
        );
    }

    public function fromClassInfo(ClassInfo $classInfo) : ObjectSchema {
        return new ObjectSchema(
            TypeDetails::fromTypeName($classInfo->getClass()),
            $classInfo->getClass(),
            $classInfo->getClassDescription(),
            $this->getPropertySchemas($classInfo),
            $classInfo->getRequiredProperties(),
        );
    }

    public function fromPropertyInfo(PropertyInfo $propertyInfo) : Schema {
        return $this->makePropertySchema(
            $propertyInfo->getTypeDetails(),
            $propertyInfo->getName(),
            $propertyInfo->getDescription(),
        );
    }

    /** @return array<string, Schema> */
    private function getPropertySchemas(ClassInfo $classInfo) : array {
        $propertySchemas = [];
        foreach ($classInfo->getProperties() as $propertyName => $propertyInfo) {
            if (!$propertyInfo->isDeserializable()) {
                continue;
            }

            $cacheKey = $propertyInfo->getClass() . '::' . $propertyName;
            if (!isset($this->propertyCache[$cacheKey])) {
                $this->propertyCache[$cacheKey] = $this->fromPropertyInfo($propertyInfo);
            }

            $propertySchemas[$propertyName] = $this->propertyCache[$cacheKey];
        }

        return $propertySchemas;
    }

    private function makeSchema(TypeDetails $type) : Schema {
        $classInfo = $type->hasClass() ? ClassInfo::fromString($type->class() ?? '') : null;

        return match (true) {
            $type->isObject() && $classInfo !== null => new ObjectSchema(
                type: $type,
                name: $type->classOnly(),
                description: $classInfo->getClassDescription(),
                properties: $this->getPropertySchemas($classInfo),
                required: $classInfo->getRequiredProperties(),
            ),
            $type->isEnum() => new EnumSchema(
                type: $type,
                name: $type->class() ?? '',
                description: $classInfo?->getClassDescription() ?? '',
            ),
            $type->isCollection() => new CollectionSchema(
                type: $type,
                name: '',
                description: '',
                nestedItemSchema: $this->makePropertySchema($type, 'item', 'Correctly extract items of type: ' . ($type->nestedType?->shortName() ?? 'mixed')),
            ),
            $type->isArray() => new ArraySchema($type),
            $type->isScalar() => new ScalarSchema($type, 'value', 'Correctly extracted value'),
            $type->isMixed() => new Schema($type, 'value', 'Correctly extracted value'),
            default => throw SchemaMappingException::unknownSchemaType($type->type),
        };
    }

    private function makePropertySchema(TypeDetails $type, string $name, string $description) : Schema {
        return match (true) {
            $type->isEnum() => new EnumSchema($type, $name, $description),
            $type->isObject() => $this->makeObjectSchema($type, $name, $description),
            $type->isCollection() => new CollectionSchema(
                $type,
                $name,
                $description,
                $this->makeNestedItemSchema(
                    $type->nestedType ?? TypeDetails::mixed(),
                    'item',
                    'Correctly extract items of type: ' . ($type->nestedType?->shortName() ?? 'mixed'),
                ),
            ),
            $type->isScalar() => new ScalarSchema($type, $name, $description),
            $type->isArray() => new ArraySchema($type, $name, $description),
            $type->isMixed() => new Schema($type, $name, $description),
            default => throw SchemaMappingException::unknownSchemaType($type->toString()),
        };
    }

    private function makeObjectSchema(TypeDetails $type, string $name, string $description) : Schema {
        if ($this->useObjectReferences) {
            return new ObjectRefSchema($type, $name, $description);
        }

        $className = $type->class() ?? throw SchemaMappingException::missingObjectClass();
        if (isset($this->inlineObjectExpansionStack[$className])) {
            return new ObjectSchema($type, $name, $description, [], []);
        }

        $this->inlineObjectExpansionStack[$className] = true;
        try {
            $classInfo = ClassInfo::fromString($className);
            return new ObjectSchema(
                $type,
                $name,
                $description,
                $this->getPropertySchemas($classInfo),
                $classInfo->getRequiredProperties(),
            );
        } finally {
            unset($this->inlineObjectExpansionStack[$className]);
        }
    }

    private function makeNestedItemSchema(TypeDetails $type, string $name, string $description) : Schema {
        return match (true) {
            $type->isObject() => $this->makeObjectSchema($type, $name, $description),
            $type->isEnum() => new EnumSchema($type, $name, $description),
            $type->isCollection(), $type->isArray() => throw SchemaMappingException::invalidCollectionNestedType($type->toString()),
            $type->isScalar() => new ScalarSchema($type, $name, $description),
            $type->isMixed() => new Schema($type, $name, $description),
            default => throw SchemaMappingException::unknownSchemaType($type->toString()),
        };
    }
}
