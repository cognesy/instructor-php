<?php declare(strict_types=1);

namespace Cognesy\Schema;

use Cognesy\Schema\Contracts\CanParseJsonSchema;
use Cognesy\Schema\Contracts\CanRenderJsonSchema;
use Cognesy\Schema\Contracts\CanProvideSchema;
use Cognesy\Schema\Data\ArrayShapeSchema;
use Cognesy\Schema\Data\ArraySchema;
use Cognesy\Schema\Data\CollectionSchema;
use Cognesy\Schema\Data\EnumSchema;
use Cognesy\Schema\Data\ObjectRefSchema;
use Cognesy\Schema\Data\ObjectSchema;
use Cognesy\Schema\Data\ScalarSchema;
use Cognesy\Schema\Data\Schema;
use Cognesy\Schema\Exceptions\SchemaMappingException;
use Cognesy\Schema\Reflection\ClassInfo;
use Cognesy\Schema\Reflection\PropertyInfo;
use Cognesy\Utils\JsonSchema\Contracts\CanProvideJsonSchema;
use Cognesy\Utils\JsonSchema\JsonSchema;
use Symfony\Component\TypeInfo\Type;

class SchemaFactory
{
    /** @var array<string, Schema> */
    private array $schemaCache = [];

    /** @var array<string, Schema> */
    private array $propertyCache = [];

    /** @var array<class-string, int> */
    private array $inlineObjectExpansionDepth = [];
    private CanParseJsonSchema $schemaConverter;
    private CanRenderJsonSchema $schemaRenderer;

    public function __construct(
        private bool $useObjectReferences = false,
        ?CanParseJsonSchema $schemaConverter = null,
        ?CanRenderJsonSchema $schemaRenderer = null,
    ) {
        $this->schemaConverter = $schemaConverter ?? new JsonSchemaParser();
        $this->schemaRenderer = $schemaRenderer ?? new JsonSchemaRenderer();
    }

    public static function default() : self {
        return new self();
    }

    public static function withMetadata(Schema $schema, ?string $name = null, ?string $description = null) : Schema {
        $resolvedName = $name ?? $schema->name;
        $resolvedDescription = $description ?? $schema->description;

        return match (true) {
            $schema instanceof ObjectSchema => new ObjectSchema(
                type: $schema->type,
                name: $resolvedName,
                description: $resolvedDescription,
                properties: $schema->properties,
                required: $schema->required,
            ),
            $schema instanceof ArrayShapeSchema => new ArrayShapeSchema(
                type: $schema->type,
                name: $resolvedName,
                description: $resolvedDescription,
                properties: $schema->properties,
                required: $schema->required,
            ),
            $schema instanceof CollectionSchema => new CollectionSchema(
                type: $schema->type,
                name: $resolvedName,
                description: $resolvedDescription,
                nestedItemSchema: $schema->nestedItemSchema,
            ),
            default => new ($schema::class)(
                $schema->type,
                $resolvedName,
                $resolvedDescription,
                $schema->enumValues,
            ),
        };
    }

    public function schema(mixed $anyType) : Schema
    {
        if ($anyType instanceof Schema) {
            return $anyType;
        }

        if ($anyType instanceof CanProvideSchema) {
            return $anyType->toSchema();
        }

        if ($anyType instanceof CanProvideJsonSchema) {
            return $this->schemaConverter->parse(JsonSchema::fromArray($anyType->toJsonSchema()));
        }

        $type = match (true) {
            $anyType instanceof Type => TypeInfo::normalize($anyType),
            is_string($anyType) => TypeInfo::fromTypeName($anyType),
            default => TypeInfo::fromValue($anyType),
        };

        $cacheKey = TypeInfo::cacheKey($type);
        if (!isset($this->schemaCache[$cacheKey])) {
            $this->schemaCache[$cacheKey] = $this->makeSchema($type);
        }

        return $this->schemaCache[$cacheKey];
    }

    /**
     * @param array<string|int>|null $enumValues
     */
    public function propertySchema(Type $type, string $name, string $description, ?array $enumValues = null) : Schema {
        return $this->makePropertySchema($type, $name, $description, $enumValues);
    }

    public function schemaParser() : CanParseJsonSchema {
        return $this->schemaConverter;
    }

    public function schemaRenderer() : CanRenderJsonSchema {
        return $this->schemaRenderer;
    }

    /**
     * @param callable(string):void|null $onObjectRef
     * @return array<string,mixed>
     */
    public function toJsonSchema(Schema $schema, ?callable $onObjectRef = null) : array {
        return $this->schemaRenderer->render($schema, $onObjectRef)->toArray();
    }

    /**
     * @param callable(string):void|null $onObjectRef
     */
    public function renderJsonSchema(Schema $schema, ?callable $onObjectRef = null) : JsonSchema {
        return $this->schemaRenderer->render($schema, $onObjectRef);
    }

    public function fromType(Type $type, string $name = '', string $description = '') : Schema {
        return $this->makePropertySchema(TypeInfo::normalize($type), $name, $description);
    }

    public function string(string $name = '', string $description = '') : ScalarSchema {
        return new ScalarSchema(Type::string(), $name, $description);
    }

    public function int(string $name = '', string $description = '') : ScalarSchema {
        return new ScalarSchema(Type::int(), $name, $description);
    }

    public function float(string $name = '', string $description = '') : ScalarSchema {
        return new ScalarSchema(Type::float(), $name, $description);
    }

    public function bool(string $name = '', string $description = '') : ScalarSchema {
        return new ScalarSchema(Type::bool(), $name, $description);
    }

    public function array(string $name = '', string $description = '') : ArraySchema {
        return new ArraySchema(Type::array(), $name, $description);
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
            Type::object($class),
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
        return new EnumSchema(
            Type::enum($class),
            $name,
            $description,
            TypeInfo::enumValues(Type::enum($class)),
        );
    }

