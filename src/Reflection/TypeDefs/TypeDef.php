<?php
namespace Cognesy\Instructor\Reflection\TypeDefs;

use Cognesy\Instructor\Reflection\Enums\PhpType;

abstract class TypeDef
{
    public PhpType $type = PhpType::UNDEFINED;
}
