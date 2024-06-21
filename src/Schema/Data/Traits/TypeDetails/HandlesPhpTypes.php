<?php

namespace Cognesy\Instructor\Schema\Data\Traits\TypeDetails;

trait HandlesPhpTypes
{
    use DefinesPhpTypeConstants;

    static public function getType(mixed $variable) : ?string {
        $type = gettype($variable);
        return self::TYPE_MAP[$type] ?? self::PHP_UNSUPPORTED;
    }

    public function shortName() : string {
        return match ($this->type) {
            self::PHP_OBJECT => $this->classOnly(),
            self::PHP_ENUM => "one of: ".implode(', ', $this->enumValues),
            self::PHP_COLLECTION => $this->nestedType->shortName().'[]',
            self::PHP_ARRAY => 'array',
            self::PHP_SHAPE => 'struct: '.$this->docString,
            default => $this->type,
        };
    }

    public function classOnly() : string {
        if (!in_array($this->type, [self::PHP_OBJECT, self::PHP_ENUM])) {
            throw new \Exception('Trying to get class name for type that is not an object or enum');
        }
        $segments = explode('\\', $this->class);
        return array_pop($segments);
    }

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
}