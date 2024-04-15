<?php

namespace Cognesy\Instructor\Extras\Tuple;

use Closure;
use Cognesy\Instructor\Contracts\CanDeserializeSelf;
use Cognesy\Instructor\Contracts\CanProvideSchema;
use Cognesy\Instructor\Contracts\CanValidateSelf;
use Cognesy\Instructor\Deserializers\Symfony\Deserializer;
use Cognesy\Instructor\Schema\Data\Schema\ObjectSchema;
use Cognesy\Instructor\Schema\Data\Schema\Schema;
use Cognesy\Instructor\Schema\Data\TypeDetails;
use Cognesy\Instructor\Schema\Factories\SchemaFactory;
use Cognesy\Instructor\Schema\Factories\TypeDetailsFactory;
use Cognesy\Instructor\Validation\ValidationResult;

class Tuple implements CanProvideSchema, CanDeserializeSelf, CanValidateSelf
{
    protected string $name = '';
    protected string $description = '';
    protected array $fields = [];
    private array $fieldTypes = [];
    private TypeDetailsFactory $typeDetailsFactory;
    private SchemaFactory $schemaFactory;
    private Deserializer $deserializer;

    public function __construct(string $name = '', string $description = '') {
        $this->name = $name;
        $this->description = $description;
        $this->schemaFactory = new SchemaFactory(false);
        $this->typeDetailsFactory = new TypeDetailsFactory();
        $this->deserializer = new Deserializer();
    }

    public function define(Closure $fieldDefs) : self {
        $this->fieldTypes = $fieldDefs($this);
        return $this;
    }

    public function fields() : array {
        return $this->fields;
    }

    public function get(string $field) {
        return $this->fields[$field] ?? null;
    }

    public function set(string $field, mixed $value) {
        $this->fields[$field] = $value;
    }

    public function fromJson(string $jsonData): static {
        $data = json_decode($jsonData, true);
        foreach ($data as $field => $jsonValue) {
            $type = $this->fieldTypes[$field];
            $class = $type->class;
            if ($class === null) {
                $value = $this->deserializer->fromJson($jsonValue, $class);
            } else {
                $value = $jsonValue;
            }
            $this->set($field, $value);
        }
        return $this;
    }

    public function toSchema(): Schema {
        $properties = [];
        $required = [];
        foreach ($this->fields as $field => $value) {
            $valueType = $this->typeDetailsFactory->fromValue($value);
            $propertySchema = $this->schemaFactory->schema($valueType);
            $properties[$field] = $propertySchema;
            $required[] = $field;
        }
        $typeDetails = new TypeDetails(
            type: 'object',
            class: static::class,
            nestedType: null,
            enumType: null,
            enumValues: null,
        );
        $schema = new ObjectSchema(
            type: $typeDetails,
            name: 'Tuple',
            description: 'Extract data from provided content',
            properties: $properties,
            required: $required,
        );
        return $schema;
    }

    public function validate(): ValidationResult {
        return ValidationResult::valid();
    }
}