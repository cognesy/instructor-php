<?php

namespace Cognesy\Instructor\Schema\PropertyInfoBased\Data\Schema;

class EnumSchema extends Schema
{
    public function toArray(callable $refCallback = null) : array
    {
        return array_filter([
            'type' => $this->type->enumType ?? 'string',
            'enum' => $this->type->enumValues ?? [],
            'description' => $this->description ?? '',
        ]);
    }
}
