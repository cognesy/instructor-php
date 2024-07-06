<?php

namespace Cognesy\Instructor\Extras\Module\Signature\Attributes;

use Cognesy\Instructor\Schema\Data\TypeDetails;

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
        $field->type = TypeDetails::string();
        return $field;
    }

    public static function int(string $name, string $description = '') {
        $field = new static($description);
        $field->name = $name;
        $field->type = TypeDetails::int();
        return $field;
    }

    public static function float(string $name, string $description = '') {
        $field = new static($description);
        $field->name = $name;
        $field->type = TypeDetails::float();
        return $field;
    }

    public static function bool(string $name, string $description = '') {
        $field = new static($description);
        $field->name = $name;
        $field->type = TypeDetails::bool();
        return $field;
    }

    public static function collection(string $name, string $nestedTypeName, string $description = '') {
        $field = new static($description);
        $field->name = $name;
        $field->type = TypeDetails::collection(TypeDetails::fromTypeName($nestedTypeName));
        return $field;
    }

    public static function array(string $name, string $description = '') {
        $field = new static($description);
        $field->name = $name;
        $field->type = TypeDetails::array();
        return $field;
    }

    public static function object(string $name, string $class, string $description = '') {
        $field = new static($description);
        $field->name = $name;
        $field->type = TypeDetails::object($class);
        return $field;
    }

    public static function enum(string $name, string $class, string $description = '') {
        $field = new static($description);
        $field->name = $name;
        $field->type = TypeDetails::enum($class);
        return $field;
    }
}