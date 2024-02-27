<?php

namespace Cognesy\Instructor\Reflection\TypeDefs;

use Cognesy\Instructor\Reflection\Enums\PhpType;

class SimpleTypeDef extends TypeDef
{
    public function __construct(PhpType $type)
    {
        $this->type = $type;
    }
}