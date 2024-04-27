<?php

namespace Cognesy\Instructor\Schema\Data\Schema;

use Exception;

class ScalarSchema extends Schema
{
    /**
     * Renders scalar schema
     */
    public function toArray(callable $refCallback = null) : array
    {
        return array_filter([
            'type' => $this->type->jsonType(),
            'description' => $this->description,
        ]);
    }

    public function toXml(bool $asArrayItem = false) : string {
        $xml = [];
        if (!$asArrayItem) {
            $xml[] = '<parameter>';
            $xml[] = '<name>'.$this->name.'</name>';
            $xml[] = '<type>'.$this->type->jsonType().'</type>';
            if ($this->description) {
                $xml[] = '<description>'.trim($this->description).'</description>';
            }
            $xml[] = '</parameter>';
        } else {
            $xml[] = '<type>'.$this->type->jsonType().'</type>';
            if ($this->description) {
                $xml[] = '<description>'.trim($this->description).'</description>';
            }
        }
        return implode($this->xmlLineSeparator, $xml);
    }
}
