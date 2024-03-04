<?php

namespace Cognesy\Instructor\Schema\ReflectionBased\Reflection\TypeDefs;

use Cognesy\Instructor\Schema\ReflectionBased\Reflection\Enums\PhpType;

class ObjectTypeDef extends TypeDef
{
    public string $className;

    public function __construct(string $className) {
        $this->type = PhpType::OBJECT;
        $this->className = $className;
    }
}