    public function collection(string $nestedType, string $name = '', string $description = '', ?Schema $nestedTypeSchema = null) : CollectionSchema {
        $nestedTypeInfo = TypeInfo::fromTypeName($nestedType);
        $nestedSchema = $nestedTypeSchema ?? $this->makeSchema($nestedTypeInfo);

        return new CollectionSchema(
            Type::list($nestedTypeInfo),
            $name,
            $description,
            $nestedSchema,
        );
    }

    public function fromClassInfo(ClassInfo $classInfo) : ObjectSchema {
        return new ObjectSchema(
            Type::object($classInfo->getClass()),
            $classInfo->getClass(),
            $classInfo->getClassDescription(),
            $this->getPropertySchemas($classInfo),
            $classInfo->getRequiredProperties(),
        );
    }

    public function fromPropertyInfo(PropertyInfo $propertyInfo) : Schema {
        return $this->makePropertySchema(
            $propertyInfo->getType(),
            $propertyInfo->getName(),
            $propertyInfo->getDescription(),
        );
    }

    // INTERNALS //////////////////////////////////////////////////////////////////////////////

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

    private function makeSchema(Type $type) : Schema {
        $type = TypeInfo::normalize($type);
        $className = TypeInfo::className($type);

        return match (true) {
            TypeInfo::isCollection($type) => new CollectionSchema(
                type: $type,
                name: '',
                description: '',
                nestedItemSchema: $this->makeNestedItemSchema(
                    TypeInfo::collectionValueType($type) ?? Type::mixed(),
                    'item',
                    'Correctly extract items of type: ' . TypeInfo::shortName(TypeInfo::collectionValueType($type) ?? Type::mixed()),
                ),
            ),
            TypeInfo::isObject($type) && !TypeInfo::isEnum($type) => $this->makeObjectSchema(
                $type,
                name: $className !== null ? TypeInfo::shortName($type) : 'object',
                description: $this->classDescription($className),
                allowReference: false,
            ),
            TypeInfo::isEnum($type) => new EnumSchema(
                type: $type,
                name: $className ?? '',
                description: $this->classDescription($className),
                enumValues: TypeInfo::enumValues($type),
            ),
            TypeInfo::isArray($type) => new ArraySchema($type),
            TypeInfo::isScalar($type) => new ScalarSchema($type, 'value', 'Correctly extracted value'),
            TypeInfo::isMixed($type) => new Schema($type, 'value', 'Correctly extracted value'),
            default => throw SchemaMappingException::unknownSchemaType((string) $type),
        };
    }

    /**
     * @param array<string|int>|null $enumValues
     */
    private function makePropertySchema(Type $type, string $name, string $description, ?array $enumValues = null) : Schema {
        $type = TypeInfo::normalize($type);

        return match (true) {
            TypeInfo::isCollection($type) => new CollectionSchema(
                $type,
                $name,
                $description,
                $this->makeNestedItemSchema(
                    TypeInfo::collectionValueType($type) ?? Type::mixed(),
                    'item',
                    'Correctly extract items of type: ' . TypeInfo::shortName(TypeInfo::collectionValueType($type) ?? Type::mixed()),
                ),
            ),
            TypeInfo::isEnum($type) => new EnumSchema($type, $name, $description, $enumValues ?? TypeInfo::enumValues($type)),
            TypeInfo::isObject($type) => $this->makeObjectSchema($type, $name, $description, allowReference: true),
            TypeInfo::isScalar($type) => new ScalarSchema($type, $name, $description, $enumValues),
            TypeInfo::isArray($type) => new ArraySchema($type, $name, $description),
            TypeInfo::isMixed($type) => new Schema($type, $name, $description),
            default => throw SchemaMappingException::unknownSchemaType((string) $type),
        };
    }

    private function makeObjectSchema(Type $type, string $name, string $description, bool $allowReference) : Schema {
        if ($this->useObjectReferences && $allowReference) {
            return new ObjectRefSchema($type, $name, $description);
        }

        $className = TypeInfo::className($type);
        if ($className === null || !class_exists($className)) {
            return new ObjectSchema($type, $name, $description, [], []);
        }

        $depth = $this->inlineObjectExpansionDepth[$className] ?? 0;
        if ($depth >= 2) {
            return new ObjectSchema($type, $name, $description, [], []);
        }

        $this->inlineObjectExpansionDepth[$className] = $depth + 1;
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
            if ($depth === 0) {
                unset($this->inlineObjectExpansionDepth[$className]);
            } else {
                $this->inlineObjectExpansionDepth[$className] = $depth;
            }
        }
    }

    private function makeNestedItemSchema(Type $type, string $name, string $description) : Schema {
        $type = TypeInfo::normalize($type);
        if ($type instanceof \Symfony\Component\TypeInfo\Type\CollectionType || $type->isIdentifiedBy(\Symfony\Component\TypeInfo\TypeIdentifier::ARRAY)) {
            throw SchemaMappingException::invalidCollectionNestedType((string) $type);
        }

        return match (true) {
            TypeInfo::isObject($type) => $this->makeObjectSchema($type, $name, $description, allowReference: true),
            TypeInfo::isEnum($type) => new EnumSchema($type, $name, $description, TypeInfo::enumValues($type)),
            TypeInfo::isScalar($type) => new ScalarSchema($type, $name, $description),
            TypeInfo::isMixed($type) => new Schema($type, $name, $description),
            default => throw SchemaMappingException::unknownSchemaType((string) $type),
        };
    }

    private function classDescription(?string $className) : string {
        if ($className === null || !class_exists($className)) {
            return '';
        }

        return ClassInfo::fromString($className)->getClassDescription();
    }
}
