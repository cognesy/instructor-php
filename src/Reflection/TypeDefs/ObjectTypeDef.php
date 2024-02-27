<?php

namespace Cognesy\Instructor\Reflection\TypeDefs;

use Cognesy\Instructor\Reflection\Enums\PhpType;

class ObjectTypeDef extends TypeDef
{
    public string $className;

    public function __construct(string $className) {
        $this->type = PhpType::OBJECT;
        $this->className = $className;
    }
}
