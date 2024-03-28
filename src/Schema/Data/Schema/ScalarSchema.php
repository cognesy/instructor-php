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

    public function toXml() : string {
        $lines = [
            '<parameter>',
            '<name>'.$this->name.'</name>',
            '<type>'.$this->type->jsonType().'</type>',
            '<description>'.$this->description.'</description>',
            '</parameter>',
        ];
        return implode("\n", $lines);
    }
}
