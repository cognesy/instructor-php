<?php

namespace Cognesy\Instructor\Extras\Tuple;

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
    private TypeDetails $typeDetails;
    private Field $nestedField;

    public function __construct(string $name = '') {
        $this->name = $name;
    }

    static public function define(string $name) : self {
        return new Field($name);
    }

    public function description(string $description) : self {
        $this->description = $description;
        return $this;
    }

    public function int() : self {
        $this->typeDetails = new TypeDetails('int');
        return $this;
    }

    public function string() : self {
        $this->typeDetails = new TypeDetails('string');
        return $this;
    }

    public function float() : self {
        $this->typeDetails = new TypeDetails('float');
        return $this;
    }

    public function bool() : self {
        $this->typeDetails = new TypeDetails('bool');
        return $this;
    }

    public function enum(string $enumClass) : self {
        $reflection = new ReflectionEnum($enumClass);
        $backingType = $reflection->getBackingType()?->getName();
        $enumValues = array_values($reflection->getConstants());
        $this->typeDetails = new TypeDetails('enum', $enumClass, null, $backingType, $enumValues);
        return $this;
    }

    public function object(string $objectClass) : self {
        $this->typeDetails = new TypeDetails('object', $objectClass);
        return $this;
    }

    public function arrayOf(Field $field) : self {
        $this->nestedField = $field;
        $this->typeDetails = new TypeDetails('array', null, $field->typeDetails, null, null);
        return $this;
    }

    public function schema() : Schema {
        return match($this->typeDetails->type) {
            'object' => $this->objectSchema(),
            'enum' => $this->enumSchema(),
            'array' => $this->arraySchema(),
            default => $this->scalarSchema(),
        };
    }

    public function typeDetails() : TypeDetails {
        return $this->typeDetails;
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