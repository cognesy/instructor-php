<?php

namespace Cognesy\Instructor\Schema\Data\Schema;

class EnumSchema extends Schema
{
    /**
     * Renders enum schema
     */
    public function toArray(callable $refCallback = null) : array
    {
        return array_filter([
            'type' => $this->type->enumType ?? 'string',
            'enum' => $this->type->enumValues ?? [],
            'description' => $this->description ?? '',
            '$comment' => $this->type->class ?? '',
        ]);
    }

    public function toXml(bool $asArrayItem = false) : string {
        $xml = [];
        if (!$asArrayItem) {
            $xml[] = '<parameter>';
            $xml[] = '<name>'.$this->name.'</name>';
            $xml[] = '<type>'.$this->type->enumType.'</type>';
            if ($this->description) {
                $xml[] = '<description>'.trim($this->description).'</description>';
            }
            $xml[] = '<enum>';
            $xml[] = implode($this->xmlLineSeparator, array_map(fn($v) => '<value>'.$v.'</value>', $this->type->enumValues));
            $xml[] = '</enum>';
            $xml[] = '</parameter>';
        } else {
            $xml[] = '<type>'.$this->type->enumType.'</type>';
            if ($this->description) {
                $xml[] = '<description>'.trim($this->description).'</description>';
            }
            $xml[] = '<enum>';
            $xml[] = implode($this->xmlLineSeparator, array_map(fn($v) => '<value>'.$v.'</value>', $this->type->enumValues));
            $xml[] = '</enum>';
        }
        return implode($this->xmlLineSeparator, $xml);
    }
}
