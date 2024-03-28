<?php

namespace Cognesy\Instructor\Schema\Data\Schema;

use Cognesy\Instructor\Schema\Data\TypeDetails;

abstract class Schema
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

    public function getPropertyNames() : array {
        return [];
    }

    public function toXml() : string {
        return '';
    }
}
