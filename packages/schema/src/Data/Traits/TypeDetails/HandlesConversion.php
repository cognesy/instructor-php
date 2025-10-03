<?php declare(strict_types=1);

namespace Cognesy\Schema\Data\Traits\TypeDetails;

trait HandlesConversion
{
    public function toString() : string {
        return match ($this->type) {
            self::PHP_OBJECT => $this->class ?? 'object',
            self::PHP_ENUM => $this->class ?? 'enum',
            self::PHP_COLLECTION => ($this->nestedType?->__toString() ?? 'mixed').'[]',
            self::PHP_ARRAY => 'array',
            self::PHP_SHAPE => $this->docString ?? 'shape',
            default => $this->type,
        };
    }

    public function __toString() : string {
        return $this->toString();
    }
}