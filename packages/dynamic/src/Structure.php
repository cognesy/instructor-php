<?php declare(strict_types=1);

namespace Cognesy\Dynamic;

use Cognesy\Dynamic\Internal\StructureSchemaValidator;
use Cognesy\Dynamic\Internal\StructureValueNormalizer;
use Cognesy\Instructor\Transformation\Contracts\CanTransformSelf;
use Cognesy\Instructor\Validation\Contracts\CanValidateSelf;
use Cognesy\Instructor\Validation\ValidationResult;
use Cognesy\Schema\Contracts\CanProvideSchema;
use Cognesy\Schema\Data\Schema;
use Cognesy\Schema\SchemaFactory;

final class Structure implements CanProvideSchema, CanValidateSelf, CanTransformSelf
{
    /** @var array<string,mixed> */
    private array $data;
    private readonly StructureSchemaValidator $validator;
    private readonly StructureValueNormalizer $normalizer;

    /** @param array<string,mixed> $data */
    public function __construct(
        private readonly Schema $schema,
        array $data = [],
    ) {
        $this->normalizer = new StructureValueNormalizer($schema);
        $this->validator = new StructureSchemaValidator($schema);
        $this->data = $this->normalizer->normalizeRecord($data);
    }

    public static function fromSchema(Schema $schema, array $data = []) : self {
        return new self($schema, $data);
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
        return SchemaFactory::default()->toJsonSchema($this->schema());
    }

    public function withData(array $data) : self {
        $copy = clone $this;
        $copy->data = $copy->normalizer->normalizeRecord($data);
        return $copy;
    }

    /**
     * Compatibility no-op. Runtime validation is schema-only.
     *
     * @param callable(self): (bool|ValidationResult) $validator
     */
    public function withValidation(callable $validator) : self {
        return clone $this;
    }

    /**
     * @param array<string,mixed> $values
     * @return array<string,mixed>
     */
    public function normalizeRecord(array $values) : array {
        return $this->normalizer->normalizeRecord($values);
    }

    public function has(string $name) : bool {
        return $this->schema->hasProperty($name);
    }

    public function get(string $name) : mixed {
        if (!$this->has($name)) {
            throw new \InvalidArgumentException('Property not found: ' . $name);
        }

        if (array_key_exists($name, $this->data)) {
            return $this->data[$name];
        }

        $schema = $this->schema->getPropertySchema($name);
        return $schema->hasDefaultValue() ? $schema->defaultValue() : null;
    }

    public function set(string $name, mixed $value) : self {
        if (!$this->has($name)) {
            throw new \InvalidArgumentException('Property not found: ' . $name);
        }

        $copy = clone $this;
        $copy->data[$name] = $copy->normalizer->normalizeFieldValue($name, $value);
        return $copy;
    }

    #[\Override]
    public function validate() : ValidationResult {
        return $this->validator->validate($this->data);
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
        if (!$this->has($name)) {
            throw new \InvalidArgumentException('Property not found: ' . $name);
        }

        throw new \BadMethodCallException('Structure is immutable. Use set() and reassign returned instance.');
    }

    public function __isset(string $name) : bool {
        if (!$this->has($name)) {
            return false;
        }

        if (array_key_exists($name, $this->data)) {
            return $this->data[$name] !== null;
        }

        $schema = $this->schema->getPropertySchema($name);
        if (!$schema->hasDefaultValue()) {
            return false;
        }

        return $schema->defaultValue() !== null;
    }
}
