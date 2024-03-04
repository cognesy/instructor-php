<?php

namespace Cognesy\Instructor\Schema\ReflectionBased\Reflection\TypeDefs;

use Cognesy\Instructor\Schema\ReflectionBased\Reflection\Enums\PhpType;

class UndefinedTypeDef extends TypeDef
{
    public PhpType $type = PhpType::UNDEFINED;
}