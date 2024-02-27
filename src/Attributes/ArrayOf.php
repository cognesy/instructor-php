<?php
namespace Cognesy\Instructor\Attributes;

use Attribute;
use ReflectionClass;

#[Attribute]
class ArrayOf {
    public function __construct(
        public string|ReflectionClass $valueType,
        public string $keyType = '',
    ) {}
}
