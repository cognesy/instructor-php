<?php

namespace Cognesy\Instructor\Extras\Structure;

use Cognesy\Instructor\Schema\Data\Schema\ArraySchema;
use Cognesy\Instructor\Schema\Data\Schema\EnumSchema;
use Cognesy\Instructor\Schema\Data\Schema\ObjectSchema;
use Cognesy\Instructor\Schema\Data\Schema\ScalarSchema;
use Cognesy\Instructor\Schema\Data\Schema\Schema;
use Cognesy\Instructor\Schema\Data\TypeDetails;
use Cognesy\Instructor\Validation\ValidationResult;
use ReflectionEnum;

class Field {
    private string $name;
    private string $description;
    private mixed $value = null;
    private bool $required = true;
    private array $examples;
    private TypeDetails $typeDetails;
    private Field $nestedField;
    private $validator;

    public function __construct(string $name = '') {
        $this->name = $name;
    }

    static public function int(string $description = '') : self {
        $result = new Field();
        $result->typeDetails = new TypeDetails('int');
        return $result;
    }

    static public function string(string $description = '') : self {
        $result = new Field();
        $result->typeDetails = new TypeDetails('string');
        return $result;
    }

    static public function float(string $description = '') : self {
        $result = new Field();
        $result->typeDetails = new TypeDetails('float');
        return $result;
    }

    static public function bool(string $description = '') : self {
        $result = new Field();
        $result->typeDetails = new TypeDetails('bool');
        return $result;
    }

    static public function enum(string $enumClass, string $description = '') : self {
        $result = new Field();
        $reflection = new ReflectionEnum($enumClass);
        $backingType = $reflection->getBackingType()?->getName();
        $enumValues = array_values($reflection->getConstants());
        $result->typeDetails = new TypeDetails('enum', $enumClass, null, $backingType, $enumValues);
        return $result;
    }

    static public function object(string $class, string $description = '') : self {
        $result = new Field();
        $result->typeDetails = new TypeDetails('object', $class);
        return $result;
    }

    static public function structure(Structure $structure, string $description = '') : self {
        $result = new Field();
        $result->typeDetails = new TypeDetails('object', Structure::class);
        $result->value = $structure;
        $result->description = $description;
        return $result;
    }

//    static public function array(Structure $structure, string $description = '') : self {
//        $result = new Field();
//        $result->typeDetails = new TypeDetails('array', null, new TypeDetails('object', Structure::class));
//        $result->value = $structure;
//        $result->description = $description;
//        return $result;
//    }

//    static public function datetime(string $format, string $description = '') : self {
//        $result = new Field();
//        $result->typeDetails = new TypeDetails('object', 'DateTime', $format);
//        return $result;
//    }

//    static public function array(Field $field) : self {
//        $result = new Field();
//        $result->nestedField = $field;
//        $result->typeDetails = new TypeDetails('array', null, $field->typeDetails, null, null);
//        return $result;
//    }

    ///////////////////////////////////////////////////////////////////////////////////////////

    public function examples(array $examples) : self {
        $this->examples = $examples;
        return $this;
    }

    public function required(bool $isRequired = false) : self {
        $this->required = $isRequired;
        return $this;
    }

    public function optional(bool $isOptional = true) : self {
        $this->required = !$isOptional;
        return $this;
    }

    public function typeDetails() : TypeDetails {
        return $this->typeDetails;
    }

    /**
     * Sets field value
     *
     * @param mixed $value
     * @return $this
     */
    public function set(mixed $value) : self {
        $this->value = $value;
        return $this;
    }

    /**
     * Returns field value
     */
    public function get() : mixed {
        return $this->value;
    }

    /**
     * Defines a simple, inline validator for the field - the callback has to return true/false
     *
     * @param callable $validator
     * @return $this
     */
    public function validIf(callable $validator, string $error = '') : self {
        $this->validator = function() use ($validator, $error) {
            $result = $validator($this->value);
            if ($result === false) {
                return ValidationResult::fieldError($this->name, $this->value, $error ?: "Invalid field value");
            }
            return ValidationResult::valid();
        };
        return $this;
    }

    /**
     * Defines validator for the field - the callback has to return ValidationResult
     *
     * @param callable $validator
     * @return $this
     */
    public function validator(callable $validator) : self {
        $this->validator = $validator;
        return $this;
    }

    /**
     * Validates the field value
     *
     * @return ValidationResult
     */
    public function validate() : ValidationResult {
        if ($this->validator === null) {
            return ValidationResult::valid();
        }
        return ($this->validator)($this->value);
    }

    public function isRequired() : bool {
        return $this->required;
    }

    ///////////////////////////////////////////////////////////////////////////////////////////

    public function withName(string $name) : self {
        $this->name = $name;
        if ($this->typeDetails->class === Structure::class) {
            $this->value->withName($name);
        }
        return $this;
    }

    public function withDescription(string $description) : self {
        $this->description = $description;
        return $this;
    }

    public function name() : string {
        return $this->name ?? '';
    }

    public function description() : string {
        return $this->description ?? '';
    }

    public function isEmpty() : bool {
        return is_null($this->value) || empty($this->value);
    }

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
            nestedItemSchema: $this->nestedField->schema(), // TODO: ERROR!
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