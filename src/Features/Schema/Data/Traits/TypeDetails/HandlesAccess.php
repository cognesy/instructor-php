<?php

namespace Cognesy\Instructor\Features\Schema\Data\Traits\TypeDetails;

use Cognesy\Instructor\Features\Schema\Data\TypeDetails;

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
}