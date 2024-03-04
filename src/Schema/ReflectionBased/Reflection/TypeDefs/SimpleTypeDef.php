<?php

namespace Cognesy\Instructor\Schema\ReflectionBased\Reflection\TypeDefs;

use Cognesy\Instructor\Schema\ReflectionBased\Reflection\Enums\PhpType;

class SimpleTypeDef extends TypeDef
{
    public function __construct(PhpType $type)
    {
        $this->type = $type;
    }
}