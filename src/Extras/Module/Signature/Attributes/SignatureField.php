<?php

namespace Cognesy\Instructor\Extras\Module\Signature\Attributes;

use Cognesy\Instructor\Schema\Data\TypeDetails;
use Cognesy\Instructor\Schema\Factories\TypeDetailsFactory;

abstract class SignatureField
{
    public string $name = '';
    public string $description = '';
    public TypeDetails $type;

    public function __construct(
        string $description = '',
    ) {
        $this->description = $description;
    }

    public function name(): string {
        return $this->name;
    }

    public function description(): string {
        return $this->description;
    }

    public function type(): TypeDetails {
        return $this->type;
    }

    public static function string(string $name, string $description = '') {
        $field = new static($description);
        $field->name = $name;
        $field->type = (new TypeDetailsFactory)->scalarType('string');
        return $field;
    }

    public static function int(string $name, string $description = '') {
        $field = new static($description);
        $field->name = $name;
        $field->type = (new TypeDetailsFactory)->scalarType('int');
        return $field;
    }

    public static function float(string $name, string $description = '') {
        $field = new static($description);
        $field->name = $name;
        $field->type = (new TypeDetailsFactory)->scalarType('float');
        return $field;
    }

    public static function bool(string $name, string $description = '') {
        $field = new static($description);
        $field->name = $name;
        $field->type = (new TypeDetailsFactory)->scalarType('bool');
        return $field;
    }

    public static function collection(string $name, string $class, string $description = '') {
        $field = new static($description);
        $field->name = $name;
        $field->type = (new TypeDetailsFactory)->collectionType($class);
        return $field;
    }

    public static function array(string $name, string $description = '') {
        // TODO: implement support for arbitrary arrays
    }

    public static function object(string $name, string $class, string $description = '') {
        $field = new static($description);
        $field->name = $name;
        $field->type = (new TypeDetailsFactory)->objectType($class);
        return $field;
    }

    public static function enum(string $name, string $class, string $description = '') {
        $field = new static($description);
        $field->name = $name;
        $values = (new $class)->values();
        $field->type = (new TypeDetailsFactory)->enumType($class, $values);
        return $field;
    }
}