<?php declare(strict_types=1);

namespace Cognesy\Dynamic;

use Cognesy\Instructor\Deserialization\Deserializers\SymfonyDeserializer;
use Cognesy\Instructor\Transformation\Contracts\CanTransformSelf;
use Cognesy\Instructor\Validation\Contracts\CanValidateSelf;
use Cognesy\Instructor\Validation\ValidationError;
use Cognesy\Instructor\Validation\ValidationResult;
use Cognesy\Schema\Contracts\CanProvideSchema;
use Cognesy\Schema\Data\ArrayShapeSchema;
use Cognesy\Schema\Data\CollectionSchema;
use Cognesy\Schema\Data\ObjectSchema;
use Cognesy\Schema\Data\Schema;
use Cognesy\Schema\SchemaFactory;
use Cognesy\Schema\TypeInfo;
use Symfony\Component\TypeInfo\TypeIdentifier;

final class Structure implements CanProvideSchema, CanValidateSelf, CanTransformSelf
{
    /** @var array<string,mixed> */
    private array $data;
    /** @var array<string, Field> */
    private array $fields;
    /** @var (callable(self):(bool|ValidationResult))|null */
    private $validator = null;

    /** @param array<string,mixed> $data @param array<string, Field> $fields */
    public function __construct(
        private readonly Schema $schema,
        array $data = [],
        array $fields = [],
    ) {
        $this->fields = $fields !== [] ? $fields : self::fieldMapFromSchema($schema);
        $this->data = $this->normalizeRecord($data);
    }

    /** @param array<Field>|callable(StructureBuilder):(array<Field>|StructureBuilder) $fields */
    public static function define(string $name, array|callable $fields, string $description = '') : self {
        $builder = StructureBuilder::define($name, $description);

        $resolved = match (true) {
            is_array($fields) => $builder->withFields($fields),
            is_callable($fields) => $fields($builder),
            default => throw new \InvalidArgumentException('Structure fields must be array<Field> or callable'),
        };

        return match (true) {
            $resolved instanceof StructureBuilder => $resolved->build(),
            is_array($resolved) => $builder->withFields($resolved)->build(),
            default => throw new \InvalidArgumentException('Structure callback must return StructureBuilder or array<Field>.'),
        };
    }

    public static function fromSchema(Schema $schema, array $data = []) : self {
        $builder = StructureBuilder::fromSchema($schema);
        return new self($schema, $data, $builder->fields());
    }

    public static function builder(string $name, string $description = '') : StructureBuilder {
        return StructureBuilder::define($name, $description);
    }

    public function schema() : Schema {
        return $this->schema;
    }

    public function name() : string {
        return $this->schema->name();
    }

    public function description() : string {
        return $this->schema->description();
    }

    #[\Override]
    public function toSchema() : Schema {
        return $this->schema();
    }

    /** @return array<string,mixed> */
    public function data() : array {
        return $this->data;
    }

    /** @return array<string,mixed> */
    public function toArray() : array {
        return $this->data;
    }

    /** @return array<string,mixed> */
    public function toJsonSchema() : array {
        $jsonSchema = SchemaFactory::default()->toJsonSchema($this->schema());
        $jsonSchema['x-php-class'] = self::class;
        return $jsonSchema;
    }

    public function withData(array $data) : self {
        $copy = clone $this;
        $copy->data = $copy->normalizeRecord($data);
        return $copy;
    }

    /** @param callable(self): (bool|ValidationResult) $validator */
    public function withValidation(callable $validator) : self {
        $copy = clone $this;
        $copy->validator = $validator;
        return $copy;
    }

    /**
     * Transitional helper used by existing runtime callsites.
     *
     * @param array<string,mixed> $values
     * @return array<string,mixed>
     */
    public function normalizeRecord(array $values) : array {
        if (!$this->schema->hasProperties()) {
            return $values;
        }

        $normalized = [];
        foreach ($this->fields as $name => $field) {
            if (array_key_exists($name, $values)) {
                $normalized[$name] = $this->normalizeValue($field->schema(), $values[$name]);
                continue;
            }

            if ($field->hasDefaultValue()) {
                $normalized[$name] = $field->defaultValue();
            }
        }

        return $normalized;
    }

