<?php

namespace Cognesy\Instructor\Schema\Data\Schema;

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
