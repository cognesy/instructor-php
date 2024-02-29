<?php
namespace Cognesy\Instructor\Attributes;

use Attribute;
use ReflectionClass;

// Currently, this attribute cannot be correctly deserialized by Symfony Serializer.
// Use PHPDoc instead for typed arrays.

#[Attribute]
class ArrayOf
{
    public function __construct(
        public string|ReflectionClass $valueType,
        public string $keyType = '',
    ) {}
}
