<?php

namespace Cognesy\Instructor\Schema\PropertyInfoBased\Data\Schema;

class ScalarSchema extends Schema
{
    public function toArray(callable $refCallback = null) : array
    {
        return array_filter([
            'type' => $this->type->jsonType(),
            'description' => $this->description,
        ]);
    }
}
