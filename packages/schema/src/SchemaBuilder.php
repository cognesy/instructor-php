<?php declare(strict_types=1);

namespace Cognesy\Schema;

use Cognesy\Schema\Data\ArrayShapeSchema;
use Cognesy\Schema\Data\CollectionSchema;
use Cognesy\Schema\Data\ObjectSchema;
use Cognesy\Schema\Data\Schema;
use Symfony\Component\TypeInfo\Type;

final class SchemaBuilder
{
    private readonly SchemaFactory $schemaFactory;

    /** @param array<string, Schema> $properties
     *  @param array<string, true> $required
     */
    public function __construct(
        private readonly string $name,
        private readonly string $description = '',
        private readonly array $properties = [],
        private readonly array $required = [],
        ?SchemaFactory $schemaFactory = null,
    ) {
        $this->schemaFactory = $schemaFactory ?? SchemaFactory::default();
    }

    public static function define(string $name, string $description = '') : self {
        return new self($name, $description);
    }

    public static function fromSchema(Schema $schema) : self {
        if (!$schema->hasProperties()) {
            return new self($schema->name(), $schema->description());
        }

        $required = [];
        if ($schema instanceof ObjectSchema || $schema instanceof ArrayShapeSchema) {
            foreach ($schema->required as $propertyName) {
                $propertySchema = $schema->properties[$propertyName] ?? null;
                if ($propertySchema === null || $propertySchema->hasDefaultValue()) {
                    continue;
                }
                $required[$propertyName] = true;
            }
        }

        return new self(
            name: $schema->name(),
            description: $schema->description(),
            properties: $schema->getPropertySchemas(),
            required: $required,
        );
    }

    public function name() : string {
        return $this->name;
    }

    public function description() : string {
        return $this->description;
    }

    public function withProperty(string $name, Schema $schema, bool $required = true) : self {
        $propertySchema = SchemaFactory::withMetadata($schema, name: $name);
        $properties = $this->properties;
        $properties[$name] = $propertySchema;

        $requiredSet = $this->required;
        if (!$required || $propertySchema->hasDefaultValue()) {
            unset($requiredSet[$name]);
            return new self($this->name, $this->description, $properties, $requiredSet, $this->schemaFactory);
        }

        $requiredSet[$name] = true;
        return new self($this->name, $this->description, $properties, $requiredSet, $this->schemaFactory);
    }

    /** @param array<array-key, mixed> $properties */
    public function withProperties(array $properties) : self {
        $builder = $this;
        foreach ($properties as $name => $schema) {
            if (!$schema instanceof Schema) {
                continue;
            }

            $propertyName = is_string($name) ? $name : $schema->name();
            if ($propertyName === '') {
                continue;
            }

            $builder = $builder->withProperty($propertyName, $schema);
        }

        return $builder;
    }

    public function string(string $name, string $description = '', bool $required = true) : self {
        return $this->withProperty($name, $this->schemaFactory->string($name, $description), $required);
    }

    public function int(string $name, string $description = '', bool $required = true) : self {
        return $this->withProperty($name, $this->schemaFactory->int($name, $description), $required);
    }

    public function float(string $name, string $description = '', bool $required = true) : self {
        return $this->withProperty($name, $this->schemaFactory->float($name, $description), $required);
    }

    public function bool(string $name, string $description = '', bool $required = true) : self {
        return $this->withProperty($name, $this->schemaFactory->bool($name, $description), $required);
    }

    public function array(string $name, string $description = '', bool $required = true) : self {
        return $this->withProperty($name, $this->schemaFactory->array($name, $description), $required);
    }

    /** @param class-string $enumClass */
    public function enum(string $name, string $enumClass, string $description = '', bool $required = true) : self {
        return $this->withProperty($name, $this->schemaFactory->enum($enumClass, $name, $description), $required);
    }

    /** @param array<string|int> $values */
    public function option(string $name, array $values, string $description = '', bool $required = true) : self {
        $type = self::isIntEnum($values) ? Type::int() : Type::string();
        $schema = $this->schemaFactory->propertySchema($type, $name, $description, $values);
        return $this->withProperty($name, $schema, $required);
    }

    /** @param class-string $class */
    public function object(string $name, string $class, string $description = '', bool $required = true) : self {
        $schema = $this->schemaFactory->fromType(Type::object($class), $name, $description);
        return $this->withProperty($name, $schema, $required);
    }

    public function collection(string $name, string|Type|Schema $itemType, string $description = '', bool $required = true) : self {
        $schemaFactory = $this->schemaFactory;
        $schema = match (true) {
            is_string($itemType) => $schemaFactory->collection($itemType, $name, $description),
            $itemType instanceof Type => new CollectionSchema(
                type: Type::list(TypeInfo::normalize($itemType)),
                name: $name,
                description: $description,
                nestedItemSchema: $schemaFactory->fromType(TypeInfo::normalize($itemType), 'item', ''),
            ),
            $itemType instanceof Schema => new CollectionSchema(
                type: Type::list(TypeInfo::normalize($itemType->type())),
                name: $name,
                description: $description,
                nestedItemSchema: $itemType,
            ),
            default => throw new \InvalidArgumentException('Invalid collection item type: ' . get_debug_type($itemType)),
        };
        return $this->withProperty($name, $schema, $required);
    }

    /** @param callable(self):(self|array<array-key,mixed>)|self|array<array-key,mixed> $shape */
    public function shape(
        string $name,
        callable|self|array $shape,
        string $description = '',
        bool $required = true,
    ) : self {
        $nested = self::resolveShape($name, $description, $shape);
        $schema = SchemaFactory::withMetadata(
            schema: $nested->schema(),
            name: $name,
            description: $description !== '' ? $description : $nested->description(),
        );
        return $this->withProperty($name, $schema, $required);
    }

    /** @return array<string, Schema> */
    public function properties() : array {
        return $this->properties;
    }

    public function schema() : ObjectSchema {
        return new ObjectSchema(
            type: Type::object(\stdClass::class),
            name: $this->name,
            description: $this->description,
            properties: $this->properties,
            required: array_keys($this->required),
        );
    }

    public function build() : ObjectSchema {
        return $this->schema();
    }

    /** @param callable(self):(self|array<array-key,mixed>)|self|array<array-key,mixed> $shape */
    private static function resolveShape(string $name, string $description, callable|self|array $shape) : self {
        if ($shape instanceof self) {
            return $shape;
        }

        if (is_array($shape)) {
            return self::define($name, $description)->withProperties($shape);
        }

        $resolved = $shape(self::define($name, $description));
        return match (true) {
            $resolved instanceof self => $resolved,
            is_array($resolved) => self::define($name, $description)->withProperties($resolved),
            default => throw new \InvalidArgumentException('Shape callback must return SchemaBuilder or array<Schema>.'),
        };
    }

    /** @param array<string|int> $values */
    private static function isIntEnum(array $values) : bool {
        foreach ($values as $value) {
            if (is_int($value)) {
                continue;
            }
            return false;
        }

        return $values !== [];
    }
}
