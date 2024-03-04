<?php

namespace Cognesy\Instructor\Schema\PropertyInfoBased\Data\Schema;

use Cognesy\Instructor\Schema\PropertyInfoBased\Data\TypeDetails;

class Schema
{
    public TypeDetails $type;
    public string $name = '';
    public string $description = '';

    public function __construct(
        TypeDetails $type,
        string $name = '',
        string $description = '',
    ) {
        $this->type = $type;
        $this->name = $name;
        $this->description = $description;
    }

    public function toArray(callable $refCallback = null) : array
    {
        return array_filter([
            'type' => $this->type->type,
            'description' => $this->description,
        ]);
    }
}
