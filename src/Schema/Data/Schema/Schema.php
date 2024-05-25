<?php

namespace Cognesy\Instructor\Schema\Data\Schema;

use Cognesy\Instructor\Schema\Data\TypeDetails;

class Schema
{
    public TypeDetails $type;
    public string $name = '';
    public string $description = '';
    public mixed $example = null;

    protected string $xmlLineSeparator = "";

    public function __construct(
        TypeDetails $type,
        string $name = '',
        string $description = '',
    ) {
        $this->type = $type;
        $this->name = $name;
        $this->description = $description;
    }

    static public function undefined() : self {
        return new self(TypeDetails::undefined());
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

    public function toXml(bool $asArrayItem = false) : string {
        return '';
    }
}
