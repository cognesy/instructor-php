<?php

namespace Cognesy\Instructor\Reflection\TypeDefs;

use Cognesy\Instructor\Reflection\Enums\PhpType;

class UndefinedTypeDef extends TypeDef
{
    public PhpType $type = PhpType::UNDEFINED;
}