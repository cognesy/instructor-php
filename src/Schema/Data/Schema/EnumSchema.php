<?php

namespace Cognesy\Instructor\Schema\Data\Schema;

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

    public function toXml() : string {
        $lines = [
            '<parameter>',
            '<name>'.$this->name.'</name>',
            '<type>'.$this->type->enumType.'</type>',
            '<description>'.$this->description.'</description>',
            '<enum>',
            implode("\n", array_map(fn($v) => '<value>'.$v.'</value>', $this->type->enumValues)),
            '</enum>',
            '</parameter>',
        ];
        return implode("\n", $lines);
    }
}
