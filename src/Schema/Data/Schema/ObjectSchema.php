<?php

namespace Cognesy\Instructor\Schema\Data\Schema;

use Cognesy\Instructor\Schema\Data\TypeDetails;

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
            'title' => $this->name,
            'description' => $this->description,
            'properties' => $propertyDefs,
            'required' => $this->required,
            '$comment' => $this->type->class,
        ]);
    }

    public function getPropertyNames() : array {
        return array_keys($this->properties);
    }

    public function toXml(bool $asArrayItem = false) : string {
        $xml = [];
        if (!$asArrayItem) {
            $xml[] = '<parameter>';
            $xml[] = '<name>'.$this->name.'</name>';
            $xml[] = '<type>object</type>';
            if ($this->description) {
                $xml[] = '<description>'.trim($this->description).'</description>';
            }
            $xml[] = '<properties>';
            $xml[] = implode($this->xmlLineSeparator, array_map(fn($p) => $p->toXml(), $this->properties));
            $xml[] = '</properties>';
            $xml[] = '</parameter>';
        } else {
            $xml[] = '<type>object</type>';
            if ($this->description) {
                $xml[] = '<description>'.trim($this->description).'</description>';
            }
            $xml[] = '<properties>';
            $xml[] = implode($this->xmlLineSeparator, array_map(fn($p) => $p->toXml(), $this->properties));
            $xml[] = '</properties>';
        }
        return implode($this->xmlLineSeparator, $xml);
    }
}
