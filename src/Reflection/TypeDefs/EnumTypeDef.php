<?php

namespace Cognesy\Instructor\Reflection\TypeDefs;

use Cognesy\Instructor\Reflection\Enums\PhpType;

class EnumTypeDef extends ObjectTypeDef
{
    public array $values = [];

    public function __construct(string $className, array $values)
    {
        parent::__construct($className);
        $this->type = PhpType::ENUM;
        $this->values = $values;
    }
}
