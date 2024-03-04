<?php
namespace Cognesy\Instructor\Schema\ReflectionBased\Reflection\TypeDefs;

use Cognesy\Instructor\Schema\ReflectionBased\Reflection\Enums\PhpType;

abstract class TypeDef
{
    public PhpType $type = PhpType::UNDEFINED;
}
