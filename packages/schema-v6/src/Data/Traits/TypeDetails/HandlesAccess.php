<?php

namespace Cognesy\Schema\Data\Traits\TypeDetails;

use Cognesy\Schema\Data\TypeDetails;

trait HandlesAccess
{
    public function type() : string {
        return $this->type;
    }

    public function class() : ?string {
        return $this->class;
    }

    public function nestedType() : ?TypeDetails {
        return $this->nestedType;
    }

    public function enumType() : ?string {
        return $this->enumType;
    }

    public function enumValues() : ?array {
        return $this->enumValues;
    }

    public function docString() : string {
        return $this->docString;
    }

    public function isScalar() : bool {
        return in_array($this->type, [self::PHP_INT, self::PHP_STRING, self::PHP_BOOL, self::PHP_FLOAT]);
    }

    public function isObject() : bool {
        return $this->type === self::PHP_OBJECT;
    }

    public function isEnum() : bool {
        return $this->type === self::PHP_ENUM;
    }

    public function isArray() : bool {
        return in_array($this->type, [self::PHP_ARRAY, self::PHP_COLLECTION]);
    }

    public function isCollection() : bool {
        return $this->type === self::PHP_COLLECTION;
    }

    public function isCollectionOf(string $type) : bool {
        return $this->isCollection() && $this->nestedType->type() === $type;
    }

    public function isCollectionOfScalar() : bool {
        return $this->isCollection() && $this->nestedType->isScalar();
    }

    public function isCollectionOfObject() : bool {
        return $this->isCollection() && $this->nestedType->isObject();
    }

    public function isCollectionOfEnum() : bool {
        return $this->isCollection() && $this->nestedType->isEnum();
    }

    public function isCollectionOfArray() : bool {
        return $this->isCollection() && $this->nestedType->isArray();
    }

    public function hasNestedType() : bool {
        return $this->nestedType !== null;
    }

    public function hasClass() : bool {
        return $this->class !== null;
    }

    public function hasEnumType() : bool {
        return $this->enumType !== null;
    }
}