<?php

namespace Cognesy\Instructor\Extras\Structure;

use Cognesy\Instructor\Schema\Data\Schema\ArraySchema;
use Cognesy\Instructor\Schema\Data\Schema\EnumSchema;
use Cognesy\Instructor\Schema\Data\Schema\ObjectSchema;
use Cognesy\Instructor\Schema\Data\Schema\ScalarSchema;
use Cognesy\Instructor\Schema\Data\Schema\Schema;
use Cognesy\Instructor\Schema\Data\TypeDetails;
use ReflectionEnum;

class Field {
    private string $name;
    private string $description;
    private mixed $value;
    private bool $required = false;
    private mixed $example;
    private TypeDetails $typeDetails;
    private Field $nestedField;

    public function __construct(string $name = '') {
        $this->name = $name;
    }

    static public function int(string $name = '') : self {
        $result = new Field();
        $result->typeDetails = new TypeDetails('int');
        return $result;
    }

    static public function string(string $name = '') : self {
        $result = new Field();
        $result->typeDetails = new TypeDetails('string');
        return $result;
    }

    static public function float(string $name = '') : self {
        $result = new Field();
        $result->typeDetails = new TypeDetails('float');
        return $result;
    }

    static public function bool(string $name = '') : self {
        $result = new Field();
        $result->typeDetails = new TypeDetails('bool');
        return $result;
    }

    static public function enum(string $enumClass, string $name = '') : self {
        $result = new Field();
        $reflection = new ReflectionEnum($enumClass);
        $backingType = $reflection->getBackingType()?->getName();
        $enumValues = array_values($reflection->getConstants());
        $result->typeDetails = new TypeDetails('enum', $enumClass, null, $backingType, $enumValues);
        return $result;
    }

    static public function object(string $objectClass, string $name = '') : self {
        $result = new Field();
        $result->typeDetails = new TypeDetails('object', $objectClass);
        return $result;
    }

    static public function arrayOf(Field $field) : self {
        $result = new Field();
        $result->nestedField = $field;
        $result->typeDetails = new TypeDetails('array', null, $field->typeDetails, null, null);
        return $result;
    }

    public function description(string $description) : self {
        $this->description = $description;
        return $this;
    }

    public function example(mixed $example) : self {
        $this->example = $example;
        return $this;
    }

    public function required(bool $isRequired = false) : self {
        $this->required = $isRequired;
        return $this;
    }

    public function typeDetails() : TypeDetails {
        return $this->typeDetails;
    }

    public function set(mixed $value) : self {
        $this->value = $value;
        return $this;
    }

    public function get() : mixed {
        return $this->value;
    }

    ///////////////////////////////////////////////////////////////////////////////////////////

    private function makeSchema() : Schema {
        return match($this->typeDetails->type) {
            'object' => $this->objectSchema(),
            'enum' => $this->enumSchema(),
            'array' => $this->arraySchema(),
            default => $this->scalarSchema(),
        };
    }

    private function objectSchema() : ObjectSchema {
        return new ObjectSchema(
            type: $this->typeDetails,
            name: $this->name,
            description: $this->description,
        );
    }

    private function enumSchema() : Schema {
        return new EnumSchema(
            type: $this->typeDetails,
            name: $this->name,
            description: $this->description,
        );
    }

    private function arraySchema() : Schema {
        return new ArraySchema(
            type: $this->typeDetails,
            name: $this->name,
            description: $this->description,
            nestedItemSchema: $this->nestedField->schema(),
        );
    }

    private function scalarSchema() : Schema {
        return new ScalarSchema(
            type: $this->typeDetails,
            name: $this->name,
            description: $this->description,
        );
    }
}