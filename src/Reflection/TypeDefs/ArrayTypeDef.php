<?php

namespace Cognesy\Instructor\Reflection\TypeDefs;

use Cognesy\Instructor\Reflection\Enums\PhpType;

class ArrayTypeDef extends TypeDef
{
    public ?PhpType $keyType;
    public ?TypeDef $valueType;

    public function __construct(PhpType $keyType, TypeDef $valueType)
    {
        $this->type = PhpType::ARRAY;
        $this->keyType = $keyType;
        $this->valueType = $valueType;
    }
}
