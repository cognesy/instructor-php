<?php

namespace Cognesy\Instructor\Schema\PropertyInfoBased\Data\Schema;

use Cognesy\Instructor\Schema\PropertyInfoBased\Data\TypeDetails;

class ObjectSchema extends Schema
{
    /** @var Schema[] */
    public array $properties = []; // for objects OR empty
    /** @var string[] */
    public array $required = []; // for objects OR empty

    public function __construct(
        TypeDetails $type,
        string $name = '',
        string $description = '',
        array $properties = [],
        array $required = [],
    ) {
        parent::__construct($type, $name, $description);
        $this->properties = $properties;
        $this->required = $required;
    }

    public function toArray(callable $refCallback = null) : array
    {
        $propertyDefs = [];
        foreach ($this->properties as $property) {
            $propertyDefs[$property->name] = $property->toArray($refCallback);
        }
        return array_filter([
            'type' => 'object',
            'properties' => $propertyDefs,
            'required' => $this->required,
            'description' => $this->description,
        ]);
    }
}