    /** @return Field[] */
    public function fields() : array {
        return array_values($this->fields);
    }

    public function field(string $name) : Field {
        if (!isset($this->fields[$name])) {
            throw new \InvalidArgumentException('Field not found: ' . $name);
        }

        return $this->fields[$name];
    }

    public function has(string $name) : bool {
        return isset($this->fields[$name]);
    }

    public function get(string $name) : mixed {
        if (!isset($this->fields[$name])) {
            throw new \InvalidArgumentException('Field not found: ' . $name);
        }

        if (array_key_exists($name, $this->data)) {
            return $this->data[$name];
        }

        return $this->fields[$name]->defaultValue();
    }

    public function set(string $name, mixed $value) : self {
        if (!isset($this->fields[$name])) {
            throw new \InvalidArgumentException('Field not found: ' . $name);
        }

        $copy = clone $this;
        $copy->data[$name] = $this->normalizeValue($this->fields[$name]->schema(), $value);
        return $copy;
    }

    #[\Override]
    public function validate() : ValidationResult {
        $errors = [];
        $this->validateSchemaData($this->schema, $this->data, '', $errors);

        foreach ($this->fields as $name => $field) {
            if (!array_key_exists($name, $this->data)) {
                continue;
            }

            $fieldValidation = $field->validate($this->data[$name]);
            if ($fieldValidation->isValid()) {
                continue;
            }

            foreach ($fieldValidation->getErrors() as $error) {
                $errors[] = $error;
            }
        }

        if ($this->validator !== null) {
            $validation = ($this->validator)($this);
            if ($validation instanceof ValidationResult && $validation->isInvalid()) {
                foreach ($validation->getErrors() as $error) {
                    $errors[] = $error;
                }
            }
            if ($validation === false) {
                $errors[] = new ValidationError('structure', $this->data, 'Structure-level validation failed.');
            }
        }

        return $errors === []
            ? ValidationResult::valid()
            : ValidationResult::invalid($errors, 'Structure validation failed');
    }

    public function fromArray(array $data) : static {
        return $this->withData($data);
    }

    #[\Override]
    public function transform() : mixed {
        if (count($this->data) === 1) {
            return array_values($this->data)[0];
        }

        return $this->toArray();
    }

    public function clone() : self {
        return clone $this;
    }

    public function __get(string $name) : mixed {
        return $this->get($name);
    }

    public function __set(string $name, mixed $value) : void {
        if (!isset($this->fields[$name])) {
            throw new \InvalidArgumentException('Field not found: ' . $name);
        }

        throw new \BadMethodCallException('Structure is immutable. Use set() and reassign returned instance.');
    }

    public function __isset(string $name) : bool {
        return isset($this->data[$name]) || isset($this->fields[$name]);
    }

