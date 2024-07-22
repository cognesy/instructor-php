<?php

namespace Cognesy\Instructor\Schema\Data\Traits\TypeDetails;

trait HandlesConversion
{
    public function toString() : string {
        return match ($this->type) {
            self::PHP_OBJECT => $this->class,
            self::PHP_ENUM => $this->class,
            self::PHP_COLLECTION => $this->nestedType->__toString().'[]',
            self::PHP_ARRAY => 'array',
            self::PHP_SHAPE => $this->docString,
            default => $this->type,
        };
    }

    public function __toString() : string {
        return $this->toString();
    }
}