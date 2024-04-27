<?php

namespace Cognesy\Instructor\Extras\Structure;

use Closure;
use Cognesy\Instructor\Contracts\CanDeserializeSelf;
use Cognesy\Instructor\Contracts\CanProvideSchema;
use Cognesy\Instructor\Contracts\CanValidateSelf;
use Cognesy\Instructor\Deserialization\Symfony\Deserializer;
use Cognesy\Instructor\Schema\Data\Schema\ObjectSchema;
use Cognesy\Instructor\Schema\Data\Schema\Schema;
use Cognesy\Instructor\Schema\Data\TypeDetails;
use Cognesy\Instructor\Schema\Factories\SchemaFactory;
use Cognesy\Instructor\Schema\Factories\TypeDetailsFactory;
use Cognesy\Instructor\Utils\Json;
use Cognesy\Instructor\Validation\ValidationResult;

class Structure implements CanProvideSchema, CanDeserializeSelf, CanValidateSelf
{
    protected string $name = '';
    protected string $description = '';
    /** @var Field[] */
    protected array $fields = [];
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
        $this->fields = $fieldDefs($this);
        return $this;
    }

    public function fields() : array {
        return $this->fields;
    }

    public function get(string $field) {
        return $this->fields[$field]->get() ?? null;
    }

    public function set(string $field, mixed $value) {
        $this->fields[$field]->set($value);
    }

    public function fromJson(string $jsonData): static {
        $data = Json::parse($jsonData);
        foreach ($data as $name => $jsonValue) {
            $type = $this->fields[$name]->typeDetails() ?? null;
            if ($type === null) {
                throw new \Exception("Undefined field `$name` found in JSON data.");
            }
            if ($type->class === null) {
                $value = $jsonValue;
            } else {
                $value = $this->deserializer->fromJson($jsonValue, $type->class);
            }
            $this->set($name, $value);
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
            name: 'Structure',
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