    /** @param array<ValidationError> $errors */
    private function validateSchemaData(Schema $schema, mixed $value, string $path, array &$errors) : void {
        if ($schema instanceof ObjectSchema || $schema instanceof ArrayShapeSchema) {
            if (!is_array($value)) {
                $errors[] = new ValidationError($path === '' ? 'root' : $path, $value, 'Expected object/associative array.');
                return;
            }

            $required = $schema->required;
            foreach ($required as $requiredField) {
                if (array_key_exists($requiredField, $value)) {
                    continue;
                }
                $errors[] = new ValidationError(self::path($path, $requiredField), null, 'Missing required field.');
            }

            foreach ($schema->getPropertySchemas() as $propertyName => $propertySchema) {
                if (!array_key_exists($propertyName, $value)) {
                    continue;
                }
                $this->validateSchemaData($propertySchema, $value[$propertyName], self::path($path, $propertyName), $errors);
            }

            return;
        }

        if ($schema instanceof CollectionSchema) {
            if (!is_array($value)) {
                $errors[] = new ValidationError($path, $value, 'Expected collection array.');
                return;
            }

            foreach ($value as $index => $item) {
                $this->validateSchemaData($schema->nestedItemSchema, $item, self::path($path, (string) $index), $errors);
            }

            return;
        }

        if ($value === null) {
            return;
        }

        $type = $schema->type();
        if (TypeInfo::isEnum($type)) {
            $allowed = $schema->enumValues ?? TypeInfo::enumValues($type);
            if ($allowed === [] || in_array($value, $allowed, true)) {
                return;
            }

            $errors[] = new ValidationError($path, $value, 'Value is not in enum/options list.');
            return;
        }

        if ($type->isIdentifiedBy(TypeIdentifier::INT) && !is_int($value)) {
            $errors[] = new ValidationError($path, $value, 'Expected integer.');
            return;
        }

        if ($type->isIdentifiedBy(TypeIdentifier::FLOAT) && !is_float($value) && !is_int($value)) {
            $errors[] = new ValidationError($path, $value, 'Expected float.');
            return;
        }

        if (TypeInfo::isBool($type) && !is_bool($value)) {
            $errors[] = new ValidationError($path, $value, 'Expected boolean.');
            return;
        }

        if ($type->isIdentifiedBy(TypeIdentifier::STRING) && !is_string($value)) {
            $errors[] = new ValidationError($path, $value, 'Expected string.');
            return;
        }

        if (TypeInfo::isArray($type) && !is_array($value)) {
            $errors[] = new ValidationError($path, $value, 'Expected array.');
            return;
        }

        if (TypeInfo::isObject($type) && !is_array($value) && !is_object($value)) {
            $errors[] = new ValidationError($path, $value, 'Expected object-compatible value.');
        }
    }

    private function normalizeValue(Schema $schema, mixed $value) : mixed {
        if ($value === null) {
            return null;
        }

        if ($value instanceof self) {
            return $value->toArray();
        }

        if ($schema instanceof CollectionSchema) {
            if (!is_array($value)) {
                return $value;
            }

            $normalized = [];
            foreach ($value as $item) {
                $normalized[] = $this->normalizeValue($schema->nestedItemSchema, $item);
            }
            return $normalized;
        }

        if (($schema instanceof ObjectSchema || $schema instanceof ArrayShapeSchema) && is_array($value)) {
            $className = TypeInfo::className($schema->type());
            if ($className !== null && $className !== self::class && $className !== \stdClass::class && class_exists($className)) {
                try {
                    return (new SymfonyDeserializer())->fromArray($value, $className);
                } catch (\Throwable) {
                    // fall back to normalized array representation
                }
            }

            $nested = new self($schema, $value, self::fieldMapFromSchema($schema));
            return $nested->toArray();
        }

        if (TypeInfo::isDateTimeClass($schema->type()) && $value instanceof \DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        if (TypeInfo::isEnum($schema->type()) && $value instanceof \UnitEnum) {
            return $value instanceof \BackedEnum ? $value->value : $value->name;
        }

        return $value;
    }

    /** @return array<string, Field> */
    private static function fieldMapFromSchema(Schema $schema) : array {
        if (!$schema->hasProperties()) {
            return [];
        }

        $requiredLookup = [];
        if ($schema instanceof ObjectSchema || $schema instanceof ArrayShapeSchema) {
            $requiredLookup = array_fill_keys($schema->required, true);
        }

        $fields = [];
        foreach ($schema->getPropertySchemas() as $name => $propertySchema) {
            $fields[$name] = Field::fromSchema($name, $propertySchema, isset($requiredLookup[$name]));
        }

        return $fields;
    }

    private static function path(string $base, string $segment) : string {
        if ($base === '') {
            return $segment;
        }

        return $base . '.' . $segment;
    }
}
