<?php declare(strict_types=1);

namespace Cognesy\Dynamic;

use Cognesy\Schema\Data\ArrayShapeSchema;
use Cognesy\Schema\Data\ObjectSchema;
use Cognesy\Schema\Data\Schema;
use Cognesy\Schema\SchemaFactory;
use Symfony\Component\TypeInfo\Type;

final class StructureBuilder
{
    /** @var array<string, Field> */
    private array $fields;

    /** @param array<string, Field> $fields */
    public function __construct(
        private readonly string $name,
        private readonly string $description = '',
        array $fields = [],
    ) {
        $this->fields = $fields;
    }

    public static function define(string $name, string $description = '') : self {
        return new self($name, $description);
    }

    public function name() : string {
        return $this->name;
    }

    public function description() : string {
        return $this->description;
    }

    public function withField(Field $field) : self {
        $copy = $this->fields;
        $copy[$field->name()] = $field;
        return new self($this->name, $this->description, $copy);
    }

    /** @param array<array-key,mixed> $fields */
    public function withFields(array $fields) : self {
        $builder = $this;
        foreach ($fields as $field) {
            if (!$field instanceof Field) {
                continue;
            }
            $builder = $builder->withField($field);
        }
        return $builder;
    }

    public function string(string $name, string $description = '', bool $required = true) : self {
        return $this->withField(Field::string($name, $description)->optional(!$required));
    }

    public function int(string $name, string $description = '', bool $required = true) : self {
        return $this->withField(Field::int($name, $description)->optional(!$required));
    }

    public function float(string $name, string $description = '', bool $required = true) : self {
        return $this->withField(Field::float($name, $description)->optional(!$required));
    }

    public function bool(string $name, string $description = '', bool $required = true) : self {
        return $this->withField(Field::bool($name, $description)->optional(!$required));
    }

    public function array(string $name, string $description = '', bool $required = true) : self {
        return $this->withField(Field::array($name, $description)->optional(!$required));
    }

    /** @param class-string $enumClass */
    public function enum(string $name, string $enumClass, string $description = '', bool $required = true) : self {
        return $this->withField(Field::enum($name, $enumClass, $description)->optional(!$required));
    }

    /** @param array<string|int> $values */
    public function option(string $name, array $values, string $description = '', bool $required = true) : self {
        return $this->withField(Field::option($name, $values, $description)->optional(!$required));
    }

    /** @param class-string $class */
    public function object(string $name, string $class, string $description = '', bool $required = true) : self {
        return $this->withField(Field::object($name, $class, $description)->optional(!$required));
    }

    public function collection(string $name, string|Type|Structure $itemType, string $description = '', bool $required = true) : self {
        return $this->withField(Field::collection($name, $itemType, $description)->optional(!$required));
    }

    /** @param array<Field>|callable(self):(array<Field>|self)|self $fields */
    public function structure(string $name, array|callable|self $fields, string $description = '', bool $required = true) : self {
        $nested = match (true) {
            $fields instanceof self => $fields,
            is_array($fields) => self::define($name, $description)->withFields($fields),
            is_callable($fields) => $this->resolveCallableStructure($name, $description, $fields),
            default => throw new \InvalidArgumentException('Invalid nested structure definition'),
        };

        $schema = SchemaFactory::withMetadata(
            schema: $nested->schema(),
            name: $name,
            description: $description !== '' ? $description : $nested->description(),
        );
        return $this->withField(Field::fromSchema($name, $schema, $required));
    }

    /** @return array<string, Field> */
    public function fields() : array {
        return $this->fields;
    }

    public function schema() : Schema {
        $properties = [];
        $required = [];

        foreach ($this->fields as $field) {
            $properties[$field->name()] = $field->schema();
            if ($field->isRequired()) {
                $required[] = $field->name();
            }
        }

        return new ObjectSchema(
            type: Type::object(\stdClass::class),
            name: $this->name,
            description: $this->description,
            properties: $properties,
            required: $required,
        );
    }

    public function build(array $data = []) : Structure {
        return new Structure($this->schema(), $data, $this->fields);
    }

    public static function fromSchema(Schema $schema) : self {
        if (!$schema->hasProperties()) {
            return new self($schema->name(), $schema->description());
        }

        $requiredSet = [];
        if ($schema instanceof ObjectSchema || $schema instanceof ArrayShapeSchema) {
            $requiredSet = array_fill_keys($schema->required, true);
        }

        $fields = [];
        foreach ($schema->getPropertySchemas() as $propertyName => $propertySchema) {
            $fields[$propertyName] = Field::fromSchema(
                $propertyName,
                $propertySchema,
                isset($requiredSet[$propertyName]),
            );
        }

        return new self($schema->name(), $schema->description(), $fields);
    }

    /** @param callable(self):(array<Field>|self) $fields */
    private function resolveCallableStructure(string $name, string $description, callable $fields) : self {
        $initial = self::define($name, $description);
        $resolved = $fields($initial);

        return match (true) {
            $resolved instanceof self => $resolved,
            is_array($resolved) => $initial->withFields($resolved),
            default => throw new \InvalidArgumentException('Structure callback must return StructureBuilder or array<Field>.')
        };
    }
}
